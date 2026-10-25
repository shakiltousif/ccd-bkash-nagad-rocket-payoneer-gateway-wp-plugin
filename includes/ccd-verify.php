<?php

/**
 * Faster payment verification:
 * - payment details + "Payment received / Not received" buttons in the admin "New order" email,
 *   via signed, expiring, single-use links that open a confirmation page (GET never changes
 *   anything, so email link scanners cannot approve orders);
 * - admin email when a customer uploads a payment screenshot after ordering;
 * - "Needs verification" filter and a payment screenshot column in the Orders list.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** How long email verification links stay valid. */
define('CCD_VERIFY_LINK_TTL', 7 * DAY_IN_SECONDS);

/**
 * Order statuses that still wait for payment verification.
 *
 * @return string[] Without the "wc-" prefix.
 */
function ccd_verify_statuses()
{
    return (array) apply_filters('ccd_verify_statuses', array('on-hold', 'pending', 'advance-received'));
}

/**
 * Whether an order paid with a CodeCareBD gateway still waits for verification.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function ccd_order_needs_verification($order)
{
    $config = ccd_gateway_config();
    return is_a($order, 'WC_Order')
        && isset($config[$order->get_payment_method()])
        && in_array($order->get_status(), ccd_verify_statuses(), true)
        && !$order->get_meta('_ccd_verify_done');
}

/**
 * Site secret for signing verification links (created on first use, not autoloaded).
 *
 * @return string
 */
function ccd_verify_secret()
{
    $secret = get_option('ccd_verify_secret');
    if (!is_string($secret) || strlen($secret) < 32) {
        $secret = wp_generate_password(64, true, true);
        update_option('ccd_verify_secret', $secret, false);
    }
    return $secret;
}

/**
 * Signature for a verification link. The order key is part of the payload.
 *
 * @param WC_Order $order    Order.
 * @param string   $decision approve|reject.
 * @param int      $expires  Unix time.
 * @return string
 */
function ccd_verify_signature($order, $decision, $expires)
{
    return hash_hmac('sha256', implode('|', array($order->get_id(), $decision, (int) $expires, $order->get_order_key())), ccd_verify_secret());
}

/**
 * Signed verification link.
 *
 * @param WC_Order $order    Order.
 * @param string   $decision approve|reject.
 * @return string
 */
function ccd_verify_link($order, $decision)
{
    $expires = time() + CCD_VERIFY_LINK_TTL;
    return add_query_arg(array(
        'action' => 'ccd_verify',
        'order'  => $order->get_id(),
        'do'     => $decision,
        'exp'    => $expires,
        'sig'    => ccd_verify_signature($order, $decision, $expires),
    ), admin_url('admin-post.php'));
}

/**
 * Payment details of an order for emails and the confirmation page.
 *
 * @param WC_Order $order Order.
 * @return array[] label => value
 */
function ccd_order_payment_details($order)
{
    $config = ccd_gateway_config();
    $method = $order->get_payment_method();
    if (!isset($config[$method])) {
        return array();
    }
    $rows = array(
        __('Payment method', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') => $config[$method]['label'],
    );
    foreach ($config[$method]['fields'] as $field) {
        $value = (string) $order->get_meta($field['meta']);
        if ('' !== $value) {
            $rows[$field['label']] = $value;
        }
    }
    $paid_to = $order->get_meta('_ccd_paid_to') ? $order->get_meta('_ccd_paid_to') : $order->get_meta('_ccd_bank_paid_to');
    if ($paid_to) {
        $rows[__('Paid To', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')] = $paid_to;
    }
    $rows[__('Payment Screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')] = $order->get_meta('_ccd_payment_proof')
        ? __('Attached (view it on the order screen)', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')
        : __('Not uploaded', 'codecarebd-bkash-nagad-rocket-payoneer-gateway');
    $rows[__('Amount to receive', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')] = html_entity_decode(wp_strip_all_tags(wc_price($order->get_total(), array('currency' => $order->get_currency()))), ENT_QUOTES, 'UTF-8');
    return $rows;
}

/**
 * Email block: payment details and the two buttons.
 *
 * @param WC_Order $order      Order.
 * @param bool     $plain_text Plain text email.
 * @return string
 */
function ccd_verify_email_block($order, $plain_text)
{
    $details = ccd_order_payment_details($order);
    $buttons = ccd_order_needs_verification($order);

    if ($plain_text) {
        $out = "\n" . strtoupper(__('Payment to verify', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')) . "\n";
        foreach ($details as $label => $value) {
            $out .= $label . ': ' . $value . "\n";
        }
        if ($buttons) {
            $out .= __('Payment received:', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . ' ' . ccd_verify_link($order, 'approve') . "\n";
            $out .= __('Payment not received:', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . ' ' . ccd_verify_link($order, 'reject') . "\n";
        }
        return $out . "\n";
    }

    $html  = '<h2>' . esc_html__('Payment to verify', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</h2>';
    $html .= '<table cellspacing="0" cellpadding="6" border="1" style="border:1px solid #e5e5e5;border-collapse:collapse;width:100%;margin-bottom:16px;">';
    foreach ($details as $label => $value) {
        $html .= '<tr><th style="text-align:left;border:1px solid #e5e5e5;">' . esc_html($label) . '</th><td style="text-align:left;border:1px solid #e5e5e5;">' . esc_html($value) . '</td></tr>';
    }
    $html .= '</table>';
    if ($buttons) {
        $html .= '<p style="margin:0 0 8px;">' . esc_html__('After checking your account:', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</p>';
        $html .= '<p style="margin:0 0 24px;">';
        $html .= '<a href="' . esc_url(ccd_verify_link($order, 'approve')) . '" style="display:inline-block;padding:10px 18px;margin:0 8px 8px 0;background:#2e7d32;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">' . esc_html__('Payment received', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</a>';
        $html .= '<a href="' . esc_url(ccd_verify_link($order, 'reject')) . '" style="display:inline-block;padding:10px 18px;margin:0 8px 8px 0;background:#b32d2e;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">' . esc_html__('Not received', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</a>';
        $html .= '</p><p style="font-size:12px;color:#666;margin:0 0 16px;">' . esc_html__('Each button opens a confirmation page. Links work once and expire after 7 days.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</p>';
    }
    return $html;
}

add_filter('woocommerce_settings_api_form_fields_new_order', 'ccd_verify_new_order_email_setting');
/**
 * Setting on WooCommerce > Settings > Emails > New order.
 *
 * @param array $fields Email settings.
 * @return array
 */
function ccd_verify_new_order_email_setting($fields)
{
    $fields['ccd_verify_buttons'] = array(
        'title'       => __('CodeCareBD payment verification', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        'type'        => 'checkbox',
        'label'       => __('Add payment details and "Payment received / Not received" buttons for bKash, Nagad, Rocket, Upay, CellFin, Bangla QR, bank transfer and Payoneer orders', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        'description' => __('Buttons open a confirmation page; nothing changes until you confirm. Anyone with the email can use the links, so keep this email private.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        'default'     => 'yes',
    );
    return $fields;
}

add_action('woocommerce_email_after_order_table', 'ccd_verify_new_order_email', 20, 4);
/**
 * Payment details and buttons in the admin "New order" email.
 *
 * @param WC_Order $order         Order.
 * @param bool     $sent_to_admin Sent to admin.
 * @param bool     $plain_text    Plain text.
 * @param WC_Email $email         Email object.
 */
function ccd_verify_new_order_email($order, $sent_to_admin, $plain_text = false, $email = null)
{
    if (!$sent_to_admin || !is_a($email, 'WC_Email') || 'new_order' !== $email->id || !is_a($order, 'WC_Order')) {
        return;
    }
    $config = ccd_gateway_config();
    if (!isset($config[$order->get_payment_method()]) || 'yes' !== $email->get_option('ccd_verify_buttons', 'yes')) {
        return;
    }
    echo ccd_verify_email_block($order, $plain_text); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building.
}

add_action('ccd_payment_proof_uploaded', 'ccd_verify_proof_uploaded_email');
/**
 * Email the shop when a customer uploads a payment screenshot after ordering.
 *
 * @param WC_Order $order Order.
 */
function ccd_verify_proof_uploaded_email($order)
{
    if (!apply_filters('ccd_send_proof_uploaded_email', true, $order) || !function_exists('WC')) {
        return;
    }
    $mailer = WC()->mailer();
    $emails = $mailer->get_emails();
    $recipient = isset($emails['WC_Email_New_Order']) ? $emails['WC_Email_New_Order']->get_recipient() : get_option('admin_email');
    if (!$recipient) {
        return;
    }

    /* translators: %s: order number */
    $heading = sprintf(__('Payment screenshot received for order #%s', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), $order->get_order_number());
    $body    = '<p>' . esc_html(sprintf(
        /* translators: 1: customer name, 2: order number */
        __('%1$s uploaded a payment screenshot for order #%2$s.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        $order->get_formatted_billing_full_name() ? $order->get_formatted_billing_full_name() : __('The customer', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        $order->get_order_number()
    )) . '</p>';
    $body   .= ccd_verify_email_block($order, false);
    $body   .= '<p><a href="' . esc_url($order->get_edit_order_url()) . '">' . esc_html__('Open the order to view the screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</a></p>';

    /* translators: 1: site name, 2: order number */
    $subject = sprintf(__('[%1$s] Payment screenshot for order #%2$s', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), $order->get_order_number());
    $mailer->send($recipient, $subject, $mailer->wrap_message($heading, $body));
}

add_filter('woocommerce_valid_order_statuses_for_payment_complete', 'ccd_verify_payment_complete_statuses');
/**
 * Allow payment_complete() from "Advance Received" (manual verification of the advance).
 *
 * @param string[] $statuses Statuses.
 * @return string[]
 */
function ccd_verify_payment_complete_statuses($statuses)
{
    $statuses[] = 'advance-received';
    return array_unique($statuses);
}

/**
 * Validate a verification request; returns the order or a WP_Error.
 *
 * @param array $req Request values (order, do, exp, sig).
 * @return WC_Order|WP_Error
 */
function ccd_verify_check_request($req)
{
    $order_id = isset($req['order']) ? absint($req['order']) : 0;
    $decision = isset($req['do']) ? sanitize_key($req['do']) : '';
    $expires  = isset($req['exp']) ? absint($req['exp']) : 0;
    $sig      = isset($req['sig']) ? sanitize_text_field($req['sig']) : '';
    $order    = $order_id ? wc_get_order($order_id) : false;

    if (!$order || !in_array($decision, array('approve', 'reject'), true) || !$sig
        || !hash_equals(ccd_verify_signature($order, $decision, $expires), $sig)) {
        return new WP_Error('invalid', __('This link is not valid.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'));
    }
    if ($expires < time()) {
        return new WP_Error('expired', __('This link has expired. Please verify the payment from the order screen.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'));
    }
    if ($order->get_meta('_ccd_verify_done') || false !== get_option('ccd_verified_' . $order->get_id(), false)) {
        return new WP_Error('used', __('This payment was already marked from an email link.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'));
    }
    if (!ccd_order_needs_verification($order)) {
        /* translators: %s: order status */
        return new WP_Error('status', sprintf(__('This order is already "%s", so it was not changed.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), wc_get_order_status_name($order->get_status())));
    }
    return $order;
}

/**
 * Minimal standalone page for the verification flow.
 *
 * @param string $title Title.
 * @param string $body  Body HTML (already escaped).
 * @param int    $code  HTTP status.
 */
function ccd_verify_page($title, $body, $code = 200)
{
    status_header($code);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    echo '<!DOCTYPE html><html lang="' . esc_attr(get_bloginfo('language')) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html($title) . '</title>';
    echo '<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f7f7;color:#1d2327;margin:0;padding:24px 16px}main{max-width:520px;margin:0 auto;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px}h1{font-size:20px;margin:0 0 16px}table{width:100%;border-collapse:collapse;margin:0 0 20px}th,td{text-align:left;padding:6px 8px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{width:45%;color:#50575e;font-weight:600}button{font-size:16px;padding:10px 18px;border:0;border-radius:4px;color:#fff;cursor:pointer}.ok{background:#2e7d32}.no{background:#b32d2e}.msg{font-size:15px;line-height:1.5}a{color:#2271b1}</style>';
    echo '</head><body><main><h1>' . esc_html($title) . '</h1>' . $body . '</main></body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $body escaped by callers.
    exit;
}

add_action('admin_post_ccd_verify', 'ccd_verify_handle');
add_action('admin_post_nopriv_ccd_verify', 'ccd_verify_handle');
/**
 * GET: show a confirmation page. POST: apply the decision.
 */
function ccd_verify_handle()
{
    $is_post = isset($_SERVER['REQUEST_METHOD']) && 'POST' === $_SERVER['REQUEST_METHOD'];
    $req     = $is_post ? wp_unslash($_POST) : wp_unslash($_GET); // phpcs:ignore WordPress.Security.NonceVerification -- authenticated by the HMAC signature.
    $order   = ccd_verify_check_request($req);
    $site    = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

    if (is_wp_error($order)) {
        $back = current_user_can('edit_shop_orders') && isset($req['order']) && ($o = wc_get_order(absint($req['order'])))
            ? '<p><a href="' . esc_url($o->get_edit_order_url()) . '">' . esc_html__('Open the order', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</a></p>'
            : '';
        ccd_verify_page($site, '<p class="msg">' . esc_html($order->get_error_message()) . '</p>' . $back, 'invalid' === $order->get_error_code() ? 403 : 200);
    }

    $decision = sanitize_key($req['do']);
    /* translators: %s: order number */
    $title = sprintf(__('Order #%s', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), $order->get_order_number());

    if (!$is_post) {
        $rows = '';
        foreach (ccd_order_payment_details($order) as $label => $value) {
            $rows .= '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        }
        $question = 'approve' === $decision
            ? __('Mark this payment as received? The order moves to processing.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')
            : __('Mark this payment as NOT received? The order is cancelled and stock is restored.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway');
        $form  = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        foreach (array('order' => $order->get_id(), 'do' => $decision, 'exp' => absint($req['exp']), 'sig' => sanitize_text_field($req['sig'])) as $k => $v) {
            $form .= '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
        }
        $form .= '<input type="hidden" name="action" value="ccd_verify">';
        $form .= '<button type="submit" class="' . ('approve' === $decision ? 'ok' : 'no') . '">' . esc_html('approve' === $decision ? __('Confirm: payment received', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') : __('Confirm: not received', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')) . '</button></form>';
        ccd_verify_page($title, '<table>' . $rows . '</table><p class="msg">' . esc_html($question) . '</p>' . $form);
    }

    // POST: claim the order atomically before acting, so two racing requests (double click, or
    // "received" and "not received" at once) cannot both act. add_option() is not atomic (it
    // checks, then INSERT ... ON DUPLICATE KEY UPDATE), so use INSERT IGNORE on the unique
    // option_name: exactly one request inserts the row. The row stays as the "used" marker.
    if (!ccd_verify_claim($order->get_id(), $decision)) {
        ccd_verify_page($site, '<p class="msg">' . esc_html__('This payment was already marked from an email link.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</p>');
    }
    $order->update_meta_data('_ccd_verify_done', $decision . '|' . time());
    $order->save();
    if ('approve' === $decision) {
        $order->add_order_note(__('Payment marked as received from the email verification link.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'));
        $order->payment_complete();
        $message = __('Done. The payment is marked as received.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway');
    } else {
        $order->update_status('cancelled', __('Payment marked as not received from the email verification link.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'));
        $message = __('Done. The order is cancelled.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway');
    }
    do_action('ccd_payment_verified', $order, $decision);

    $order = wc_get_order($order->get_id());
    /* translators: %s: order status */
    $status = sprintf(__('Order status: %s', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), wc_get_order_status_name($order->get_status()));
    ccd_verify_page($title, '<p class="msg">' . esc_html($message) . '</p><p class="msg">' . esc_html($status) . '</p><p><a href="' . esc_url($order->get_edit_order_url()) . '">' . esc_html__('Open the order', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</a></p>');
}

/**
 * Atomically claim an order for email verification.
 *
 * @param int    $order_id Order ID.
 * @param string $decision approve|reject.
 * @return bool True for the one request that wins the claim.
 */
function ccd_verify_claim($order_id, $decision)
{
    global $wpdb;
    $name     = 'ccd_verified_' . absint($order_id);
    $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $decision . '|' . time())); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    wp_cache_delete($name, 'options');
    wp_cache_delete('notoptions', 'options');
    return 1 === (int) $inserted;
}

add_action('woocommerce_delete_order', 'ccd_verify_forget_order');
/**
 * Remove the email-verification marker when an order is permanently deleted.
 *
 * @param int $order_id Order ID.
 */
function ccd_verify_forget_order($order_id)
{
    delete_option('ccd_verified_' . absint($order_id));
}

//#################################
// ORDERS LIST: "Needs verification" filter and screenshot column
//#################################

/**
 * Filter dropdown markup.
 */
function ccd_verify_filter_dropdown()
{
    $current = isset($_GET['ccd_verify']) ? sanitize_key(wp_unslash($_GET['ccd_verify'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
    echo '<select name="ccd_verify" id="ccd_verify_filter">';
    echo '<option value="">' . esc_html__('All payments', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</option>';
    echo '<option value="needs" ' . selected($current, 'needs', false) . '>' . esc_html__('Needs payment verification', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '</option>';
    echo '</select>';
}

/**
 * Whether the "Needs verification" filter is active.
 *
 * @return bool
 */
function ccd_verify_filter_active()
{
    return isset($_GET['ccd_verify']) && 'needs' === sanitize_key(wp_unslash($_GET['ccd_verify'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
}

add_action('woocommerce_order_list_table_restrict_manage_orders', 'ccd_verify_hpos_dropdown', 20, 2);
/**
 * HPOS orders list: dropdown.
 *
 * @param string $order_type Order type.
 * @param string $which      top|bottom.
 */
function ccd_verify_hpos_dropdown($order_type = 'shop_order', $which = 'top')
{
    if ('shop_order' === $order_type && 'top' === $which) {
        ccd_verify_filter_dropdown();
    }
}

add_filter('woocommerce_order_list_table_prepare_items_query_args', 'ccd_verify_hpos_query');
/**
 * HPOS orders list: apply the filter.
 *
 * @param array $args Query args.
 * @return array
 */
function ccd_verify_hpos_query($args)
{
    if (ccd_verify_filter_active()) {
        $args['payment_method'] = array_keys(ccd_gateway_config());
        $args['status']         = array_map(function ($s) {
            return 'wc-' . $s;
        }, ccd_verify_statuses());
    }
    return $args;
}

add_action('restrict_manage_posts', 'ccd_verify_legacy_dropdown', 20);
/**
 * Legacy orders list: dropdown.
 *
 * @param string $post_type Post type.
 */
function ccd_verify_legacy_dropdown($post_type = '')
{
    if ('shop_order' === $post_type) {
        ccd_verify_filter_dropdown();
    }
}

add_filter('request', 'ccd_verify_legacy_query');
/**
 * Legacy orders list: apply the filter.
 *
 * @param array $vars Query vars.
 * @return array
 */
function ccd_verify_legacy_query($vars)
{
    global $typenow;
    if (is_admin() && 'shop_order' === $typenow && ccd_verify_filter_active()) {
        $vars['post_status'] = array_map(function ($s) {
            return 'wc-' . $s;
        }, ccd_verify_statuses());
        $vars['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            array(
                'key'     => '_payment_method',
                'value'   => array_keys(ccd_gateway_config()),
                'compare' => 'IN',
            ),
        );
    }
    return $vars;
}

/**
 * Screenshot cell: thumbnail (images) or "PDF" link.
 *
 * @param WC_Order $order Order.
 */
function ccd_verify_render_proof_cell($order)
{
    if (!is_a($order, 'WC_Order') || !function_exists('ccd_payment_proof_admin_url')) {
        return;
    }
    $url = ccd_payment_proof_admin_url($order);
    if (!$url) {
        echo '<span aria-hidden="true">&ndash;</span>';
        return;
    }
    if ('application/pdf' === $order->get_meta('_ccd_payment_proof_type')) {
        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">PDF</a>';
        return;
    }
    echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" title="' . esc_attr__('View payment screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '"><img src="' . esc_url($url) . '" alt="' . esc_attr__('Payment screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') . '" loading="lazy" style="max-width:48px;max-height:48px;border:1px solid #dcdcde;border-radius:3px;"></a>';
}

add_filter('manage_woocommerce_page_wc-orders_columns', 'ccd_verify_add_proof_column', 20);
add_filter('manage_edit-shop_order_columns', 'ccd_verify_add_proof_column', 20);
/**
 * Add the screenshot column after "Transaction ID".
 *
 * @param array $columns Columns.
 * @return array
 */
function ccd_verify_add_proof_column($columns)
{
    $out = array();
    foreach ($columns as $key => $label) {
        $out[$key] = $label;
        if ('tran_id' === $key) {
            $out['ccd_proof'] = __('Screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway');
        }
    }
    if (!isset($out['ccd_proof'])) {
        $out['ccd_proof'] = __('Screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway');
    }
    return $out;
}

add_action('manage_woocommerce_page_wc-orders_custom_column', 'ccd_verify_hpos_proof_column', 20, 2);
/**
 * HPOS screenshot column.
 *
 * @param string   $column Column.
 * @param WC_Order $order  Order.
 */
function ccd_verify_hpos_proof_column($column, $order)
{
    if ('ccd_proof' === $column) {
        ccd_verify_render_proof_cell(is_a($order, 'WC_Order') ? $order : wc_get_order($order));
    }
}

add_action('manage_shop_order_posts_custom_column', 'ccd_verify_legacy_proof_column', 20, 2);
/**
 * Legacy screenshot column.
 *
 * @param string $column  Column.
 * @param int    $post_id Post ID.
 */
function ccd_verify_legacy_proof_column($column, $post_id = 0)
{
    if ('ccd_proof' === $column) {
        ccd_verify_render_proof_cell(wc_get_order($post_id ? $post_id : get_the_ID()));
    }
}
