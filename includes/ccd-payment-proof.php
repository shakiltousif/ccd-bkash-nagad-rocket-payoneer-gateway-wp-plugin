<?php

/**
 * Payment screenshot / receipt upload ("payment proof").
 *
 * Flow: the customer picks a file at checkout, it is uploaded right away to a
 * wc-ajax endpoint and stored in a private folder with a random name. The
 * endpoint returns a token that is kept in the customer's WooCommerce session
 * and submitted with the order; on checkout the file is attached to the order.
 * Shop managers view files through an admin-only link. Files never enter the
 * Media Library. Unattached uploads are deleted after 24 hours.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Maximum file size in bytes: 5 MB, or less when the server's PHP upload
 * limit (upload_max_filesize / post_max_size) is lower.
 *
 * @return int
 */
function ccd_proof_max_bytes()
{
    return (int) min(5 * MB_IN_BYTES, wp_max_upload_size());
}

/**
 * "File is too large" message with the effective limit.
 *
 * @return string
 */
function ccd_proof_too_big_message()
{
    /* translators: %s: maximum file size, e.g. 2 MB */
    return sprintf(__('File is too large. Maximum size is %s.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), size_format(ccd_proof_max_bytes()));
}

/** Maximum uploads per customer session. */
define('CCD_PROOF_MAX_UPLOADS', 10);

/**
 * Allowed file types: extension => MIME type.
 *
 * @return array
 */
function ccd_proof_mimes()
{
    return array(
        'jpg|jpeg' => 'image/jpeg',
        'png'      => 'image/png',
        'webp'     => 'image/webp',
        'pdf'      => 'application/pdf',
    );
}

/**
 * Proof mode of a gateway: off, optional or required.
 *
 * @param string $gateway_id Gateway id.
 * @return string
 */
function ccd_proof_mode($gateway_id)
{
    $settings = ccd_gateway_settings($gateway_id);
    $mode     = isset($settings[$gateway_id . '_payment_proof']) ? $settings[$gateway_id . '_payment_proof'] : 'off';
    return in_array($mode, array('optional', 'required'), true) ? $mode : 'off';
}

/**
 * Private storage folder, created with deny rules on first use.
 *
 * @return string Absolute path with trailing slash, or '' on failure.
 */
function ccd_proof_dir()
{
    $uploads = wp_upload_dir(null, false);
    if (!empty($uploads['error'])) {
        return '';
    }
    $dir = trailingslashit($uploads['basedir']) . 'ccd-payment-proofs/';

    if (!is_dir($dir . 'tmp') && !wp_mkdir_p($dir . 'tmp')) {
        return '';
    }
    if (!is_dir($dir . 'orders')) {
        wp_mkdir_p($dir . 'orders');
    }
    if (!file_exists($dir . '.htaccess')) {
        // Apache; on Nginx the random file names keep files unguessable.
        @file_put_contents($dir . '.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        @file_put_contents($dir . 'index.php', "<?php\n// Silence is golden.\n"); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        @file_put_contents($dir . 'web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>'); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    return $dir;
}

/**
 * Uploads stored in the current session: token => file info.
 *
 * @return array
 */
function ccd_proof_session_files()
{
    if (!WC()->session) {
        return array();
    }
    $files = WC()->session->get('ccd_proof_files');
    return is_array($files) ? $files : array();
}

/**
 * Data for the checkout scripts.
 *
 * @return array
 */
function ccd_proof_script_data()
{
    return array(
        'uploadUrl' => WC_AJAX::get_endpoint('ccd_upload_proof'),
        'nonce'     => wp_create_nonce('ccd-proof'),
        'maxBytes'  => ccd_proof_max_bytes(),
        'accept'    => 'image/jpeg,image/png,image/webp,application/pdf',
        'i18n'      => array(
            'uploading' => __('Uploading...', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
            'uploaded'  => __('Uploaded:', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
            'tooBig'    => ccd_proof_too_big_message(),
            /* translators: %s: maximum file size */
            'hint'      => sprintf(__('JPG, PNG, WebP or PDF, max %s.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), size_format(ccd_proof_max_bytes())),
            'failed'    => __('Upload failed. Please try again.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        ),
    );
}

/**
 * Validate an uploaded file and move it into the private folder.
 *
 * @param string $field  $_FILES key.
 * @param string $subdir 'tmp/' (checkout, attached later) or 'orders/'.
 * @param string $prefix File name prefix (e.g. order id).
 * @return array{file:string,name:string,type:string}|WP_Error Error data is the HTTP status.
 */
function ccd_proof_store_upload($field, $subdir, $prefix = '')
{
    if (empty($_FILES[$field]) || !isset($_FILES[$field]['tmp_name'], $_FILES[$field]['name'], $_FILES[$field]['error'], $_FILES[$field]['size'])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing -- callers verify the nonce.
        return new WP_Error('ccd_proof', __('No file received.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), 400);
    }

    $file = $_FILES[$field]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- validated below; callers verify the nonce.
    if (in_array((int) $file['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
        return new WP_Error('ccd_proof', ccd_proof_too_big_message(), 400);
    }
    if (UPLOAD_ERR_NO_FILE === (int) $file['error']) {
        return new WP_Error('ccd_proof', __('Please choose a file to upload.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), 400);
    }
    if (UPLOAD_ERR_OK !== (int) $file['error'] || !is_uploaded_file($file['tmp_name'])) {
        return new WP_Error('ccd_proof', __('Upload failed. Please try again.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), 400);
    }
    if ((int) $file['size'] <= 0 || (int) $file['size'] > ccd_proof_max_bytes()) {
        return new WP_Error('ccd_proof', ccd_proof_too_big_message(), 400);
    }

    $name  = sanitize_file_name(wp_unslash($file['name']));
    $check = wp_check_filetype_and_ext($file['tmp_name'], $name, ccd_proof_mimes());
    if (empty($check['ext']) || empty($check['type'])) {
        return new WP_Error('ccd_proof', __('Please upload a JPG, PNG, WebP or PDF file.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), 400);
    }

    // Content check: real image, or a real PDF.
    if ('application/pdf' === $check['type']) {
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ('%PDF-' !== $head) {
            return new WP_Error('ccd_proof', __('This PDF file appears to be damaged.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), 400);
        }
    } elseif (false === @getimagesize($file['tmp_name'])) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return new WP_Error('ccd_proof', __('This image file appears to be damaged.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), 400);
    }

    $dir = ccd_proof_dir();
    if (!$dir) {
        return new WP_Error('ccd_proof', __('Upload failed. Please try again.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), 500);
    }

    $ext      = 'jpeg' === $check['ext'] ? 'jpg' : $check['ext'];
    $relative = $subdir . $prefix . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $dir . $relative)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return new WP_Error('ccd_proof', __('Upload failed. Please try again.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), 500);
    }
    @chmod($dir . $relative, 0640); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

    return array('file' => $relative, 'name' => $name, 'type' => $check['type']);
}

add_action('wc_ajax_ccd_upload_proof', 'ccd_ajax_upload_proof');
/**
 * Handle a payment proof upload from the checkout page.
 */
function ccd_ajax_upload_proof()
{
    if (!check_ajax_referer('ccd-proof', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Your session has expired. Please reload the page and try again.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')), 403);
    }

    $gateway_id = isset($_POST['gateway']) ? sanitize_key(wp_unslash($_POST['gateway'])) : '';
    $config     = ccd_gateway_config();
    if (!isset($config[$gateway_id]) || 'off' === ccd_proof_mode($gateway_id)) {
        wp_send_json_error(array('message' => __('File upload is not enabled for this payment method.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')), 400);
    }

    if (!WC()->session || !WC()->cart || WC()->cart->is_empty()) {
        wp_send_json_error(array('message' => __('Your cart is empty.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')), 400);
    }

    $files = ccd_proof_session_files();
    $count = (int) WC()->session->get('ccd_proof_upload_count');
    if ($count >= CCD_PROOF_MAX_UPLOADS) {
        wp_send_json_error(array('message' => __('Too many uploads. Please contact us if you need help.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')), 429);
    }

    $stored = ccd_proof_store_upload('file', 'tmp/');
    if (is_wp_error($stored)) {
        wp_send_json_error(array('message' => $stored->get_error_message()), (int) $stored->get_error_data());
    }
    $dir      = ccd_proof_dir();
    $relative = $stored['file'];
    $name     = $stored['name'];
    $check    = array('type' => $stored['type']);

    // Replace this gateway's previous upload in the session.
    foreach ($files as $old_token => $info) {
        if (isset($info['gateway']) && $info['gateway'] === $gateway_id) {
            wp_delete_file($dir . $info['file']);
            unset($files[$old_token]);
        }
    }

    $token         = bin2hex(random_bytes(16));
    $files[$token] = array(
        'file'    => $relative,
        'name'    => $name,
        'type'    => $check['type'],
        'gateway' => $gateway_id,
        'time'    => time(),
    );
    WC()->session->set('ccd_proof_files', $files);
    WC()->session->set('ccd_proof_upload_count', $count + 1);

    wp_send_json_success(array('token' => $token, 'name' => $name));
}

/**
 * Validation errors for the submitted proof token.
 *
 * @param string $gateway_id Gateway id.
 * @param string $token      Submitted token.
 * @return string[]
 */
function ccd_payment_proof_errors($gateway_id, $token)
{
    $mode = ccd_proof_mode($gateway_id);
    if ('off' === $mode) {
        return array();
    }

    $token = sanitize_key((string) $token);
    if ('' === $token) {
        return 'required' === $mode
            ? array(__('Please upload a screenshot or PDF of your payment.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'))
            : array();
    }

    $files = ccd_proof_session_files();
    if (!isset($files[$token]) || $files[$token]['gateway'] !== $gateway_id) {
        return array(__('Your payment screenshot upload has expired. Please upload it again.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'));
    }

    return array();
}

/**
 * Attach an uploaded proof to the order.
 *
 * @param WC_Order $order      Order.
 * @param string   $gateway_id Gateway id.
 * @param string   $token      Upload token.
 */
function ccd_attach_payment_proof($order, $gateway_id, $token)
{
    $token = sanitize_key((string) $token);
    $files = ccd_proof_session_files();
    $dir   = ccd_proof_dir();

    if (!$dir || '' === $token || !isset($files[$token]) || $files[$token]['gateway'] !== $gateway_id) {
        return;
    }

    $info     = $files[$token];
    $ext      = pathinfo($info['file'], PATHINFO_EXTENSION);
    $relative = 'orders/' . $order->get_id() . '-' . bin2hex(random_bytes(12)) . '.' . $ext;

    if (!@rename($dir . $info['file'], $dir . $relative)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
        return;
    }

    // Replace a proof from an earlier attempt of the same order.
    $previous = $order->get_meta('_ccd_payment_proof');
    if ($previous && 0 === strpos($previous, 'orders/')) {
        wp_delete_file($dir . $previous);
    }

    $order->update_meta_data('_ccd_payment_proof', $relative);
    $order->update_meta_data('_ccd_payment_proof_name', $info['name']);
    $order->update_meta_data('_ccd_payment_proof_type', $info['type']);
    $order->save();
    $order->add_order_note(__('Customer uploaded a payment screenshot.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'));

    unset($files[$token]);
    WC()->session->set('ccd_proof_files', $files);
}

/**
 * Admin link to view an order's proof, or '' when none.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function ccd_payment_proof_admin_url($order)
{
    if (!$order->get_meta('_ccd_payment_proof')) {
        return '';
    }
    return wp_nonce_url(admin_url('admin-post.php?action=ccd_view_proof&order_id=' . $order->get_id()), 'ccd_view_proof_' . $order->get_id());
}

add_action('admin_post_ccd_view_proof', 'ccd_view_proof');
/**
 * Stream an order's proof file to a shop manager.
 */
function ccd_view_proof()
{
    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
    check_admin_referer('ccd_view_proof_' . $order_id);

    if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Sorry, you are not allowed to view this file.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), '', array('response' => 403));
    }

    $order    = wc_get_order($order_id);
    $relative = $order ? (string) $order->get_meta('_ccd_payment_proof') : '';
    $dir      = ccd_proof_dir();
    $path     = $dir ? realpath($dir . $relative) : false;
    $base     = $dir ? realpath($dir . 'orders') : false;

    if (!$relative || !$path || !$base || 0 !== strpos($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
        wp_die(esc_html__('File not found.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), '', array('response' => 404));
    }

    $type = (string) $order->get_meta('_ccd_payment_proof_type');
    if (!in_array($type, array_values(ccd_proof_mimes()), true)) {
        $type = 'application/octet-stream';
    }

    nocache_headers();
    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="proof-' . $order_id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
}

/**
 * Classic checkout: upload field inside the payment box.
 *
 * @param string $gateway_id Gateway id.
 */
function ccd_render_payment_proof_field($gateway_id)
{
    $mode = ccd_proof_mode($gateway_id);
    if ('off' === $mode) {
        return;
    }

    // Keep an upload made earlier in this session (the payment box is re-rendered on every checkout update).
    $token = '';
    $name  = '';
    foreach (ccd_proof_session_files() as $file_token => $info) {
        if ($info['gateway'] === $gateway_id) {
            $token = $file_token;
            $name  = $info['name'];
        }
    }

    $label = 'required' === $mode
        ? __('Payment screenshot or PDF', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . ' <abbr class="required" title="' . esc_attr__('required', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '">*</abbr>'
        : __('Payment screenshot or PDF (optional)', 'codecarebd-bkash-nagad-rocket-payoneer-gateway');

    echo '<div class="ccd-proof-field" data-gateway="' . esc_attr($gateway_id) . '">';
    echo '<label for="ccd_proof_file_' . esc_attr($gateway_id) . '">' . wp_kses($label, array('abbr' => array('class' => array(), 'title' => array()))) . '</label>';
    echo '<input type="file" id="ccd_proof_file_' . esc_attr($gateway_id) . '" class="ccd-proof-input" accept="image/jpeg,image/png,image/webp,application/pdf">';
    echo '<input type="hidden" name="ccd_payment_proof_' . esc_attr($gateway_id) . '" class="ccd-proof-token" value="' . esc_attr($token) . '">';
    echo '<small class="ccd-proof-status">' . ($name ? esc_html__('Uploaded:', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . ' ' . esc_html($name) : esc_html(sprintf(
        /* translators: %s: maximum file size */
        __('JPG, PNG, WebP or PDF, max %s.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        size_format(ccd_proof_max_bytes())
    ))) . '</small>';
    echo '</div>';
}

add_action('init', 'ccd_schedule_proof_cleanup');
/**
 * Schedule the daily cleanup of unattached uploads.
 */
function ccd_schedule_proof_cleanup()
{
    if (!wp_next_scheduled('ccd_proof_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'ccd_proof_cleanup');
    }
}

add_action('ccd_proof_cleanup', 'ccd_proof_cleanup');
/**
 * Delete uploads older than 24 hours that were never attached to an order.
 */
function ccd_proof_cleanup()
{
    $uploads = wp_upload_dir(null, false);
    $dir     = trailingslashit($uploads['basedir']) . 'ccd-payment-proofs/tmp/';
    if (!is_dir($dir)) {
        return;
    }
    foreach ((array) glob($dir . '*') as $file) {
        if ($file && is_file($file) && preg_match('/\.(jpg|png|webp|pdf)$/', $file) && filemtime($file) < time() - DAY_IN_SECONDS) {
            wp_delete_file($file);
        }
    }
}

//#################################
// UPLOAD AFTER ORDERING (thank you page, My Account > View order)
//#################################

/** Maximum uploads per order after checkout. */
define('CCD_PROOF_MAX_ORDER_UPLOADS', 5);

/**
 * Whether the customer may still add or replace the payment screenshot.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function ccd_order_accepts_proof($order)
{
    if (!is_a($order, 'WC_Order')) {
        return false;
    }
    $config = ccd_gateway_config();
    $method = $order->get_payment_method();
    if (!isset($config[$method]) || 'off' === ccd_proof_mode($method)) {
        return false;
    }
    $statuses = (array) apply_filters('ccd_proof_upload_statuses', array('pending', 'on-hold', 'advance-received'), $order);
    return in_array($order->get_status(), $statuses, true)
        && (int) $order->get_meta('_ccd_proof_order_uploads') < CCD_PROOF_MAX_ORDER_UPLOADS;
}

/**
 * Whether the current visitor may upload for this order: the logged-in
 * customer who owns it, or anyone holding the order key (thank you page link).
 *
 * @param WC_Order $order     Order.
 * @param string   $order_key Order key from the request.
 * @return bool
 */
function ccd_can_upload_for_order($order, $order_key)
{
    if (!is_a($order, 'WC_Order')) {
        return false;
    }
    if ($order->get_customer_id() && get_current_user_id() === (int) $order->get_customer_id()) {
        return true;
    }
    return '' !== (string) $order_key && hash_equals((string) $order->get_order_key(), (string) $order_key);
}

add_action('woocommerce_thankyou', 'ccd_render_order_proof_form', 20);
add_action('woocommerce_view_order', 'ccd_render_order_proof_form', 20);
/**
 * "Upload your payment screenshot" form below the order details.
 *
 * @param int $order_id Order ID.
 */
function ccd_render_order_proof_form($order_id)
{
    $order = wc_get_order($order_id);
    if (!ccd_order_accepts_proof($order)) {
        return;
    }

    $has  = (bool) $order->get_meta('_ccd_payment_proof');
    $done = isset($_GET['ccd_proof']) ? sanitize_key(wp_unslash($_GET['ccd_proof'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
    $msg  = isset($_GET['ccd_proof_msg']) ? sanitize_text_field(wp_unslash($_GET['ccd_proof_msg'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

    echo '<section class="ccd-order-proof">';
    echo '<h2>' . esc_html__('Payment screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</h2>';
    if ('uploaded' === $done) {
        echo '<p class="ccd-order-proof-ok">' . esc_html__('Thank you! Your payment screenshot was received.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</p>';
    } elseif ('error' === $done && '' !== $msg) {
        echo '<p class="ccd-order-proof-error">' . esc_html($msg) . '</p>';
    }
    echo '<p>' . esc_html(
        $has
            ? __('We have your payment screenshot. You can upload a new one to replace it.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')
            : __('Paid already? Upload a screenshot or PDF of your payment so we can confirm your order faster.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')
    ) . '</p>';
    echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="ccd_order_proof">';
    echo '<input type="hidden" name="order_id" value="' . esc_attr($order->get_id()) . '">';
    echo '<input type="hidden" name="order_key" value="' . esc_attr($order->get_order_key()) . '">';
    wp_nonce_field('ccd_order_proof_' . $order->get_id(), 'ccd_order_proof_nonce');
    echo '<input type="file" name="ccd_proof_file" accept="image/jpeg,image/png,image/webp,application/pdf" required> ';
    echo '<button type="submit" class="button">' . esc_html($has ? __('Replace screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') : __('Upload screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')) . '</button>';
    /* translators: %s: maximum file size */
    echo '<br><small>' . esc_html(sprintf(__('JPG, PNG, WebP or PDF, max %s.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), size_format(ccd_proof_max_bytes()))) . '</small>';
    echo '</form>';
    echo '</section>';
}

add_action('admin_post_ccd_order_proof', 'ccd_handle_order_proof');
add_action('admin_post_nopriv_ccd_order_proof', 'ccd_handle_order_proof');
/**
 * Handle the after-order upload form.
 */
function ccd_handle_order_proof()
{
    $order_id  = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order_key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';
    $order     = $order_id ? wc_get_order($order_id) : false;

    if (!$order || !isset($_POST['ccd_order_proof_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['ccd_order_proof_nonce'])), 'ccd_order_proof_' . $order_id) || !ccd_can_upload_for_order($order, $order_key)) {
        wp_die(esc_html__('This upload link is not valid. Please open your order again and retry.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), '', array('response' => 403, 'back_link' => true));
    }

    $back = wp_get_referer() ? wp_get_referer() : $order->get_checkout_order_received_url();
    $back = remove_query_arg(array('ccd_proof', 'ccd_proof_msg'), $back);

    if (!ccd_order_accepts_proof($order)) {
        wp_safe_redirect(add_query_arg(array('ccd_proof' => 'error', 'ccd_proof_msg' => rawurlencode(__('This order can no longer receive uploads. Please contact us.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'))), $back));
        exit;
    }

    $stored = ccd_proof_store_upload('ccd_proof_file', 'orders/', $order->get_id() . '-');
    if (is_wp_error($stored)) {
        wp_safe_redirect(add_query_arg(array('ccd_proof' => 'error', 'ccd_proof_msg' => rawurlencode($stored->get_error_message())), $back));
        exit;
    }

    $dir      = ccd_proof_dir();
    $previous = $order->get_meta('_ccd_payment_proof');
    if ($previous && 0 === strpos($previous, 'orders/') && $dir) {
        wp_delete_file($dir . $previous);
    }

    $order->update_meta_data('_ccd_payment_proof', $stored['file']);
    $order->update_meta_data('_ccd_payment_proof_name', $stored['name']);
    $order->update_meta_data('_ccd_payment_proof_type', $stored['type']);
    $order->update_meta_data('_ccd_proof_order_uploads', (int) $order->get_meta('_ccd_proof_order_uploads') + 1);
    $order->save();
    $order->add_order_note($previous
        ? __('Customer replaced the payment screenshot after checkout.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')
        : __('Customer uploaded a payment screenshot after checkout.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'));

    do_action('ccd_payment_proof_uploaded', $order);

    wp_safe_redirect(add_query_arg('ccd_proof', 'uploaded', $back));
    exit;
}
