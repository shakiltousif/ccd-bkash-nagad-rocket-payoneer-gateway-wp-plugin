<?php

/**
 * Plugin Name: CodeCareBD - bKash, Nagad, Rocket, Payoneer Gateway
 * Plugin URI: https://wordpress.org/plugins/codecarebd-bkash-nagad-rocket-payoneer-gateway
 * Description: CodeCareBD - Bkash, Nagad, Rocket, Payoneer Gateway plugin is for WooCommerce. This plugin will help you to integrate Bkash, Nagad, Rocket, and Payoneer Payment Gateway.
 * Author: Shakil Ahamed
 * Version: 1.4.0
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
define('CCD_PAYMENT_GATEWAY_VERSION', '1.4.0');
define('CCD_PAYMENT_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CCD_PAYMENT_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, function () {
    if (! class_exists('\Shakvaro\WP\Insights\Insights', false)) {
        require_once __DIR__ . '/vendor/shakvaro-wp-insights/src/Insights.php';
    }
    \Shakvaro\WP\Insights\Insights::mark_activated('codecarebd-bkash-nagad-rocket-payoneer-gateway');
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

    foreach (array('ccd_bkash', 'ccd_nagad', 'ccd_rocket', 'ccd_payoneer') as $ccd_gateway_id) {
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
    return $load_gateways;
}



//Payment Options Init 
function ccd_payment_options_init()
{
    require_once(__DIR__ . '/includes/classes/CCD_Payment_Bkash.php');
    require_once(__DIR__ . '/includes/classes/CCD_Payment_Nagad.php');
    require_once(__DIR__ . '/includes/classes/CCD_Payment_Rocket.php');
    require_once(__DIR__ . '/includes/classes/CCD_Payment_Payoneer.php');
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
    return array(
        'ccd_bkash' => array(
            'bkash_number'             => '_bkash_number',
            'bkash_transaction_id'     => '_bkash_transaction',
        ),
        'ccd_nagad' => array(
            'ccd_nagad_number'         => '_ccd_nagad_number',
            'ccd_nagad_transaction_id' => '_ccd_nagad_transaction',
        ),
        'ccd_rocket' => array(
            'ccd_rocket_number'         => '_ccd_rocket_number',
            'ccd_rocket_transaction_id' => '_ccd_rocket_transaction',
        ),
        'ccd_payoneer' => array(
            'ccd_payoneeer_sender_email' => '_ccd_payoneeer_sender_email',
            'ccd_payoneer_transaction_id' => '_ccd_payoneer_transaction_id',
        ),
    );
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
    $map = ccd_payment_field_map();

    if (!is_a($order, 'WC_Order') || !isset($map[$method])) {
        return;
    }

    foreach ($map[$method] as $field => $meta_key) {
        if (!isset($data[$field])) {
            continue;
        }
        $value = ('ccd_payoneeer_sender_email' === $field)
            ? sanitize_email($data[$field])
            : sanitize_text_field($data[$field]);
        $order->update_meta_data($meta_key, $value);
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
    $errors = array();

    if ($method === 'ccd_bkash' || $method === 'ccd_nagad' || $method === 'ccd_rocket') {
        $labels = array(
            'ccd_bkash'  => array('bKash', 'bkash_number', 'bkash_transaction_id'),
            'ccd_nagad'  => array('Nagad', 'ccd_nagad_number', 'ccd_nagad_transaction_id'),
            'ccd_rocket' => array('Rocket', 'ccd_rocket_number', 'ccd_rocket_transaction_id'),
        );
        list($label, $number_field, $trx_field) = $labels[$method];

        $number = isset($data[$number_field]) ? sanitize_text_field($data[$number_field]) : '';
        $trx    = isset($data[$trx_field]) ? sanitize_text_field($data[$trx_field]) : '';

        if ($number === '') {
            /* translators: %s: payment method label */
            $errors[] = sprintf(__('Please enter your %s number.', 'ccd-payment-gateway-domain'), $label);
        } elseif (!preg_match('/^[0-9]{11}$/', $number)) {
            /* translators: %s: payment method label */
            $errors[] = sprintf(__('Please enter a valid %s phone number.', 'ccd-payment-gateway-domain'), $label);
        }

        if ($trx === '') {
            /* translators: %s: payment method label */
            $errors[] = sprintf(__('Please enter your %s transaction ID.', 'ccd-payment-gateway-domain'), $label);
        } elseif (strlen($trx) < 5) {
            /* translators: %s: payment method label */
            $errors[] = sprintf(__('Please enter a valid %s transaction ID.', 'ccd-payment-gateway-domain'), $label);
        }
    } elseif ($method === 'ccd_payoneer') {
        $email = isset($data['ccd_payoneeer_sender_email']) ? sanitize_email($data['ccd_payoneeer_sender_email']) : '';
        $trx   = isset($data['ccd_payoneer_transaction_id']) ? sanitize_text_field($data['ccd_payoneer_transaction_id']) : '';

        if ($email === '') {
            $errors[] = __('Please enter your Payoneer sender email.', 'ccd-payment-gateway-domain');
        } elseif (!is_email($email)) {
            $errors[] = __('Please enter a valid Payoneer sender email.', 'ccd-payment-gateway-domain');
        }

        if ($trx === '') {
            $errors[] = __('Please enter your Payoneer transaction ID.', 'ccd-payment-gateway-domain');
        } elseif (strlen($trx) < 5) {
            $errors[] = __('Please enter a valid Payoneer transaction ID.', 'ccd-payment-gateway-domain');
        }
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

    ccd_save_payment_fields($order, $method, $data);
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
                $payment_data[$item['key']] = isset($item['value']) ? $item['value'] : '';
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
    $map    = ccd_payment_field_map();

    if (!isset($map[$method])) {
        return;
    }

    $meta_keys   = array_values($map[$method]);
    $account     = sanitize_text_field($order->get_meta($meta_keys[0]));
    $transaction = sanitize_text_field($order->get_meta($meta_keys[1]));

    $titles  = array(
        'ccd_bkash'    => 'bKash No.',
        'ccd_nagad'    => 'Nagad No.',
        'ccd_rocket'   => 'Rocket No.',
        'ccd_payoneer' => 'Payoneer Email: ',
    );
    $images  = array(
        'ccd_bkash'    => 'assets/img/bkash.png',
        'ccd_nagad'    => 'assets/img/nagad.png',
        'ccd_rocket'   => 'assets/img/rocket.png',
        'ccd_payoneer' => 'assets/img/payoneer.png',
    );
    $account_title = $titles[$method];
    $img_url       = plugins_url($images[$method], __FILE__);

    // Check if advance payment is enabled and used
    $is_advance_payment = $order->get_meta('_ccd_advance_payment_received');
    $original_total = $order->get_meta('_ccd_original_order_total');
    $advance_amount = $order->get_meta('_ccd_advance_amount_paid');
    $remaining_amount = $order->get_meta('_ccd_remaining_amount');
?>
    <div class="form-field form-field-wide">
        <img src='<?php echo esc_attr($img_url); ?>' width="150" />
        <table class="wp-list-table widefat fixed striped posts">
            <tbody>
                <tr>
                    <th><strong><?php echo esc_html($account_title); ?></strong></th>
                    <td>: <?php echo esc_attr($account); ?></td>
                </tr>
                <tr>
                    <th><strong><?php esc_html_e('Transaction ID.', 'ccd-payment-gateway-domain'); ?></strong></th>
                    <td>: <?php echo esc_attr($transaction); ?></td>
                </tr>
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
    $map    = ccd_payment_field_map();

    if (!isset($map[$method])) {
        return;
    }

    $meta_keys   = array_values($map[$method]);
    $account     = sanitize_text_field($order->get_meta($meta_keys[0]));
    $transaction = sanitize_text_field($order->get_meta($meta_keys[1]));

    $titles = array(
        'ccd_bkash'    => 'bKash No:',
        'ccd_nagad'    => 'Nagad No:',
        'ccd_rocket'   => 'Rocket No:',
        'ccd_payoneer' => 'Payoneer Email:',
    );
    $account_title = $titles[$method];

    // Check if advance payment is enabled and used
    $is_advance_payment = $order->get_meta('_ccd_advance_payment_received');
    $original_total = $order->get_meta('_ccd_original_order_total');
    $advance_amount = $order->get_meta('_ccd_advance_amount_paid');
    $remaining_amount = $order->get_meta('_ccd_remaining_amount');
?>
    <table>
        <tr>
            <th><?php echo esc_html($account_title); ?></th>
            <td><?php echo esc_attr($account); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Transaction ID:', 'ccd-payment-gateway-domain'); ?></th>
            <td><?php echo esc_attr($transaction); ?></td>
        </tr>
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

    $labels = array(
        'ccd_bkash'    => 'Bkash',
        'ccd_nagad'    => 'Nagad',
        'ccd_rocket'   => 'Rocket',
        'ccd_payoneer' => 'Payoneer',
    );
    $map = ccd_payment_field_map();

    $account = '';
    $tran_id = '';

    foreach ($map as $method => $fields) {
        $meta_keys = array_values($fields);
        $number    = $order->get_meta($meta_keys[0]);
        if ($number !== '') {
            $account = sanitize_text_field($number) . ' ( ' . $labels[$method] . ' )';
            $tran_id = sanitize_text_field($order->get_meta($meta_keys[1]));
            break;
        }
    }

    return array($account, $tran_id);
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
        
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'ccd-payment-gateway-domain') . '</p></div>';
    }
    
    $enable_advance_payment = get_option('ccd_enable_advance_payment', 'no');
    $status_label = get_option('ccd_advance_payment_status_label', 'Advance Received');
    $advance_title = get_option('ccd_advance_payment_title', '');
    $advance_text = get_option('ccd_advance_payment_text', '');
    $remaining_text = get_option('ccd_remaining_payment_text', '');
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
                            <?php echo esc_html__('When enabled, customers will pay only the delivery/shipping charge as advance payment. The order will be set to "Advance Received" status after payment confirmation.', 'ccd-payment-gateway-domain'); ?>
                        </p>
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

    $shipping_total = $order->get_shipping_total();
    $shipping_tax = $order->get_shipping_tax();

    return floatval($shipping_total) + floatval($shipping_tax);
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
    if (!function_exists('WC') || !WC()->cart || !WC()->cart->needs_shipping()) {
        return null;
    }

    $cart           = WC()->cart;
    $shipping_total = $cart->get_shipping_total();
    $shipping_tax   = $cart->get_shipping_tax();
    $advance_amount = floatval($shipping_total) + floatval($shipping_tax);

    if ($advance_amount <= 0) {
        return null;
    }

    $cart_total       = floatval($cart->get_total(''));
    $remaining_amount = $cart_total - $advance_amount;

    $title = get_option('ccd_advance_payment_title', '');
    $title = !empty($title) ? $title : __('Advance Payment Information:', 'ccd-payment-gateway-domain');
    $title = apply_filters('ccd_advance_payment_title', $title);

    $advance_text = get_option('ccd_advance_payment_text', '');
    $advance_text = !empty($advance_text) ? $advance_text : __('You will pay only the delivery charge as advance payment:', 'ccd-payment-gateway-domain');
    $advance_text = apply_filters('ccd_advance_payment_text', $advance_text);

    $remaining_text = get_option('ccd_remaining_payment_text', '');
    $remaining_text = !empty($remaining_text) ? $remaining_text : __('Remaining amount to be paid later:', 'ccd-payment-gateway-domain');
    $remaining_text = apply_filters('ccd_remaining_payment_text', $remaining_text);

    $advance_formatted   = wp_strip_all_tags(wc_price($advance_amount, array('currency' => get_woocommerce_currency())));
    $remaining_formatted = wp_strip_all_tags(wc_price($remaining_amount, array('currency' => get_woocommerce_currency())));

    // Support {amount} placeholders.
    $advance_text   = str_replace('{amount}', $advance_formatted, $advance_text);
    $remaining_text = str_replace('{amount}', $remaining_formatted, $remaining_text);

    return array(
        'title'           => wp_strip_all_tags($title),
        'advanceText'     => wp_strip_all_tags($advance_text),
        'remainingText'   => wp_strip_all_tags($remaining_text),
        'advanceAmount'   => $advance_formatted,
        'remainingAmount' => $remaining_formatted,
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
    ->track_feature('advance_payment', function () { return get_option('ccd_enable_advance_payment', 'no') === 'yes'; })
    ->add_deactivation_survey();
});
