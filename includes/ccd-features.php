<?php

/**
 * Shared features used by all CodeCareBD gateways:
 * - per-gateway configuration map
 * - duplicate Transaction ID protection
 * - configurable send money charge (percentage or fixed)
 * - receiving account box with copy button and QR code
 * - review request admin notice
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Per-gateway configuration. Option keys are kept exactly as released in
 * earlier versions so existing settings keep working.
 *
 * @return array
 */
function ccd_gateway_config()
{
    return array(
        'ccd_bkash' => array(
            'label'          => 'bKash',
            'number_key'     => 'bkash_number',
            'type_key'       => 'bkash_account_type',
            'charge_key'     => 'bkash_charge',
            'charge_rate'    => 1.85,
            'trx_field'      => 'bkash_transaction_id',
        ),
        'ccd_nagad' => array(
            'label'          => 'Nagad',
            'number_key'     => 'ccd_nagad_number',
            'type_key'       => 'ccd_nagad_account_type',
            'charge_key'     => 'ccd_nagad_charge',
            'charge_rate'    => 1.45,
            'trx_field'      => 'ccd_nagad_transaction_id',
        ),
        'ccd_rocket' => array(
            'label'          => 'Rocket',
            'number_key'     => 'ccd_rocket_number',
            'type_key'       => 'ccd_rocket_account_type',
            'charge_key'     => 'ccd_rocket_charge',
            'charge_rate'    => 1.8,
            'trx_field'      => 'ccd_rocket_transaction_id',
        ),
        'ccd_payoneer' => array(
            'label'          => 'Payoneer',
            'number_key'     => 'ccd_payoneer_recipient_email',
            'type_key'       => '',
            'charge_key'     => '',
            'charge_rate'    => 0,
            'trx_field'      => 'ccd_payoneer_transaction_id',
        ),
    );
}

/**
 * Saved settings of a gateway.
 *
 * @param string $gateway_id Gateway id.
 * @return array
 */
function ccd_gateway_settings($gateway_id)
{
    $settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
    return is_array($settings) ? $settings : array();
}

/**
 * Insert form fields after a given key (appends when the key is missing).
 *
 * @param array  $fields Existing form fields.
 * @param string $after  Key to insert after.
 * @param array  $insert Fields to insert.
 * @return array
 */
function ccd_insert_form_fields($fields, $after, $insert)
{
    $position = array_search($after, array_keys($fields), true);
    if (false === $position) {
        return array_merge($fields, $insert);
    }
    return array_slice($fields, 0, $position + 1, true) + $insert + array_slice($fields, $position + 1, null, true);
}

/**
 * Add the v1.4 settings (charge type/amount, QR code) to a gateway's form fields.
 *
 * @param string $gateway_id Gateway id.
 * @param array  $fields     Existing form fields.
 * @return array
 */
function ccd_extend_form_fields($gateway_id, $fields)
{
    $config = ccd_gateway_config();
    if (!isset($config[$gateway_id])) {
        return $fields;
    }
    $config = $config[$gateway_id];

    if (!empty($config['number_key']) && 'ccd_payoneer' !== $gateway_id) {
        $fields = ccd_insert_form_fields($fields, $config['number_key'], array(
            $gateway_id . '_qr_code' => array(
                'title'       => __('QR Code Image', 'ccd-payment-gateway-domain'),
                'type'        => 'text',
                'class'       => 'ccd-qr-code-url',
                'description' => __('Optional. Upload your payment QR code to the Media Library and paste its URL here (or use the Select image button). It is shown on the checkout page so customers can scan to pay.', 'ccd-payment-gateway-domain'),
                'default'     => '',
                'desc_tip'    => false,
            ),
        ));
    }

    if (!empty($config['charge_key'])) {
        $fields = ccd_insert_form_fields($fields, $config['charge_key'], array(
            $gateway_id . '_charge_type' => array(
                'title'       => __('Charge Type', 'ccd-payment-gateway-domain'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'options'     => array(
                    'percent' => __('Percentage of order total', 'ccd-payment-gateway-domain'),
                    'fixed'   => __('Fixed amount', 'ccd-payment-gateway-domain'),
                ),
                'default'     => 'percent',
                'desc_tip'    => true,
                'description' => __('How the send money charge is calculated.', 'ccd-payment-gateway-domain'),
            ),
            $gateway_id . '_charge_amount' => array(
                'title'             => __('Charge Amount', 'ccd-payment-gateway-domain'),
                'type'              => 'number',
                'default'           => (string) $config['charge_rate'],
                'custom_attributes' => array('step' => '0.01', 'min' => '0'),
                'desc_tip'          => true,
                'description'       => __('Percentage (e.g. 1.85) or fixed amount in your store currency, depending on Charge Type.', 'ccd-payment-gateway-domain'),
            ),
        ));

        $fields[$config['charge_key']]['label']       = sprintf(
            /* translators: %s: payment method label */
            __('Add %s "Send Money" charge to the order total', 'ccd-payment-gateway-domain'),
            $config['label']
        );
        $fields[$config['charge_key']]['description'] = __('Set the charge type and amount below.', 'ccd-payment-gateway-domain');
    }

    return $fields;
}

//#################################
// SEND MONEY CHARGE
//#################################

/**
 * Charge settings of a gateway, or null when no charge applies.
 *
 * @param string $gateway_id Gateway id.
 * @return array{type:string,amount:float}|null
 */
function ccd_get_charge_settings($gateway_id)
{
    $config = ccd_gateway_config();
    if (empty($config[$gateway_id]['charge_key'])) {
        return null;
    }

    $settings = ccd_gateway_settings($gateway_id);
    $key      = $config[$gateway_id]['charge_key'];
    if (!isset($settings[$key]) || 'yes' !== $settings[$key]) {
        return null;
    }

    $type   = isset($settings[$gateway_id . '_charge_type']) && 'fixed' === $settings[$gateway_id . '_charge_type'] ? 'fixed' : 'percent';
    $amount = isset($settings[$gateway_id . '_charge_amount']) && '' !== $settings[$gateway_id . '_charge_amount']
        ? (float) $settings[$gateway_id . '_charge_amount']
        : (float) $config[$gateway_id]['charge_rate'];

    if ($amount <= 0) {
        return null;
    }

    return array('type' => $type, 'amount' => $amount);
}

/**
 * Whether any gateway has a send money charge enabled.
 *
 * @return bool
 */
function ccd_any_charge_enabled()
{
    foreach (array_keys(ccd_gateway_config()) as $gateway_id) {
        if (ccd_get_charge_settings($gateway_id)) {
            return true;
        }
    }
    return false;
}

/**
 * Customer-facing charge note, e.g. 'Note: 1.85% bKash "Send Money" charge will be added to the order total.'
 *
 * @param string $gateway_id Gateway id.
 * @return string Plain text, empty when no charge applies.
 */
function ccd_get_charge_note($gateway_id)
{
    $charge = ccd_get_charge_settings($gateway_id);
    if (!$charge) {
        return '';
    }
    $config = ccd_gateway_config();
    $amount = 'fixed' === $charge['type']
        ? html_entity_decode(wp_strip_all_tags(wc_price($charge['amount'])), ENT_QUOTES, 'UTF-8')
        : wc_format_localized_decimal($charge['amount']) . '%';

    return sprintf(
        /* translators: 1: charge amount (e.g. 1.85% or ৳20), 2: payment method label */
        __('Note: %1$s %2$s "Send Money" charge will be added to the order total.', 'ccd-payment-gateway-domain'),
        $amount,
        $config[$gateway_id]['label']
    );
}

add_action('woocommerce_cart_calculate_fees', 'ccd_payment_charge_calculator');
/**
 * Add the send money charge for the chosen gateway to the cart.
 *
 * @param WC_Cart $cart Cart object.
 */
function ccd_payment_charge_calculator($cart)
{
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }
    if (!WC()->session) {
        return;
    }

    $gateway_id = WC()->session->get('chosen_payment_method');
    $charge     = $gateway_id ? ccd_get_charge_settings($gateway_id) : null;
    if (!$charge) {
        return;
    }

    $surcharge = 'fixed' === $charge['type']
        ? $charge['amount']
        : $cart->get_cart_contents_total() * $charge['amount'] / 100;
    $surcharge = round($surcharge, wc_get_price_decimals());

    if ($surcharge <= 0) {
        return;
    }

    $config = ccd_gateway_config();
    $cart->add_fee(
        /* translators: %s: payment method label */
        sprintf(__('%s Charge', 'ccd-payment-gateway-domain'), $config[$gateway_id]['label']),
        $surcharge,
        true,
        ''
    );
}

add_action('woocommerce_blocks_loaded', 'ccd_register_store_api_update_callback');
/**
 * Block checkout: let the client tell the cart which payment method is
 * selected, so the send money charge is recalculated immediately.
 */
function ccd_register_store_api_update_callback()
{
    if (!function_exists('woocommerce_store_api_register_update_callback')) {
        return;
    }
    woocommerce_store_api_register_update_callback(array(
        'namespace' => 'ccd-payment-gateway',
        'callback'  => function ($data) {
            $method = isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : '';
            if (WC()->session) {
                WC()->session->set('chosen_payment_method', $method);
            }
        },
    ));
}

//#################################
// DUPLICATE TRANSACTION ID PROTECTION
//#################################

/**
 * Find an existing order that already uses this transaction ID.
 *
 * Failed, cancelled and draft orders are ignored so a customer can retry
 * after a failed attempt.
 *
 * @param string $gateway_id       Gateway id.
 * @param string $transaction_id   Submitted transaction ID.
 * @param int    $exclude_order_id Order being placed (ignored in the search).
 * @return int Order ID, or 0 when the transaction ID is unused.
 */
function ccd_find_order_by_transaction_id($gateway_id, $transaction_id, $exclude_order_id = 0)
{
    $map    = ccd_payment_field_map();
    $config = ccd_gateway_config();
    $transaction_id = trim((string) $transaction_id);

    if ('' === $transaction_id || !isset($map[$gateway_id], $config[$gateway_id])) {
        return 0;
    }

    $trx_field = $config[$gateway_id]['trx_field'];
    if (!isset($map[$gateway_id][$trx_field])) {
        return 0;
    }

    $statuses   = array_diff(array_keys(wc_get_order_statuses()), array('wc-failed', 'wc-cancelled'));
    $meta_query = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        array(
            'key'   => $map[$gateway_id][$trx_field],
            'value' => $transaction_id,
        ),
    );
    $exclude = $exclude_order_id ? array((int) $exclude_order_id) : array();

    $hpos = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

    if ($hpos) {
        $orders = wc_get_orders(array(
            'limit'      => 1,
            'return'     => 'ids',
            'type'       => 'shop_order',
            'status'     => $statuses,
            'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'exclude'    => $exclude,
        ));
    } else {
        // The legacy posts data store ignores meta_query in wc_get_orders().
        $orders = get_posts(array(
            'post_type'      => 'shop_order',
            'post_status'    => $statuses,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'post__not_in'   => $exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
            'no_found_rows'  => true,
        ));
    }

    return !empty($orders) ? (int) $orders[0] : 0;
}

/**
 * Validation errors for a reused transaction ID.
 *
 * Filter `ccd_block_duplicate_transaction_id` (bool) to turn the check off.
 *
 * @param string $gateway_id       Gateway id.
 * @param array  $data             Submitted field values.
 * @param int    $exclude_order_id Order being placed.
 * @return string[]
 */
function ccd_duplicate_transaction_errors($gateway_id, $data, $exclude_order_id = 0)
{
    if (!apply_filters('ccd_block_duplicate_transaction_id', true, $gateway_id)) {
        return array();
    }

    $config = ccd_gateway_config();
    if (!isset($config[$gateway_id])) {
        return array();
    }

    $trx_field = $config[$gateway_id]['trx_field'];
    $trx       = isset($data[$trx_field]) ? sanitize_text_field($data[$trx_field]) : '';

    if (ccd_find_order_by_transaction_id($gateway_id, $trx, $exclude_order_id)) {
        return array(
            sprintf(
                /* translators: %s: payment method label */
                __('This %s transaction ID has already been used for another order. Please check the transaction ID or contact us.', 'ccd-payment-gateway-domain'),
                $config[$gateway_id]['label']
            ),
        );
    }

    return array();
}

//#################################
// RECEIVING ACCOUNT BOX (COPY BUTTON + QR CODE)
//#################################

/**
 * Receiving account details shown to the customer.
 *
 * @param string $gateway_id Gateway id.
 * @return array{text:string,value:string,qr:string}
 */
function ccd_get_account_display($gateway_id)
{
    $config   = ccd_gateway_config();
    $settings = ccd_gateway_settings($gateway_id);
    $result   = array('text' => '', 'value' => '', 'qr' => '');

    if (!isset($config[$gateway_id])) {
        return $result;
    }
    $config = $config[$gateway_id];

    $value = !empty($settings[$config['number_key']]) ? trim($settings[$config['number_key']]) : '';
    if ('' !== $value) {
        if ('ccd_payoneer' === $gateway_id) {
            $result['text'] = __('Payoneer Recipient Email :', 'ccd-payment-gateway-domain');
        } else {
            $type = (!empty($config['type_key']) && !empty($settings[$config['type_key']])) ? $settings[$config['type_key']] : '';
            $result['text'] = trim(sprintf('%s %s Number :', $config['label'], $type));
        }
        $result['value'] = $value;
    }

    if (!empty($settings[$gateway_id . '_qr_code'])) {
        $result['qr'] = esc_url_raw($settings[$gateway_id . '_qr_code']);
    }

    return $result;
}

/**
 * Classic checkout: receiving account line with copy button, plus QR code.
 *
 * @param string $gateway_id Gateway id.
 */
function ccd_render_account_box($gateway_id)
{
    $account = ccd_get_account_display($gateway_id);

    if ('' !== $account['value']) {
        echo '<p class="ccd-account-info">' . esc_html($account['text']) . ' <strong class="ccd-account-value">' . esc_html($account['value']) . '</strong> ';
        echo '<button type="button" class="ccd-copy-btn" data-ccd-copy="' . esc_attr($account['value']) . '" data-ccd-copied="' . esc_attr__('Copied!', 'ccd-payment-gateway-domain') . '">' . esc_html__('Copy', 'ccd-payment-gateway-domain') . '</button></p>';
    }

    if ('' !== $account['qr']) {
        echo '<p class="ccd-qr-code"><img src="' . esc_url($account['qr']) . '" alt="' . esc_attr__('Scan to pay', 'ccd-payment-gateway-domain') . '" loading="lazy"><br><small>' . esc_html__('Scan this QR code with your app to pay', 'ccd-payment-gateway-domain') . '</small></p>';
    }
}

/**
 * Classic checkout: send money charge note.
 *
 * @param string $gateway_id Gateway id.
 */
function ccd_render_charge_note($gateway_id)
{
    $note = ccd_get_charge_note($gateway_id);
    if ('' !== $note) {
        echo '<div class="ccd_extra_charge_note">' . wpautop(esc_html($note)) . '</div>';
    }
}

add_action('admin_enqueue_scripts', 'ccd_admin_enqueue_media_picker');
/**
 * Media picker for the QR code field on the gateway settings screens.
 *
 * @param string $hook Current admin page.
 */
function ccd_admin_enqueue_media_picker($hook)
{
    if ('woocommerce_page_wc-settings' !== $hook || !isset($_GET['section']) || 0 !== strpos(sanitize_key(wp_unslash($_GET['section'])), 'ccd_')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script('ccd-admin-qr', CCD_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/js/ccd-admin.js', array('jquery'), CCD_PAYMENT_GATEWAY_VERSION, true);
    wp_localize_script('ccd-admin-qr', 'ccdAdmin', array(
        'select' => __('Select image', 'ccd-payment-gateway-domain'),
        'title'  => __('Choose QR code image', 'ccd-payment-gateway-domain'),
    ));
}

//#################################
// REVIEW REQUEST NOTICE
//#################################

/**
 * Number of orders paid with CodeCareBD gateways (cached for a day, max 10 counted).
 *
 * @return int
 */
function ccd_count_gateway_orders()
{
    $count = get_transient('ccd_gateway_order_count');
    if (false === $count) {
        $orders = wc_get_orders(array(
            'limit'          => 10,
            'return'         => 'ids',
            'type'           => 'shop_order',
            'payment_method' => array_keys(ccd_gateway_config()),
        ));
        $count = count($orders);
        set_transient('ccd_gateway_order_count', $count, DAY_IN_SECONDS);
    }
    return (int) $count;
}

add_action('admin_notices', 'ccd_review_notice');
/**
 * Ask for a WordPress.org review once the store has received 10 orders.
 */
function ccd_review_notice()
{
    if (!current_user_can('manage_woocommerce') || !function_exists('wc_get_orders')) {
        return;
    }

    $state = get_option('ccd_review_notice', array());
    $state = is_array($state) ? $state : array();
    if (!empty($state['done']) || (!empty($state['snooze_until']) && time() < (int) $state['snooze_until'])) {
        return;
    }

    if (ccd_count_gateway_orders() < 10) {
        return;
    }

    $review_url = 'https://wordpress.org/support/plugin/codecarebd-bkash-nagad-rocket-payoneer-gateway/reviews/#new-post';
    $action_url = function ($action) {
        return wp_nonce_url(admin_url('admin-post.php?action=ccd_review_notice&ccd_do=' . $action), 'ccd_review_notice');
    };

    echo '<div class="notice notice-info"><p>';
    esc_html_e('You have received 10+ orders with CodeCareBD Payment Gateway. If it helps your store, could you leave a quick review on WordPress.org? It helps other store owners find the plugin.', 'ccd-payment-gateway-domain');
    echo '</p><p>';
    echo '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url($review_url) . '" onclick="window.location.href=\'' . esc_js($action_url('done')) . '\'">' . esc_html__('Leave a review', 'ccd-payment-gateway-domain') . '</a> ';
    echo '<a class="button" href="' . esc_url($action_url('later')) . '">' . esc_html__('Maybe later', 'ccd-payment-gateway-domain') . '</a> ';
    echo '<a href="' . esc_url($action_url('done')) . '">' . esc_html__('I already did', 'ccd-payment-gateway-domain') . '</a>';
    echo '</p></div>';
}

add_action('admin_post_ccd_review_notice', 'ccd_handle_review_notice');
/**
 * Handle the review notice buttons.
 */
function ccd_handle_review_notice()
{
    check_admin_referer('ccd_review_notice');
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Sorry, you are not allowed to do that.', 'ccd-payment-gateway-domain'));
    }

    $do    = isset($_GET['ccd_do']) ? sanitize_key(wp_unslash($_GET['ccd_do'])) : '';
    $state = 'done' === $do ? array('done' => 1) : array('snooze_until' => time() + 30 * DAY_IN_SECONDS);
    update_option('ccd_review_notice', $state, false);

    wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
    exit;
}
