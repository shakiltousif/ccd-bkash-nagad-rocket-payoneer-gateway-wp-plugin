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
    return sprintf(__('File is too large. Maximum size is %s.', 'ccd-payment-gateway-domain'), size_format(ccd_proof_max_bytes()));
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
            'uploading' => __('Uploading...', 'ccd-payment-gateway-domain'),
            'uploaded'  => __('Uploaded:', 'ccd-payment-gateway-domain'),
            'tooBig'    => ccd_proof_too_big_message(),
            /* translators: %s: maximum file size */
            'hint'      => sprintf(__('JPG, PNG, WebP or PDF, max %s.', 'ccd-payment-gateway-domain'), size_format(ccd_proof_max_bytes())),
            'failed'    => __('Upload failed. Please try again.', 'ccd-payment-gateway-domain'),
        ),
    );
}

add_action('wc_ajax_ccd_upload_proof', 'ccd_ajax_upload_proof');
/**
 * Handle a payment proof upload from the checkout page.
 */
function ccd_ajax_upload_proof()
{
    if (!check_ajax_referer('ccd-proof', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Your session has expired. Please reload the page and try again.', 'ccd-payment-gateway-domain')), 403);
    }

    $gateway_id = isset($_POST['gateway']) ? sanitize_key(wp_unslash($_POST['gateway'])) : '';
    $config     = ccd_gateway_config();
    if (!isset($config[$gateway_id]) || 'off' === ccd_proof_mode($gateway_id)) {
        wp_send_json_error(array('message' => __('File upload is not enabled for this payment method.', 'ccd-payment-gateway-domain')), 400);
    }

    if (!WC()->session || !WC()->cart || WC()->cart->is_empty()) {
        wp_send_json_error(array('message' => __('Your cart is empty.', 'ccd-payment-gateway-domain')), 400);
    }

    $files = ccd_proof_session_files();
    $count = (int) WC()->session->get('ccd_proof_upload_count');
    if ($count >= CCD_PROOF_MAX_UPLOADS) {
        wp_send_json_error(array('message' => __('Too many uploads. Please contact us if you need help.', 'ccd-payment-gateway-domain')), 429);
    }

    if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'], $_FILES['file']['name'], $_FILES['file']['error'], $_FILES['file']['size'])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        wp_send_json_error(array('message' => __('No file received.', 'ccd-payment-gateway-domain')), 400);
    }

    $file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
    if (in_array((int) $file['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
        wp_send_json_error(array('message' => ccd_proof_too_big_message()), 400);
    }
    if (UPLOAD_ERR_OK !== (int) $file['error'] || !is_uploaded_file($file['tmp_name'])) {
        wp_send_json_error(array('message' => __('Upload failed. Please try again.', 'ccd-payment-gateway-domain')), 400);
    }
    if ((int) $file['size'] <= 0 || (int) $file['size'] > ccd_proof_max_bytes()) {
        wp_send_json_error(array('message' => ccd_proof_too_big_message()), 400);
    }

    $name  = sanitize_file_name(wp_unslash($file['name']));
    $check = wp_check_filetype_and_ext($file['tmp_name'], $name, ccd_proof_mimes());
    if (empty($check['ext']) || empty($check['type'])) {
        wp_send_json_error(array('message' => __('Please upload a JPG, PNG, WebP or PDF file.', 'ccd-payment-gateway-domain')), 400);
    }

    // Content check: real image, or a real PDF.
    if ('application/pdf' === $check['type']) {
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ('%PDF-' !== $head) {
            wp_send_json_error(array('message' => __('This PDF file appears to be damaged.', 'ccd-payment-gateway-domain')), 400);
        }
    } elseif (false === @getimagesize($file['tmp_name'])) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        wp_send_json_error(array('message' => __('This image file appears to be damaged.', 'ccd-payment-gateway-domain')), 400);
    }

    $dir = ccd_proof_dir();
    if (!$dir) {
        wp_send_json_error(array('message' => __('Upload failed. Please try again.', 'ccd-payment-gateway-domain')), 500);
    }

    $ext      = 'jpeg' === $check['ext'] ? 'jpg' : $check['ext'];
    $relative = 'tmp/' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $dir . $relative)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        wp_send_json_error(array('message' => __('Upload failed. Please try again.', 'ccd-payment-gateway-domain')), 500);
    }
    @chmod($dir . $relative, 0640); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

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
            ? array(__('Please upload a screenshot or PDF of your payment.', 'ccd-payment-gateway-domain'))
            : array();
    }

    $files = ccd_proof_session_files();
    if (!isset($files[$token]) || $files[$token]['gateway'] !== $gateway_id) {
        return array(__('Your payment screenshot upload has expired. Please upload it again.', 'ccd-payment-gateway-domain'));
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
    $order->add_order_note(__('Customer uploaded a payment screenshot.', 'ccd-payment-gateway-domain'));

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
        wp_die(esc_html__('Sorry, you are not allowed to view this file.', 'ccd-payment-gateway-domain'), '', array('response' => 403));
    }

    $order    = wc_get_order($order_id);
    $relative = $order ? (string) $order->get_meta('_ccd_payment_proof') : '';
    $dir      = ccd_proof_dir();
    $path     = $dir ? realpath($dir . $relative) : false;
    $base     = $dir ? realpath($dir . 'orders') : false;

    if (!$relative || !$path || !$base || 0 !== strpos($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
        wp_die(esc_html__('File not found.', 'ccd-payment-gateway-domain'), '', array('response' => 404));
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
        ? __('Payment screenshot or PDF', 'ccd-payment-gateway-domain') . ' <abbr class="required" title="' . esc_attr__('required', 'ccd-payment-gateway-domain') . '">*</abbr>'
        : __('Payment screenshot or PDF (optional)', 'ccd-payment-gateway-domain');

    echo '<div class="ccd-proof-field" data-gateway="' . esc_attr($gateway_id) . '">';
    echo '<label for="ccd_proof_file_' . esc_attr($gateway_id) . '">' . wp_kses($label, array('abbr' => array('class' => array(), 'title' => array()))) . '</label>';
    echo '<input type="file" id="ccd_proof_file_' . esc_attr($gateway_id) . '" class="ccd-proof-input" accept="image/jpeg,image/png,image/webp,application/pdf">';
    echo '<input type="hidden" name="ccd_payment_proof_' . esc_attr($gateway_id) . '" class="ccd-proof-token" value="' . esc_attr($token) . '">';
    echo '<small class="ccd-proof-status">' . ($name ? esc_html__('Uploaded:', 'ccd-payment-gateway-domain') . ' ' . esc_html($name) : esc_html(sprintf(
        /* translators: %s: maximum file size */
        __('JPG, PNG, WebP or PDF, max %s.', 'ccd-payment-gateway-domain'),
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
