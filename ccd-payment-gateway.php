<?php

/**
 * Plugin Name: CodeCareBD - bKash, Nagad, Rocket, Payoneer Gateway
 * Plugin URI: https://wordpress.org/plugins/codecarebd-bkash-nagad-rocket-payoneer-gateway
 * Description: CodeCareBD - Bkash, Nagad, Rocket, Payoneer Gateway plugin is for WooCommerce. This plugin will help you to integrate Bkash, Nagad, Rocket, and Payoneer Payment Gateway.
 * Author: Shakil Ahamed
 * Version: 0.7
 * Tested up to: 6.4.3
 * Author URI: https://shakilahamed.com
 * Text Domain: ccd-payment-gateway-domain
 * 
 * Woocommerce tested up to: 8.5.2
 *
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly


//include plugin modules
include_once(ABSPATH . 'wp-admin/includes/plugin.php');

//checking if elementor active
if (is_plugin_active('woocommerce/woocommerce.php')) {

    add_filter('woocommerce_payment_gateways', 'ccd_rocket_payment_options');

    add_action('plugins_loaded', 'ccd_payment_options_init');

    add_action('woocommerce_checkout_update_order_meta', 'ccd_bkash_additional_fields_update_function');

    add_action('woocommerce_admin_order_data_after_billing_address', 'ccd_bkash_admin_order_data_function');

    add_action('woocommerce_order_details_after_customer_details', 'ccd_additional_info_order_review_fields_function');

    add_filter('woocommerce_account_orders_columns', 'ccd_admin_new_column_function');

    add_filter('manage_woocommerce_page_wc-orders_columns', 'ccd_admin_new_column_function_new_method');

    add_action('manage_shop_order_posts_custom_column', 'ccd_admin_column_value_function', 2);

    add_action('manage_woocommerce_page_wc-orders_custom_column', 'ccd_admin_column_value_function_new_method', 10, 2);


    add_action('wp_enqueue_scripts', 'ccd_checkout_page_enqueue_script');


    /**
     * If charge is activated
     */
    $bkash_charge = get_option('woocommerce_ccd_bkash_settings');
    $nagad_charge = get_option('woocommerce_ccd_nagad_settings');
    $rocket_charge = get_option('woocommerce_ccd_rocket_settings');
    if (isset($bkash_charge['bkash_charge']) || isset($nagad_charge['ccd_nagad_charge']) || isset($rocket_charge['ccd_rocket_charge'])) {
        if ($bkash_charge['bkash_charge'] == 'yes' || $nagad_charge['ccd_nagad_charge'] == 'yes' || $rocket_charge['ccd_rocket_charge'] == 'yes') {


            add_action('woocommerce_cart_calculate_fees', 'ccd_payment_charge_calculator');
            function ccd_payment_charge_calculator()
            {

                global $woocommerce;
                $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
                $current_gateway = '';

                if (!empty($available_gateways)) {
                    if (isset($woocommerce->session->chosen_payment_method) && isset($available_gateways[$woocommerce->session->chosen_payment_method])) {
                        $current_gateway = $available_gateways[$woocommerce->session->chosen_payment_method];
                    }
                }

                if ($current_gateway != '') {

                    $current_gateway_id = $current_gateway->id;

                    if (is_admin() && !defined('DOING_AJAX')) {
                        return;
                    }


                    if ($current_gateway_id == 'ccd_bkash') {
                        $percentage = 0.0185;
                        $surcharge = round($woocommerce->cart->cart_contents_total * $percentage);
                        $woocommerce->cart->add_fee(esc_html__('bKash Charge', 'ccd-payment-gateway-domain'), $surcharge, true, '');
                    } else if ($current_gateway_id == 'ccd_nagad') {
                        $percentage = 0.0145;
                        $surcharge = round($woocommerce->cart->cart_contents_total * $percentage);
                        $woocommerce->cart->add_fee(esc_html__('Nagad Charge', 'ccd-payment-gateway-domain'), $surcharge, true, '');
                    } else if ($current_gateway_id == 'ccd_rocket') {
                        $percentage = 0.018;
                        $surcharge = round($woocommerce->cart->cart_contents_total * $percentage);
                        $woocommerce->cart->add_fee(esc_html__('Rocket Charge', 'ccd-payment-gateway-domain'), $surcharge, true, '');
                    }
                }
            }
        }
    }
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
 * Update bKash field to database
 */
function ccd_bkash_additional_fields_update_function($order_id)
{

    if ($_POST['payment_method'] == 'ccd_bkash') {
        $bkash_number = sanitize_text_field($_POST['bkash_number']);
        $bkash_transaction_id = sanitize_text_field($_POST['bkash_transaction_id']);

        $number = isset($bkash_number) ? $bkash_number : '';
        $transaction = isset($bkash_transaction_id) ? $bkash_transaction_id : '';

        update_post_meta($order_id, '_bkash_number', $number);
        update_post_meta($order_id, '_bkash_transaction', $transaction);
    } else if ($_POST['payment_method'] == 'ccd_nagad') {

        $nagad_number = sanitize_text_field($_POST['ccd_nagad_number']);
        $nagad_transaction_id = sanitize_text_field($_POST['ccd_nagad_transaction_id']);

        $number = isset($nagad_number) ? $nagad_number : '';
        $transaction = isset($nagad_transaction_id) ? $nagad_transaction_id : '';

        update_post_meta($order_id, '_ccd_nagad_number', $number);
        update_post_meta($order_id, '_ccd_nagad_transaction', $transaction);
    } else if ($_POST['payment_method'] == 'ccd_rocket') {

        $rocket_number = sanitize_text_field($_POST['ccd_rocket_number']);
        $rocket_transaction_id = sanitize_text_field($_POST['ccd_rocket_transaction_id']);

        $number = isset($rocket_number) ? $rocket_number : '';
        $transaction = isset($rocket_transaction_id) ? $rocket_transaction_id : '';

        update_post_meta($order_id, '_ccd_rocket_number', $number);
        update_post_meta($order_id, '_ccd_rocket_transaction', $transaction);
    } else if ($_POST['payment_method'] == 'ccd_payoneer') {

        $rocket_number = sanitize_text_field($_POST['ccd_payoneeer_sender_email']);
        $rocket_transaction_id = sanitize_text_field($_POST['ccd_payoneer_transaction_id']);

        $number = isset($rocket_number) ? $rocket_number : '';
        $transaction = isset($rocket_transaction_id) ? $rocket_transaction_id : '';

        update_post_meta($order_id, '_ccd_payoneeer_sender_email', $number);
        update_post_meta($order_id, '_ccd_payoneer_transaction_id', $transaction);
    } else {
        return;
    }
}


/**
 * Admin order page bKash data output
 */
function ccd_bkash_admin_order_data_function($order)
{
    $account_title = '';

    $account = '';

    $transaction = '';

    $img_url = '';

    if ($order->get_payment_method() == 'ccd_bkash') {

        if (get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_bkash_number', true)) {
            $account = sanitize_text_field(get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_bkash_number', true));
        }


        if (get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_bkash_transaction', true)) {
            $transaction = sanitize_text_field(get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_bkash_transaction', true));
        }

        $account_title = 'bKash No.';

        $img_url = plugins_url('assets/img/bkash.png', __FILE__);
    } else if ($order->get_payment_method() == 'ccd_nagad') {
        if (get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_nagad_number', true)) {
            $account = sanitize_text_field(get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_nagad_number', true));
        }

        if (get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_nagad_transaction', true)) {
            $transaction = sanitize_text_field(get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_nagad_transaction', true));
        }
        $account_title = 'Nagad No.';

        $img_url = plugins_url('assets/img/nagad.png', __FILE__);
    } else if ($order->get_payment_method() == 'ccd_rocket') {
        if (get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_rocket_number', true)) {
            $account = sanitize_text_field(get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_rocket_number', true));
        }

        if (get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_rocket_transaction', true)) {
            $transaction = sanitize_text_field(get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_rocket_transaction', true));
        }
        $account_title = 'Rocket No.';

        $img_url = plugins_url('assets/img/rocket.png', __FILE__);
    } else if ($order->get_payment_method() == 'ccd_payoneer') {
        if (get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_payoneeer_sender_email', true)) {
            $account = sanitize_text_field(get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_payoneeer_sender_email', true));
        }

        if (get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_payoneer_transaction_id', true)) {
            $transaction = sanitize_text_field(get_post_meta(isset($_GET['id']) ? $_GET['id'] : $_GET['post'], '_ccd_payoneer_transaction_id', true));
        }
        $account_title = 'Payoneer Email: ';

        $img_url = plugins_url('assets/img/payoneer.png', __FILE__);
    } else {
        return;
    }




?>
    <div class="form-field form-field-wide">
        <img src='<?php echo esc_attr($img_url); ?>' width="150" />
        <table class="wp-list-table widefat fixed striped posts">
            <tbody>
                <tr>
                    <th><strong><?php esc_html_e($account_title, 'ccd-payment-gateway-domain'); ?></strong></th>
                    <td>: <?php echo esc_attr($account); ?></td>
                </tr>
                <tr>
                    <th><strong><?php esc_html_e('Transaction ID.', 'ccd-payment-gateway-domain'); ?></strong></th>
                    <td>: <?php echo esc_attr($transaction); ?></td>

                </tr>
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
    global $wp;

    //Order ID
    $order_id  = absint($wp->query_vars['order-received']);

    $account_title = '';
    $account = '';
    $transaction = '';

    if ($order->get_payment_method() == 'ccd_bkash') {

        if (get_post_meta($order_id, '_bkash_number', true)) {

            $account = sanitize_text_field(get_post_meta($order_id, '_bkash_number', true));
        }
        if (get_post_meta($order_id, '_bkash_transaction', true)) {
            $transaction = sanitize_text_field(get_post_meta($order_id, '_bkash_transaction', true));
        }

        $account_title = 'bKash No:';
    } else if ($order->get_payment_method() == 'ccd_nagad') {
        if (get_post_meta($order_id, '_ccd_nagad_number', true)) {

            $account = sanitize_text_field(get_post_meta($order_id, '_ccd_nagad_number', true));
        }
        if (get_post_meta($order_id, '_ccd_nagad_transaction', true)) {
            $transaction = sanitize_text_field(get_post_meta($order_id, '_ccd_nagad_transaction', true));
        }

        $account_title = 'Nagad No:';
    } else if ($order->get_payment_method() == 'ccd_rocket') {
        if (get_post_meta($order_id, '_ccd_rocket_number', true)) {

            $account = sanitize_text_field(get_post_meta($order_id, '_ccd_rocket_number', true));
        }
        if (get_post_meta($order_id, '_ccd_rocket_transaction', true)) {
            $transaction = sanitize_text_field(get_post_meta($order_id, '_ccd_rocket_transaction', true));
        }

        $account_title = 'Rocket No:';
    } else if ($order->get_payment_method() == 'ccd_payoneer') {
        if (get_post_meta($order_id, '_ccd_payoneeer_sender_email', true)) {

            $account = sanitize_text_field(get_post_meta($order_id, '_ccd_payoneeer_sender_email', true));
        }
        if (get_post_meta($order_id, '_ccd_payoneer_transaction_id', true)) {
            $transaction = sanitize_text_field(get_post_meta($order_id, '_ccd_payoneer_transaction_id', true));
        }

        $account_title = 'Payoneer Email:';
    } else {
        return;
    }


?>
    <table>
        <tr>
            <th><?php esc_html_e($account_title, 'ccd-payment-gateway-domain'); ?></th>
            <td><?php echo esc_attr($account); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Transaction ID:', 'ccd-payment-gateway-domain'); ?></th>
            <td><?php echo esc_attr($transaction); ?></td>
        </tr>
    </table>
<?php

}

/**
 * Register new admin column
 */
function ccd_admin_new_column_function($columns)
{

    $new_columns = (is_array($columns)) ? $columns : array();
    unset($new_columns['order_actions']);
    $new_columns['account_no']     = esc_html__('Account No.', 'ccd-payment-gateway-domain');
    $new_columns['tran_id']     = esc_html__('Transaction ID', 'ccd-payment-gateway-domain');

    $new_columns['order_actions'] = $columns['order_actions'];
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
function ccd_admin_column_value_function($column)
{

    global $post;

    $account = '';
    if (get_post_meta($post->ID, '_bkash_number', true)) {
        $account = sanitize_text_field(get_post_meta($post->ID, '_bkash_number', true)) . ' ( Bkash )';
    } else if (get_post_meta($post->ID, '_ccd_nagad_number', true)) {
        $account = sanitize_text_field(get_post_meta($post->ID, '_ccd_nagad_number', true)) . ' ( Nagad )';
    } else if (get_post_meta($post->ID, '_ccd_rocket_number', true)) {
        $account = sanitize_text_field(get_post_meta($post->ID, '_ccd_rocket_number', true)) . ' ( Rocket )';
    } else if (get_post_meta($post->ID, '_ccd_payoneeer_sender_email', true)) {
        $account = sanitize_text_field(get_post_meta($post->ID, '_ccd_payoneeer_sender_email', true)) . ' ( Payoneer )';
    }

    $tran_id = '';

    if (get_post_meta($post->ID, '_bkash_transaction', true)) {
        $tran_id = sanitize_text_field(get_post_meta($post->ID, '_bkash_transaction', true));
    } else if (get_post_meta($post->ID, '_ccd_nagad_transaction', true)) {
        $tran_id = sanitize_text_field(get_post_meta($post->ID, '_ccd_nagad_transaction', true));
    } else if (get_post_meta($post->ID, '_ccd_rocket_transaction', true)) {
        $tran_id = sanitize_text_field(get_post_meta($post->ID, '_ccd_rocket_transaction', true));
    } else if (get_post_meta($post->ID, '_ccd_payoneer_transaction_id', true)) {
        $tran_id = sanitize_text_field(get_post_meta($post->ID, '_ccd_payoneer_transaction_id', true));
    }

    if ($column == 'account_no') {
        echo esc_attr($account);
    }
    if ($column == 'tran_id') {
        echo esc_attr($tran_id);
    }
}



// new method
function ccd_admin_column_value_function_new_method($column, $order)
{

    global $post;

    $account = '';
    if (get_post_meta($order->id, '_bkash_number', true)) {
        $account = sanitize_text_field(get_post_meta($order->id, '_bkash_number', true)) . ' ( Bkash )';
    } else if (get_post_meta($order->id, '_ccd_nagad_number', true)) {
        $account = sanitize_text_field(get_post_meta($order->id, '_ccd_nagad_number', true)) . ' ( Nagad )';
    } else if (get_post_meta($order->id, '_ccd_rocket_number', true)) {
        $account = sanitize_text_field(get_post_meta($order->id, '_ccd_rocket_number', true)) . ' ( Rocket )';
    } else if (get_post_meta($order->id, '_ccd_payoneeer_sender_email', true)) {
        $account = sanitize_text_field(get_post_meta($order->id, '_ccd_payoneeer_sender_email', true)) . ' ( Payoneer )';
    }

    $tran_id = '';

    if (get_post_meta($order->id, '_bkash_transaction', true)) {
        $tran_id = sanitize_text_field(get_post_meta($order->id, '_bkash_transaction', true));
    } else if (get_post_meta($order->id, '_ccd_nagad_transaction', true)) {
        $tran_id = sanitize_text_field(get_post_meta($order->id, '_ccd_nagad_transaction', true));
    } else if (get_post_meta($order->id, '_ccd_rocket_transaction', true)) {
        $tran_id = sanitize_text_field(get_post_meta($order->id, '_ccd_rocket_transaction', true));
    } else if (get_post_meta($order->id, '_ccd_payoneer_transaction_id', true)) {
        $tran_id = sanitize_text_field(get_post_meta($order->id, '_ccd_payoneer_transaction_id', true));
    }

    switch ($column) {
        case 'account_no':
            // Get custom order metadata
            echo esc_attr($account);
            break;

        case 'tran_id':
            // Get custom order metadata
            echo esc_attr($tran_id);
            break;
    }
}


// Enqueue script
function ccd_checkout_page_enqueue_script()
{
    if (is_checkout()) {

        // CSS
        wp_enqueue_style('ccd_checkout_page-css', plugin_dir_url(__FILE__) . 'assets/css/ccd-payment-gateway-checkout.css', array(), time());

        //js
        wp_enqueue_script('ccd_checkout_page-script', plugins_url('assets/js/ccd_scripts.js', __FILE__), array('jquery'), time(), true);
    }
}




add_action('woocommerce_checkout_process', 'ccd_bkash_payment_validation');

function ccd_bkash_payment_validation()
{

    // Checking if payment method is bKash
    if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'ccd_bkash') {

        // Check if bKash number is empty
        if (empty($_POST['bkash_number'])) {
            // Debugging message for bKash number validation
            wc_add_notice(__('Please enter your bKash number.', 'ccd-payment-gateway-domain'), 'error');
        } else {
            // Validate bKash number format
            $bkash_number = $_POST['bkash_number'];
            if (!preg_match("/^[0-9]{11}$/", $bkash_number)) {
                wc_add_notice(__('Please enter a valid bKash phone number.', 'ccd-payment-gateway-domain'), 'error');
            }
        }

        // Check if bKash transaction ID is empty
        if (empty($_POST['bkash_transaction_id'])) {
            // Debugging message for bKash transaction ID validation
            wc_add_notice(__('Please enter your bKash transaction ID.', 'ccd-payment-gateway-domain'), 'error');
        } elseif (strlen($_POST['bkash_transaction_id']) < 5) {
            // Check if bKash transaction ID has less than 5 digits
            wc_add_notice(__('Please enter a valid bKash transaction ID.', 'ccd-payment-gateway-domain'), 'error');
        }
    }

    // Checking if payment method is nagad
    if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'ccd_nagad') {

        // Check if Nagad number is empty
        if (empty($_POST['ccd_nagad_number'])) {
            // Debugging message for Nagad number validation
            wc_add_notice(__('Please enter your Nagad number.', 'ccd-payment-gateway-domain'), 'error');
        } else {
            // Validate bKash number format
            $ccd_nagad_number = $_POST['ccd_nagad_number'];
            if (!preg_match("/^[0-9]{11}$/", $ccd_nagad_number)) {
                wc_add_notice(__('Please enter a valid Nagad phone number.', 'ccd-payment-gateway-domain'), 'error');
            }
        }

        // Check if Nagad transaction ID is empty
        if (empty($_POST['ccd_nagad_transaction_id'])) {
            // Debugging message for Nagad transaction ID validation
            wc_add_notice(__('Please enter your Nagad transaction ID.', 'ccd-payment-gateway-domain'), 'error');
        } elseif (strlen($_POST['ccd_nagad_transaction_id']) < 5) {
            // Check if Nagad transaction ID has less than 5 digits
            wc_add_notice(__('Please enter a valid Nagad transaction ID.', 'ccd-payment-gateway-domain'), 'error');
        }
    }

    // Checking if payment method is Rocket
    if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'ccd_rocket') {

        // Check if Rocket number is empty
        if (empty($_POST['ccd_rocket_number'])) {
            // Debugging message for Rocket number validation
            wc_add_notice(__('Please enter your Rocket number.', 'ccd-payment-gateway-domain'), 'error');
        } else {
            // Validate Rocket number format
            $ccd_nagad_number = $_POST['ccd_rocket_number'];
            if (!preg_match("/^[0-9]{11}$/", $ccd_nagad_number)) {
                wc_add_notice(__('Please enter a valid Rocket phone number.', 'ccd-payment-gateway-domain'), 'error');
            }
        }

        // Check if Rocket transaction ID is empty
        if (empty($_POST['ccd_rocket_transaction_id'])) {
            // Debugging message for Rocket transaction ID validation
            wc_add_notice(__('Please enter your Rocket transaction ID.', 'ccd-payment-gateway-domain'), 'error');
        } elseif (strlen($_POST['ccd_rocket_transaction_id']) < 5) {
            // Check if Rocket transaction ID has less than 5 digits
            wc_add_notice(__('Please enter a valid Rocket transaction ID.', 'ccd-payment-gateway-domain'), 'error');
        }
    }


    // Checking if payment method is Payoneer
    if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'ccd_payoneer') {

        // Check if Payoneer sender email is empty
        if (empty($_POST['ccd_payoneeer_sender_email'])) {
            // Debugging message for Payoneer sender email validation
            wc_add_notice(__('Please enter your Payoneer sender email.', 'ccd-payment-gateway-domain'), 'error');
        } else {
            // Validate Payoneer sender email format
            $ccd_payoneer_sender_email = $_POST['ccd_payoneeer_sender_email'];
            if (!filter_var($ccd_payoneer_sender_email, FILTER_VALIDATE_EMAIL)) {
                wc_add_notice(__('Please enter a valid Payoneer sender email.', 'ccd-payment-gateway-domain'), 'error');
            }
        }

        // Check if Payoneer transaction ID is empty
        if (empty($_POST['ccd_payoneer_transaction_id'])) {
            // Debugging message for Payoneer transaction ID validation
            wc_add_notice(__('Please enter your Payoneer transaction ID.', 'ccd-payment-gateway-domain'), 'error');
        } elseif (strlen($_POST['ccd_payoneer_transaction_id']) < 5) {
            // Check if Payoneer transaction ID has less than 5 characters
            wc_add_notice(__('Please enter a valid Payoneer transaction ID.', 'ccd-payment-gateway-domain'), 'error');
        }
    }
}



//#################################
// ACTION LINKS
//#################################


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ccd_payment_gateway_action_links');

function ccd_payment_gateway_action_links($actions)
{
    $actions[] = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=checkout')) . '">' . __("Settings", "ccd-payment-gateway-domain") . '</a>';
    $actions[] = '<a href="https://codecarebd.com/contact" target="_blank">' . esc_html(__("Support", "ccd-payment-gateway-domain")) . '</a>';
    return $actions;
}
