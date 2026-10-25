<?php

/**
 * Plugin Name: CodeCareBD - bKash, Nagad, Rocket, Payoneer Gateway
 * Plugin URI: https://wordpress.org/plugins/codecarebd-bkash-nagad-rocket-payoneer-gateway
 * Description: Manual payment gateways for WooCommerce: bKash, Nagad, Rocket, Upay, CellFin, Bangla QR, bank transfer and Payoneer. Block checkout and HPOS ready.
 * Author: Shakil Ahamed
 * Version: 1.5.0
 * Requires at least: 6.3
 * Requires PHP: 7.3
 * Tested up to: 7.1
 * Requires Plugins: woocommerce
 * WC requires at least: 6.3
 * WC tested up to: 11.2
 * Author URI: https://shakilahamed.com
 * Text Domain: ccd-payment-gateway-domain
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Define plugin constants
define('CCD_PAYMENT_GATEWAY_VERSION', '1.5.0');
define('CCD_PAYMENT_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CCD_PAYMENT_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, function () {
    if (! class_exists('\Shakvaro\WP\Insights\Insights', false)) {
        require_once __DIR__ . '/vendor/shakvaro-wp-insights/src/Insights.php';
    }
    \Shakvaro\WP\Insights\Insights::mark_activated('codecarebd-bkash-nagad-rocket-payoneer-gateway');
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('ccd_proof_cleanup');
});

//include plugin modules
include_once(ABSPATH . 'wp-admin/includes/plugin.php');

/**
 * Declare WooCommerce feature compatibility.
 * - custom_order_tables (HPOS): plugin uses order CRUD for all order meta.
 * - cart_checkout_blocks: block-based checkout integration is provided.
 */
add_action('before_woocommerce_init', 'ccd_declare_wc_feature_compatibility');
function ccd_declare_wc_feature_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

/**
 * Register block-based (Cart & Checkout Blocks) payment method integrations.
 */
add_action('woocommerce_blocks_payment_method_type_registration', 'ccd_register_block_payment_methods');
function ccd_register_block_payment_methods($payment_method_registry)
{
    require_once __DIR__ . '/includes/blocks/class-ccd-blocks-support.php';

    foreach (array_keys(ccd_gateway_config()) as $ccd_gateway_id) {
        $payment_method_registry->register(new CCD_Blocks_Support($ccd_gateway_id));
    }
}

//checking if elementor active
if (is_plugin_active('woocommerce/woocommerce.php')) {

    add_filter('woocommerce_payment_gateways', 'ccd_rocket_payment_options');

    add_action('plugins_loaded', 'ccd_payment_options_init');

    add_action('woocommerce_checkout_update_order_meta', 'ccd_bkash_additional_fields_update_function');

    add_action('woocommerce_admin_order_data_after_billing_address', 'ccd_bkash_admin_order_data_function');

    add_action('woocommerce_order_details_after_customer_details', 'ccd_additional_info_order_review_fields_function');

    add_filter('woocommerce_account_orders_columns', 'ccd_admin_new_column_function');

    add_action('woocommerce_my_account_my_orders_column_account_no', 'ccd_account_orders_account_no_column');

    add_action('woocommerce_my_account_my_orders_column_tran_id', 'ccd_account_orders_tran_id_column');

    add_filter('manage_woocommerce_page_wc-orders_columns', 'ccd_admin_new_column_function_new_method');

    add_filter('manage_edit-shop_order_columns', 'ccd_admin_new_column_function_new_method');

    add_action('manage_shop_order_posts_custom_column', 'ccd_admin_column_value_function', 2);

    add_action('manage_woocommerce_page_wc-orders_custom_column', 'ccd_admin_column_value_function_new_method', 10, 2);


    add_action('wp_enqueue_scripts', 'ccd_checkout_page_enqueue_script');

    // Register custom order status for advance payment
    add_action('init', 'ccd_register_advance_received_order_status');
    
    // Add custom order status to WooCommerce order statuses
    add_filter('woocommerce_register_shop_order_post_statuses', 'ccd_add_advance_received_to_order_statuses');
    
    // Add custom order status to order status list
    add_filter('wc_order_statuses', 'ccd_add_advance_received_order_status');
    
    // Add settings page for advance payment
    add_action('admin_menu', 'ccd_add_advance_payment_settings_page');
    add_action('admin_init', 'ccd_register_advance_payment_settings');


    // Shared features: charge, duplicate transaction ID check, account box, review notice.
    require_once __DIR__ . '/includes/ccd-features.php';
    require_once __DIR__ . '/includes/ccd-payment-proof.php';
} else {

    //notice if Woocommerce isn't installed properly

    add_action('admin_notices', function () {

        $inactive_plugins = '';
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            $inactive_plugins .= "Woocommerce";
        }

        echo '<div class="error notice is-dismissible"><p>' . esc_attr($inactive_plugins) . ' Isn\'t installed or activated yet, Please install ' . esc_attr($inactive_plugins) . ' plugin and activate it to use this awesome addon ( CodeCareBD - Bkash, Nagad, Rocket, Payoneer Gateway )</p></div>'; // phpcs:ignore WordPress.Security.
    });

    /**
     * Deactivate Plugin
     */
    function ccd_payment_gateway_deactivate()
    {
        deactivate_plugins(plugin_basename(__FILE__));
        unset($_GET['activate']);
    }
    add_action('admin_init', 'ccd_payment_gateway_deactivate');
}

//load payment gateways
function ccd_rocket_payment_options($load_gateways)
{
    $load_gateways[] = 'CCD_Payment_Bkash';
    $load_gateways[] = 'CCD_Payment_Nagad';
    $load_gateways[] = 'CCD_Payment_Rocket';
    $load_gateways[] = 'CCD_Payment_Payoneer';
    $load_gateways[] = 'CCD_Payment_Upay';
    $load_gateways[] = 'CCD_Payment_Cellfin';
    $load_gateways[] = 'CCD_Payment_Bank';
    $load_gateways[] = 'CCD_Payment_BanglaQR';
    return $load_gateways;
}



//Payment Options Init 
function ccd_payment_options_init()
{
    require_once(__DIR__ . '/includes/classes/CCD_Payment_Bkash.php');
    require_once(__DIR__ . '/includes/classes/CCD_Payment_Nagad.php');
    require_once(__DIR__ . '/includes/classes/CCD_Payment_Rocket.php');
    require_once(__DIR__ . '/includes/classes/CCD_Payment_Payoneer.php');
    require_once(__DIR__ . '/includes/classes/class-ccd-gateway-base.php');
}




/**
 * Field map: gateway id => [ POST field name => order meta key ].
 *
 * Single source of truth used by the classic checkout, the block (Store API)
 * checkout, validation and admin readers. The POST field names and meta keys
 * are kept exactly as released in earlier versions for backward compatibility.
 */
function ccd_payment_field_map()
{
    $map = array();
    foreach (ccd_gateway_config() as $gateway_id => $config) {
        foreach ($config['fields'] as $field) {
            $map[$gateway_id][$field['name']] = $field['meta'];
        }
    }
    return $map;
}

/**
 * Save the submitted payment fields to the order using CRUD (HPOS-safe).
 *
 * @param WC_Order $order  Order object.
 * @param string   $method Gateway id.
 * @param array    $data   Associative array of submitted field values.
 */
function ccd_save_payment_fields($order, $method, $data)
{
    $config = ccd_gateway_config();

    if (!is_a($order, 'WC_Order') || !isset($config[$method])) {
        return;
    }

    foreach ($config[$method]['fields'] as $field) {
        if (!isset($data[$field['name']])) {
            continue;
        }
        $order->update_meta_data($field['meta'], ccd_normalize_field($field['rule'], $data[$field['name']]));
    }

    if ('ccd_bank' === $method) {
        $paid_to = ccd_bank_paid_to_label($data);
        if ('' !== $paid_to) {
            $order->update_meta_data('_ccd_bank_paid_to', $paid_to);
        }
    }

    $order->save();
}

/**
 * Validate submitted payment fields. Returns an array of error messages
 * (empty when valid). Shared by classic and block checkout.
 *
 * @param string $method Gateway id.
 * @param array  $data   Associative array of submitted field values.
 * @param int    $exclude_order_id Order being placed (ignored by the duplicate transaction ID check).
 * @return string[]
 */
function ccd_validate_payment_fields($method, $data, $exclude_order_id = 0)
{
    $config = ccd_gateway_config();
    $errors = array();

    if (!isset($config[$method])) {
        return $errors;
    }

    foreach ($config[$method]['fields'] as $field) {
        $raw   = isset($data[$field['name']]) ? (string) $data[$field['name']] : '';
        $value = ccd_normalize_field($field['rule'], $raw);

        // "Missing" only when nothing was typed; a value that normalises to '' (e.g. a bad email) is invalid.
        if ('' === trim($raw)) {
            $errors[] = ccd_field_error($field, $config[$method]['label'], true);
        } elseif ('' === $value) {
            $errors[] = ccd_field_error($field, $config[$method]['label'], false);
        } elseif (!ccd_is_valid_field($field['rule'], $value)) {
            $errors[] = ccd_field_error($field, $config[$method]['label'], false);
        }
    }

    if (empty($errors) && 'ccd_bank' === $method) {
        $errors = ccd_bank_paid_to_errors($data);
    }

    if (empty($errors) && function_exists('ccd_payment_proof_errors')) {
        $errors = ccd_payment_proof_errors($method, isset($data['ccd_payment_proof']) ? $data['ccd_payment_proof'] : '');
    }

    if (empty($errors) && function_exists('ccd_duplicate_transaction_errors')) {
        $errors = ccd_duplicate_transaction_errors($method, $data, $exclude_order_id);
    }

    return $errors;
}

/**
 * Classic checkout: save payment fields to the order (HPOS-safe CRUD).
 */
function ccd_bkash_additional_fields_update_function($order_id)
{
    if (!isset($_POST['payment_method'])) {
        return;
    }

    $method = sanitize_text_field(wp_unslash($_POST['payment_method']));
    $map    = ccd_payment_field_map();

    if (!isset($map[$method])) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Nonce is verified by WooCommerce core before this checkout hook fires.
    $data = array();
    foreach (array_keys($map[$method]) as $field) {
        if (isset($_POST[$field])) {
            $data[$field] = sanitize_text_field(wp_unslash($_POST[$field])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
    }

    if (isset($_POST['ccd_bank_paid_to'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $data['ccd_bank_paid_to'] = sanitize_text_field(wp_unslash($_POST['ccd_bank_paid_to'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }

    ccd_save_payment_fields($order, $method, $data);

    $proof_key = 'ccd_payment_proof_' . $method;
    if (!empty($_POST[$proof_key]) && function_exists('ccd_attach_payment_proof')) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        ccd_attach_payment_proof($order, $method, sanitize_text_field(wp_unslash($_POST[$proof_key]))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }
}

/**
 * Block (Store API) checkout: validate then save payment fields.
 *
 * The classic `woocommerce_checkout_process` and
 * `woocommerce_checkout_update_order_meta` hooks do NOT fire for the block
 * checkout, so the Store API request hook handles both validation and saving.
 */
add_action('woocommerce_store_api_checkout_update_order_from_request', 'ccd_blocks_update_order_from_request', 10, 2);
function ccd_blocks_update_order_from_request($order, $request)
{
    $method = is_callable(array($order, 'get_payment_method')) ? $order->get_payment_method() : '';
    $map    = ccd_payment_field_map();

    if (!isset($map[$method])) {
        return;
    }

    // Collect the submitted payment data (array of {key, value}).
    $payment_data = array();
    $raw          = isset($request['payment_data']) ? $request['payment_data'] : array();
    if (is_array($raw)) {
        foreach ($raw as $item) {
            if (isset($item['key'])) {
                $payment_data[$item['key']] = isset($item['value']) ? sanitize_text_field($item['value']) : '';
            }
        }
    }

    // Validate; throw to abort the order with a customer-visible message.
    $errors = ccd_validate_payment_fields($method, $payment_data, $order->get_id());
    if (!empty($errors)) {
        throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
            'ccd_payment_validation_error',
            esc_html(implode(' ', $errors)),
            400
        );
    }

    ccd_save_payment_fields($order, $method, $payment_data);

    if (!empty($payment_data['ccd_payment_proof']) && function_exists('ccd_attach_payment_proof')) {
        ccd_attach_payment_proof($order, $method, $payment_data['ccd_payment_proof']);
    }
}


/**
 * Admin order page bKash data output
 */
function ccd_bkash_admin_order_data_function($order)
{
    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order);
    }
    if (!$order) {
        return;
    }

    $method = $order->get_payment_method();
    $config = ccd_gateway_config();

    if (!isset($config[$method])) {
        return;
    }

    $fields      = $config[$method]['fields'];
    $account     = sanitize_text_field($order->get_meta($fields[0]['meta']));
    $transaction = sanitize_text_field($order->get_meta($fields[1]['meta']));
    $img_url     = plugins_url($config[$method]['icon'], __FILE__);

    // Check if advance payment is enabled and used
    $is_advance_payment = $order->get_meta('_ccd_advance_payment_received');
    $original_total = $order->get_meta('_ccd_original_order_total');
    $advance_amount = $order->get_meta('_ccd_advance_amount_paid');
    $remaining_amount = $order->get_meta('_ccd_remaining_amount');
    $proof_url = function_exists('ccd_payment_proof_admin_url') ? ccd_payment_proof_admin_url($order) : '';
?>
    <div class="form-field form-field-wide">
        <img src='<?php echo esc_attr($img_url); ?>' width="150" style="max-height:60px;width:auto;" />
        <table class="wp-list-table widefat fixed striped posts">
            <tbody>
                <tr>
                    <th><strong><?php echo esc_html($fields[0]['label']); ?></strong></th>
                    <td>: <?php echo esc_html($account); ?></td>
                </tr>
                <tr>
                    <th><strong><?php echo esc_html($fields[1]['label']); ?></strong></th>
                    <td>: <?php echo esc_html($transaction); ?></td>
                </tr>
                <?php if ($order->get_meta('_ccd_bank_paid_to')) : ?>
                <tr>
                    <th><strong><?php esc_html_e('Paid Into', 'ccd-payment-gateway-domain'); ?></strong></th>
                    <td>: <?php echo esc_html($order->get_meta('_ccd_bank_paid_to')); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($proof_url) : ?>
                <tr>
                    <th><strong><?php esc_html_e('Payment Screenshot', 'ccd-payment-gateway-domain'); ?></strong></th>
                    <td>: <a href="<?php echo esc_url($proof_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('View file', 'ccd-payment-gateway-domain'); ?></a></td>
                </tr>
                <?php endif; ?>
                <?php if ($is_advance_payment === 'yes' && $original_total) : ?>
                <tr>
                    <th colspan="2"><strong style="color: #2271b1;"><?php esc_html_e('Advance Payment Information', 'ccd-payment-gateway-domain'); ?></strong></th>
                </tr>
                <tr>
                    <th><strong><?php esc_html_e('Original Order Total', 'ccd-payment-gateway-domain'); ?></strong></th>
                    <td>: <?php echo wc_price($original_total); ?></td>
                </tr>
                <tr>
                    <th><strong><?php esc_html_e('Advance Amount Paid', 'ccd-payment-gateway-domain'); ?></strong></th>
                    <td>: <?php echo wc_price($advance_amount); ?> <span style="color: green;">✓</span></td>
                </tr>
                <tr>
                    <th><strong><?php esc_html_e('Remaining Amount', 'ccd-payment-gateway-domain'); ?></strong></th>
                    <td>: <?php echo wc_price($remaining_amount); ?> <span style="color: orange;">⚠</span></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php

}




/**
 * Order review page bKash data output
 */
function ccd_additional_info_order_review_fields_function($order)
{
    if (!is_a($order, 'WC_Order')) {
        return;
    }

    $method = $order->get_payment_method();
    $config = ccd_gateway_config();

    if (!isset($config[$method])) {
        return;
    }

    $fields      = $config[$method]['fields'];
    $account     = sanitize_text_field($order->get_meta($fields[0]['meta']));
    $transaction = sanitize_text_field($order->get_meta($fields[1]['meta']));

    // Check if advance payment is enabled and used
    $is_advance_payment = $order->get_meta('_ccd_advance_payment_received');
    $original_total = $order->get_meta('_ccd_original_order_total');
    $advance_amount = $order->get_meta('_ccd_advance_amount_paid');
    $remaining_amount = $order->get_meta('_ccd_remaining_amount');
?>
    <table>
        <tr>
            <th><?php echo esc_html($fields[0]['label']); ?>:</th>
            <td><?php echo esc_html($account); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html($fields[1]['label']); ?>:</th>
            <td><?php echo esc_html($transaction); ?></td>
        </tr>
        <?php if ($order->get_meta('_ccd_payment_proof')) : ?>
        <tr>
            <th><?php esc_html_e('Payment Screenshot:', 'ccd-payment-gateway-domain'); ?></th>
            <td><?php esc_html_e('Received', 'ccd-payment-gateway-domain'); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($is_advance_payment === 'yes' && $original_total) : ?>
        <tr>
            <th colspan="2"><strong style="color: #2271b1;"><?php esc_html_e('Advance Payment Information', 'ccd-payment-gateway-domain'); ?></strong></th>
        </tr>
        <tr>
            <th><?php esc_html_e('Original Order Total', 'ccd-payment-gateway-domain'); ?></th>
            <td><?php echo wc_price($original_total); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Advance Amount Paid', 'ccd-payment-gateway-domain'); ?></th>
            <td><?php echo wc_price($advance_amount); ?> <span style="color: green;">✓</span></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Remaining Amount', 'ccd-payment-gateway-domain'); ?></th>
            <td><?php echo wc_price($remaining_amount); ?> <span style="color: orange;">⚠</span></td>
        </tr>
        <?php endif; ?>
    </table>
<?php

}

/**
 * Register new admin column
 */
function ccd_admin_new_column_function($columns)
{

    $new_columns = (is_array($columns)) ? $columns : array();

    // WooCommerce uses 'order-actions' (hyphen) as the key; keep it as the last column.
    $actions = isset($new_columns['order-actions']) ? $new_columns['order-actions'] : null;
    unset($new_columns['order-actions']);

    $new_columns['account_no']     = esc_html__('Account No.', 'ccd-payment-gateway-domain');
    $new_columns['tran_id']     = esc_html__('Transaction ID', 'ccd-payment-gateway-domain');

    if (null !== $actions) {
        $new_columns['order-actions'] = $actions;
    }
    return $new_columns;
}


//new method
function ccd_admin_new_column_function_new_method($columns)
{
    $reordered_columns = array();

    // Inserting columns to a specific location
    foreach ($columns as $key => $column) {
        $reordered_columns[$key] = $column;

        if ($key ===  'order_status') {
            // Inserting after "Status" column
            $reordered_columns['account_no'] = esc_html__('Account No.', 'ccd-payment-gateway-domain');
            $reordered_columns['tran_id'] = esc_html__('Transaction ID', 'ccd-payment-gateway-domain');
        }
    }
    return $reordered_columns;
}

/**
 * Load data in new column
 */
/**
 * Resolve [account_no, tran_id] display strings for an order using CRUD meta.
 *
 * @param WC_Order $order Order object.
 * @return array{0:string,1:string}
 */
function ccd_get_order_column_values($order)
{
    if (!is_a($order, 'WC_Order')) {
        return array('', '');
    }

    $config = ccd_gateway_config();
    $method = $order->get_payment_method();

    // Prefer the order's own gateway; fall back to scanning (orders whose gateway was renamed).
    $candidates = isset($config[$method]) ? array($method => $config[$method]) : $config;

    foreach ($candidates as $gateway_id => $gateway) {
        $number = $order->get_meta($gateway['fields'][0]['meta']);
        if ('' !== $number) {
            return array(
                sanitize_text_field($number) . ' ( ' . $gateway['label'] . ' )',
                sanitize_text_field($order->get_meta($gateway['fields'][1]['meta'])),
            );
        }
    }

    return array('', '');
}

/**
 * Legacy posts-table order column output.
 */
function ccd_admin_column_value_function($column)
{
    global $post;

    if (!$post || ($column !== 'account_no' && $column !== 'tran_id')) {
        return;
    }

    $order = wc_get_order($post->ID);
    if (!$order) {
        return;
    }

    list($account, $tran_id) = ccd_get_order_column_values($order);

    if ($column === 'account_no') {
        echo esc_attr($account);
    } elseif ($column === 'tran_id') {
        echo esc_attr($tran_id);
    }
}


/**
 * My Account orders table column output.
 */
function ccd_account_orders_account_no_column($order)
{
    list($account) = ccd_get_order_column_values($order);
    echo esc_html($account);
}

function ccd_account_orders_tran_id_column($order)
{
    list(, $tran_id) = ccd_get_order_column_values($order);
    echo esc_html($tran_id);
}


// HPOS (wc-orders) order column output.
function ccd_admin_column_value_function_new_method($column, $order)
{
    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order);
    }
    if (!$order) {
        return;
    }

    list($account, $tran_id) = ccd_get_order_column_values($order);

    switch ($column) {
        case 'account_no':
            echo esc_attr($account);
            break;

        case 'tran_id':
            echo esc_attr($tran_id);
            break;
    }
}


// Enqueue script
function ccd_checkout_page_enqueue_script()
{
    if (is_checkout()) {

        // CSS
        wp_enqueue_style('ccd_checkout_page-css', plugin_dir_url(__FILE__) . 'assets/css/ccd-payment-gateway-checkout.css', array(), CCD_PAYMENT_GATEWAY_VERSION);

        //js
        wp_enqueue_script('ccd_checkout_page-script', plugins_url('assets/js/ccd_scripts.js', __FILE__), array('jquery'), CCD_PAYMENT_GATEWAY_VERSION, true);
        if (function_exists('ccd_proof_script_data')) {
            wp_localize_script('ccd_checkout_page-script', 'ccdProof', ccd_proof_script_data());
        }
    }
}




add_action('woocommerce_checkout_process', 'ccd_bkash_payment_validation');

function ccd_bkash_payment_validation()
{
    if (!isset($_POST['payment_method'])) {
        return;
    }

    $method = sanitize_text_field(wp_unslash($_POST['payment_method']));
    $map    = ccd_payment_field_map();

    if (!isset($map[$method])) {
        return;
    }

    // Nonce is verified by WooCommerce core before this checkout hook fires.
    $data = array();
    foreach (array_keys($map[$method]) as $field) {
        if (isset($_POST[$field])) {
            $data[$field] = sanitize_text_field(wp_unslash($_POST[$field])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
    }
    if (isset($_POST['ccd_bank_paid_to'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $data['ccd_bank_paid_to'] = sanitize_text_field(wp_unslash($_POST['ccd_bank_paid_to'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }
    // Each payment box has its own upload field, keyed by gateway id.
    if (isset($_POST['ccd_payment_proof_' . $method])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $data['ccd_payment_proof'] = sanitize_text_field(wp_unslash($_POST['ccd_payment_proof_' . $method])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }

    // A previous failed attempt may have left a pending order with this transaction ID.
    $awaiting = (WC()->session) ? absint(WC()->session->get('order_awaiting_payment')) : 0;

    foreach (ccd_validate_payment_fields($method, $data, $awaiting) as $error) {
        wc_add_notice($error, 'error');
    }
}



//#################################
// ACTION LINKS
//#################################


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ccd_payment_gateway_action_links');

function ccd_payment_gateway_action_links($actions)
{
    $actions[] = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=checkout')) . '">' . __("Settings", "ccd-payment-gateway-domain") . '</a>';
    $actions[] = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=ccd-advance-payment-settings')) . '">' . __("Advance Payment", "ccd-payment-gateway-domain") . '</a>';
    $actions[] = '<a href="https://codecarebd.com/contact" target="_blank">' . esc_html(__("Support", "ccd-payment-gateway-domain")) . '</a>';
    return $actions;
}

/**
 * Register custom order status "Advance Received"
 */
function ccd_register_advance_received_order_status()
{
    register_post_status('wc-advance-received', array(
        'label'                     => esc_html__('Advance Received', 'ccd-payment-gateway-domain'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Advance Received <span class="count">(%s)</span>', 'Advance Received <span class="count">(%s)</span>', 'ccd-payment-gateway-domain')
    ));
}

/**
 * Add custom order status to WooCommerce order statuses
 */
function ccd_add_advance_received_to_order_statuses($order_statuses)
{
    $order_statuses['wc-advance-received'] = array(
        'label'                     => esc_html__('Advance Received', 'ccd-payment-gateway-domain'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Advance Received <span class="count">(%s)</span>', 'Advance Received <span class="count">(%s)</span>', 'ccd-payment-gateway-domain')
    );
    return $order_statuses;
}

/**
 * Add custom order status to order status list
 */
function ccd_add_advance_received_order_status($order_statuses)
{
    $new_order_statuses = array();
    
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        
        // Insert after "on-hold" status
        if ('wc-on-hold' === $key) {
            $new_order_statuses['wc-advance-received'] = esc_html__('Advance Received', 'ccd-payment-gateway-domain');
        }
    }
    
    return $new_order_statuses;
}

/**
 * Add settings page for advance payment
 */
function ccd_add_advance_payment_settings_page()
{
    add_submenu_page(
        'woocommerce',
        esc_html__('Advance Payment Settings', 'ccd-payment-gateway-domain'),
        esc_html__('Advance Payment', 'ccd-payment-gateway-domain'),
        'manage_woocommerce',
        'ccd-advance-payment-settings',
        'ccd_advance_payment_settings_page'
    );
}

add_filter('woocommerce_screen_ids', 'ccd_advance_payment_screen_id');
/**
 * Load WooCommerce admin scripts (enhanced selects) on the Advance Payment page.
 *
 * @param array $ids Screen ids.
 * @return array
 */
function ccd_advance_payment_screen_id($ids)
{
    $ids[] = 'woocommerce_page_ccd-advance-payment-settings';
    return $ids;
}

/**
 * Register advance payment settings
 */
function ccd_register_advance_payment_settings()
{
    register_setting('ccd_advance_payment_settings', 'ccd_enable_advance_payment');
    register_setting('ccd_advance_payment_settings', 'ccd_advance_payment_status_label');
    register_setting('ccd_advance_payment_settings', 'ccd_advance_payment_title');
    register_setting('ccd_advance_payment_settings', 'ccd_advance_payment_text');
    register_setting('ccd_advance_payment_settings', 'ccd_remaining_payment_text');
}

/**
 * Display advance payment settings page
 */
function ccd_advance_payment_settings_page()
{
    if (isset($_POST['ccd_advance_payment_submit'])) {
        check_admin_referer('ccd_advance_payment_settings');
        
        $enable_advance_payment = isset($_POST['ccd_enable_advance_payment']) ? 'yes' : 'no';
        $status_label = isset($_POST['ccd_advance_payment_status_label']) ? sanitize_text_field($_POST['ccd_advance_payment_status_label']) : 'Advance Received';
        $advance_title = isset($_POST['ccd_advance_payment_title']) ? sanitize_text_field($_POST['ccd_advance_payment_title']) : '';
        $advance_text = isset($_POST['ccd_advance_payment_text']) ? sanitize_textarea_field($_POST['ccd_advance_payment_text']) : '';
        $remaining_text = isset($_POST['ccd_remaining_payment_text']) ? sanitize_textarea_field($_POST['ccd_remaining_payment_text']) : '';
        
        update_option('ccd_enable_advance_payment', $enable_advance_payment);
        update_option('ccd_advance_payment_status_label', $status_label);
        update_option('ccd_advance_payment_title', $advance_title);
        update_option('ccd_advance_payment_text', $advance_text);
        update_option('ccd_remaining_payment_text', $remaining_text);

        $mode = (isset($_POST['ccd_advance_payment_mode']) && 'zone' === $_POST['ccd_advance_payment_mode']) ? 'zone' : 'shipping';
        $inside_states = isset($_POST['ccd_advance_inside_states']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['ccd_advance_inside_states'])) : array();
        $valid_states  = array_keys((array) WC()->countries->get_states('BD'));
        $inside_states = array_values(array_intersect($inside_states, $valid_states));
        $inside_amount  = isset($_POST['ccd_advance_inside_amount']) ? max(0, (float) wc_format_decimal(wp_unslash($_POST['ccd_advance_inside_amount']))) : 0;
        $outside_amount = isset($_POST['ccd_advance_outside_amount']) ? max(0, (float) wc_format_decimal(wp_unslash($_POST['ccd_advance_outside_amount']))) : 0;
        $min_order      = isset($_POST['ccd_advance_min_order']) ? max(0, (float) wc_format_decimal(wp_unslash($_POST['ccd_advance_min_order']))) : 0;

        if ('zone' === $mode && empty($inside_states)) {
            $inside_states = array('BD-13');
            echo '<div class="notice notice-warning"><p>' . esc_html__('No "Inside Dhaka" area was selected, so Dhaka (BD-13) was used.', 'ccd-payment-gateway-domain') . '</p></div>';
        }
        if ('zone' === $mode && $inside_amount <= 0 && $outside_amount <= 0) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Both zone amounts are 0, so no advance payment will be asked. Enter the inside and outside Dhaka amounts.', 'ccd-payment-gateway-domain') . '</p></div>';
        }

        update_option('ccd_advance_payment_mode', $mode);
        update_option('ccd_advance_inside_states', $inside_states);
        update_option('ccd_advance_inside_amount', $inside_amount);
        update_option('ccd_advance_outside_amount', $outside_amount);
        update_option('ccd_advance_min_order', $min_order);

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'ccd-payment-gateway-domain') . '</p></div>';
    }
    
    $enable_advance_payment = get_option('ccd_enable_advance_payment', 'no');
    $status_label = get_option('ccd_advance_payment_status_label', 'Advance Received');
    $advance_title = get_option('ccd_advance_payment_title', '');
    $advance_text = get_option('ccd_advance_payment_text', '');
    $remaining_text = get_option('ccd_remaining_payment_text', '');
    $mode           = get_option('ccd_advance_payment_mode', 'shipping');
    $inside_states  = (array) get_option('ccd_advance_inside_states', array('BD-13'));
    $inside_amount  = get_option('ccd_advance_inside_amount', '');
    $outside_amount = get_option('ccd_advance_outside_amount', '');
    $min_order      = get_option('ccd_advance_min_order', '');
    $bd_states      = (array) WC()->countries->get_states('BD');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Advance Payment Settings', 'ccd-payment-gateway-domain'); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('ccd_advance_payment_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ccd_enable_advance_payment"><?php echo esc_html__('Enable Advance Payment', 'ccd-payment-gateway-domain'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="ccd_enable_advance_payment" name="ccd_enable_advance_payment" value="yes" <?php checked($enable_advance_payment, 'yes'); ?>>
                        <p class="description">
                            <?php echo esc_html__('When enabled, customers paying with these gateways pay only an advance now (the delivery charge, or a fixed amount for inside / outside Dhaka) and the rest later. The order is set to "Advance Received".', 'ccd-payment-gateway-domain'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ccd_advance_payment_mode"><?php echo esc_html__('Advance Amount', 'ccd-payment-gateway-domain'); ?></label>
                    </th>
                    <td>
                        <select id="ccd_advance_payment_mode" name="ccd_advance_payment_mode">
                            <option value="shipping" <?php selected($mode, 'shipping'); ?>><?php esc_html_e('Delivery (shipping) charge of the order', 'ccd-payment-gateway-domain'); ?></option>
                            <option value="zone" <?php selected($mode, 'zone'); ?>><?php esc_html_e('Fixed amount: inside Dhaka / outside Dhaka', 'ccd-payment-gateway-domain'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('"Delivery charge" uses the shipping cost calculated at checkout. "Fixed amount" asks a set advance depending on the delivery district.', 'ccd-payment-gateway-domain'); ?></p>
                    </td>
                </tr>
                <tr class="ccd-zone-row">
                    <th scope="row"><label for="ccd_advance_inside_states"><?php esc_html_e('Inside Dhaka Areas', 'ccd-payment-gateway-domain'); ?></label></th>
                    <td>
                        <select id="ccd_advance_inside_states" name="ccd_advance_inside_states[]" multiple="multiple" class="wc-enhanced-select" style="min-width:350px;">
                            <?php foreach ($bd_states as $code => $name) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected(in_array($code, $inside_states, true)); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Delivery districts charged the "inside Dhaka" amount. Default: Dhaka. Every other district (and other countries) use the outside amount.', 'ccd-payment-gateway-domain'); ?></p>
                    </td>
                </tr>
                <tr class="ccd-zone-row">
                    <th scope="row"><label for="ccd_advance_inside_amount"><?php esc_html_e('Inside Dhaka Amount', 'ccd-payment-gateway-domain'); ?></label></th>
                    <td><input type="number" step="0.01" min="0" id="ccd_advance_inside_amount" name="ccd_advance_inside_amount" value="<?php echo esc_attr($inside_amount); ?>" style="width:120px;"> <?php echo esc_html(get_woocommerce_currency_symbol()); ?></td>
                </tr>
                <tr class="ccd-zone-row">
                    <th scope="row"><label for="ccd_advance_outside_amount"><?php esc_html_e('Outside Dhaka Amount', 'ccd-payment-gateway-domain'); ?></label></th>
                    <td><input type="number" step="0.01" min="0" id="ccd_advance_outside_amount" name="ccd_advance_outside_amount" value="<?php echo esc_attr($outside_amount); ?>" style="width:120px;"> <?php echo esc_html(get_woocommerce_currency_symbol()); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ccd_advance_min_order"><?php esc_html_e('Minimum Order Total', 'ccd-payment-gateway-domain'); ?></label></th>
                    <td>
                        <input type="number" step="0.01" min="0" id="ccd_advance_min_order" name="ccd_advance_min_order" value="<?php echo esc_attr($min_order); ?>" style="width:120px;" placeholder="0"> <?php echo esc_html(get_woocommerce_currency_symbol()); ?>
                        <p class="description"><?php esc_html_e('Only ask for an advance when the order total is at least this amount. Leave empty or 0 to always ask.', 'ccd-payment-gateway-domain'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ccd_advance_payment_status_label"><?php echo esc_html__('Order Status Label', 'ccd-payment-gateway-domain'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ccd_advance_payment_status_label" name="ccd_advance_payment_status_label" value="<?php echo esc_attr($status_label); ?>" class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Customize the label for the advance payment order status.', 'ccd-payment-gateway-domain'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ccd_advance_payment_title"><?php echo esc_html__('Advance Payment Title', 'ccd-payment-gateway-domain'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ccd_advance_payment_title" name="ccd_advance_payment_title" value="<?php echo esc_attr($advance_title); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Advance Payment Information:', 'ccd-payment-gateway-domain'); ?>">
                        <p class="description">
                            <?php echo esc_html__('Customize the title shown in the advance payment notice box. Leave empty to use default.', 'ccd-payment-gateway-domain'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ccd_advance_payment_text"><?php echo esc_html__('Advance Payment Text', 'ccd-payment-gateway-domain'); ?></label>
                    </th>
                    <td>
                        <textarea id="ccd_advance_payment_text" name="ccd_advance_payment_text" rows="3" class="large-text" placeholder="<?php echo esc_attr__('You will pay only the delivery charge as advance payment:', 'ccd-payment-gateway-domain'); ?>"><?php echo esc_textarea($advance_text); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('Customize the text explaining the advance payment amount. Leave empty to use default. Use {amount} placeholder to show the advance amount dynamically.', 'ccd-payment-gateway-domain'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ccd_remaining_payment_text"><?php echo esc_html__('Remaining Payment Text', 'ccd-payment-gateway-domain'); ?></label>
                    </th>
                    <td>
                        <textarea id="ccd_remaining_payment_text" name="ccd_remaining_payment_text" rows="3" class="large-text" placeholder="<?php echo esc_attr__('Remaining amount to be paid later:', 'ccd-payment-gateway-domain'); ?>"><?php echo esc_textarea($remaining_text); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('Customize the text explaining the remaining payment amount. Leave empty to use default. Use {amount} placeholder to show the remaining amount dynamically.', 'ccd-payment-gateway-domain'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="ccd_advance_payment_submit" class="button button-primary" value="<?php echo esc_attr__('Save Settings', 'ccd-payment-gateway-domain'); ?>">
            </p>
        </form>
    </div>
    <script>
        jQuery(function ($) {
            var toggle = function () { $('.ccd-zone-row').toggle($('#ccd_advance_payment_mode').val() === 'zone'); };
            $('#ccd_advance_payment_mode').on('change', toggle);
            toggle();
        });
    </script>
    <?php
}

/**
 * Check if advance payment is enabled
 */
function ccd_is_advance_payment_enabled()
{
    return get_option('ccd_enable_advance_payment', 'no') === 'yes';
}

/**
 * Get advance payment amount (shipping charge)
 */
function ccd_get_advance_payment_amount($order)
{
    if (!$order) {
        return 0;
    }

    $shipping = floatval($order->get_shipping_total()) + floatval($order->get_shipping_tax());
    $country  = $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country();
    $state    = $order->get_shipping_country() ? $order->get_shipping_state() : $order->get_billing_state();

    return ccd_calculate_advance_amount($shipping, $country, $state, floatval($order->get_total()), $order->needs_shipping_address() || $shipping > 0);
}

/**
 * Advance amount for a cart or order.
 *
 * Mode "shipping" (default, as in earlier versions): the shipping charge.
 * Mode "zone": a fixed amount for inside / outside Dhaka (configurable areas).
 * Returns 0 when advance payment does not apply: disabled, order below the
 * minimum, nothing to ship, or the advance would cover the whole order.
 *
 * @param float  $shipping       Shipping total incl. tax.
 * @param string $country        Delivery country code.
 * @param string $state          Delivery state code (e.g. BD-13).
 * @param float  $order_total    Order / cart total.
 * @param bool   $needs_shipping Whether the order is delivered.
 * @return float
 */
function ccd_calculate_advance_amount($shipping, $country, $state, $order_total, $needs_shipping = true)
{
    if (!ccd_is_advance_payment_enabled() || !$needs_shipping) {
        return 0;
    }

    $minimum = (float) get_option('ccd_advance_min_order', 0);
    if ($minimum > 0 && $order_total < $minimum) {
        return 0;
    }

    if ('zone' === get_option('ccd_advance_payment_mode', 'shipping')) {
        $inside_states = (array) get_option('ccd_advance_inside_states', array('BD-13'));
        $inside        = ('BD' === $country && in_array($state, $inside_states, true));
        $amount        = (float) get_option($inside ? 'ccd_advance_inside_amount' : 'ccd_advance_outside_amount', 0);
    } else {
        $amount = (float) $shipping;
    }

    $amount = round(max(0, $amount), wc_get_price_decimals());

    // An advance that covers the whole order is just a full payment.
    if ($amount <= 0 || $amount >= $order_total) {
        return 0;
    }

    return (float) apply_filters('ccd_advance_payment_amount', $amount, $country, $state, $order_total);
}

/**
 * Apply advance payment to an order during process_payment().
 *
 * Keeps the behaviour of earlier versions: the order total becomes the
 * advance amount, the original total and remaining amount are stored as meta,
 * and the order moves to "Advance Received".
 *
 * @param WC_Order $order Order.
 * @param string   $label Gateway label for the order note.
 * @return bool Whether advance payment was applied.
 */
function ccd_apply_advance_payment($order, $label)
{
    $advance_amount = ccd_get_advance_payment_amount($order);
    if ($advance_amount <= 0) {
        return false;
    }

    $original_total   = $order->get_total();
    $remaining_amount = $original_total - $advance_amount;

    $order->update_meta_data('_ccd_advance_payment_received', 'yes');
    $order->update_meta_data('_ccd_original_order_total', $original_total);
    $order->update_meta_data('_ccd_advance_amount_paid', $advance_amount);
    $order->update_meta_data('_ccd_remaining_amount', $remaining_amount);
    $order->set_total($advance_amount);
    $order->save();

    $order->update_status(
        'advance-received',
        sprintf(
            /* translators: %s: payment method label */
            esc_html__('Advance payment received via %s. Remaining amount: ', 'ccd-payment-gateway-domain'),
            $label
        ) . wc_price($remaining_amount)
    );

    return true;
}

/**
 * Normal (non-advance) status update after checkout.
 *
 * @param WC_Order $order  Order.
 * @param string   $status Configured status (with or without "wc-" prefix).
 * @param string   $label  Gateway label for the order note.
 */
function ccd_apply_order_status($order, $status, $label)
{
    $status = (string) $status;
    if ('wc-' === substr($status, 0, 3)) {
        $status = substr($status, 3);
    }
    if ('' === $status) {
        $status = 'on-hold';
    }
    /* translators: %s: payment method label */
    $order->update_status($status, sprintf(esc_html__('Checkout with %s payment. ', 'ccd-payment-gateway-domain'), $label));
}

/**
 * Classic checkout: advance payment notice inside the payment box.
 */
function ccd_render_advance_notice()
{
    $display = ccd_get_advance_payment_display();
    if (!$display) {
        return;
    }

    echo '<div class="ccd_advance_payment_notice" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px;">';
    echo '<strong>' . esc_html($display['title']) . '</strong><br>';
    echo esc_html($display['advanceText']) . ' <strong>' . esc_html($display['advanceAmount']) . '</strong><br>';
    echo esc_html($display['remainingText']) . ' <strong>' . esc_html($display['remainingAmount']) . '</strong>';
    echo '</div>';
}

/**
 * Build the advance-payment notice strings for the current cart.
 *
 * Shared by the classic checkout fields and the block checkout integration so
 * both surfaces show identical wording. Returns null when advance payment is
 * not applicable (disabled, no cart, or no shipping charge).
 *
 * @return array{title:string,advanceText:string,remainingText:string,advanceAmount:string,remainingAmount:string}|null
 */
function ccd_get_advance_payment_display()
{
    if (!ccd_is_advance_payment_enabled()) {
        return null;
    }
    if (!function_exists('WC') || !WC()->cart) {
        return null;
    }

    $cart     = WC()->cart;
    $customer = WC()->customer;
    $shipping = floatval($cart->get_shipping_total()) + floatval($cart->get_shipping_tax());
    $country  = $customer ? $customer->get_shipping_country() : '';
    $state    = $customer ? $customer->get_shipping_state() : '';

    $cart_total     = floatval($cart->get_total('edit'));
    $advance_amount = ccd_calculate_advance_amount($shipping, $country, $state, $cart_total, $cart->needs_shipping());

    if ($advance_amount <= 0) {
        return null;
    }

    $remaining_amount = $cart_total - $advance_amount;

    $title = get_option('ccd_advance_payment_title', '');
    $title = !empty($title) ? $title : __('Advance Payment Information:', 'ccd-payment-gateway-domain');
    $title = apply_filters('ccd_advance_payment_title', $title);

    $advance_text = get_option('ccd_advance_payment_text', '');
    if (empty($advance_text)) {
        $advance_text = 'zone' === get_option('ccd_advance_payment_mode', 'shipping')
            ? __('Pay this advance now to confirm your order:', 'ccd-payment-gateway-domain')
            : __('You will pay only the delivery charge as advance payment:', 'ccd-payment-gateway-domain');
    }
    $advance_text = apply_filters('ccd_advance_payment_text', $advance_text);

    $remaining_text = get_option('ccd_remaining_payment_text', '');
    $remaining_text = !empty($remaining_text) ? $remaining_text : __('Remaining amount to be paid later:', 'ccd-payment-gateway-domain');
    $remaining_text = apply_filters('ccd_remaining_payment_text', $remaining_text);

    $advance_formatted   = html_entity_decode(wp_strip_all_tags(wc_price($advance_amount, array('currency' => get_woocommerce_currency()))), ENT_QUOTES, 'UTF-8');
    $remaining_formatted = html_entity_decode(wp_strip_all_tags(wc_price($remaining_amount, array('currency' => get_woocommerce_currency()))), ENT_QUOTES, 'UTF-8');

    // Support {amount} placeholders: when used, the amount is already in the text.
    $advance_has   = false !== strpos($advance_text, '{amount}');
    $remaining_has = false !== strpos($remaining_text, '{amount}');
    $advance_text   = str_replace('{amount}', $advance_formatted, $advance_text);
    $remaining_text = str_replace('{amount}', $remaining_formatted, $remaining_text);

    return array(
        'title'           => wp_strip_all_tags($title),
        'advanceText'     => wp_strip_all_tags($advance_text),
        'remainingText'   => wp_strip_all_tags($remaining_text),
        'advanceAmount'   => $advance_has ? '' : $advance_formatted,
        'remainingAmount' => $remaining_has ? '' : $remaining_formatted,
    );
}

/**
 * Shakvaro WP Insights — opt-in, privacy-first usage telemetry.
 * Disabled by default; nothing is sent unless the site admin opts in.
 * See readme "Privacy & Data Sharing" and https://shakvaro.com/wp-insights/privacy
 */
require_once __DIR__ . '/vendor/shakvaro-wp-insights/load.php';
add_action('shakvaro_wp_insights_loaded', function () {
    \Shakvaro\WP\Insights\Insights::register(array(
        'slug'           => 'codecarebd-bkash-nagad-rocket-payoneer-gateway',
        'name'           => 'CodeCareBD - Payment Gateway',
        'version'        => defined('CCD_PAYMENT_GATEWAY_VERSION') ? CCD_PAYMENT_GATEWAY_VERSION : '1.2',
        'plugin_file'    => __FILE__,
        'api_key'        => 'pk_codecarebd_bkash_nag_y44dKm9du3BifdGZ',
        'signing_secret' => 'aZp4Z11ignSgGT7XDsfJ0GDvXsfGST4IIHxR4IphgwvnKCBA',
        'endpoint'       => 'https://track.shakvaro.cloud',
    ))
    ->track_feature('gateway_bkash', function () { $s = get_option('woocommerce_ccd_bkash_settings'); return is_array($s) && (isset($s['enabled']) ? $s['enabled'] : '') === 'yes'; })
    ->track_feature('gateway_nagad', function () { $s = get_option('woocommerce_ccd_nagad_settings'); return is_array($s) && (isset($s['enabled']) ? $s['enabled'] : '') === 'yes'; })
    ->track_feature('gateway_rocket', function () { $s = get_option('woocommerce_ccd_rocket_settings'); return is_array($s) && (isset($s['enabled']) ? $s['enabled'] : '') === 'yes'; })
    ->track_feature('gateway_payoneer', function () { $s = get_option('woocommerce_ccd_payoneer_settings'); return is_array($s) && (isset($s['enabled']) ? $s['enabled'] : '') === 'yes'; })
    ->track_feature('gateway_upay', function () { $s = get_option('woocommerce_ccd_upay_settings'); return is_array($s) && (isset($s['enabled']) ? $s['enabled'] : '') === 'yes'; })
    ->track_feature('gateway_cellfin', function () { $s = get_option('woocommerce_ccd_cellfin_settings'); return is_array($s) && (isset($s['enabled']) ? $s['enabled'] : '') === 'yes'; })
    ->track_feature('gateway_bank', function () { $s = get_option('woocommerce_ccd_bank_settings'); return is_array($s) && (isset($s['enabled']) ? $s['enabled'] : '') === 'yes'; })
    ->track_feature('gateway_banglaqr', function () { $s = get_option('woocommerce_ccd_banglaqr_settings'); return is_array($s) && (isset($s['enabled']) ? $s['enabled'] : '') === 'yes'; })
    ->track_feature('advance_payment', function () { return get_option('ccd_enable_advance_payment', 'no') === 'yes'; })
    ->add_deactivation_survey();
});
