<?php

class CCD_Payment_Payoneer extends WC_Payment_Gateway
{

    public $ccd_payoneer_recipient_email;
    public $ccd_payoneer_account_type;
    public $ccd_payoneer_order_status;
    public $ccd_payoneer_instructions;

    public function __construct()
    {
        $this->id                     = 'ccd_payoneer';
        $this->title                 = $this->get_option('ccd_payoneer_title', 'Payment With Payoneer');
        $this->description             = $this->get_option('ccd_payoneer_description', 'Please fill up this form to confirm the payment');
        $this->method_title         = esc_html("Payoneer Gateway", "ccd-payment-gateway-domain");
        $this->method_description     = esc_html("Take Payments By Payoneer. So that we can easily accept payments by Confirming Transaction ID and Customer Billing Number.", "ccd-payment-gateway-domain");
        $this->ccd_payoneer_recipient_email = $this->get_option('ccd_payoneer_recipient_email');
        $this->ccd_payoneer_account_type     = $this->get_option('ccd_payoneer_account_type');
        $this->ccd_payoneer_order_status = $this->get_option('ccd_payoneer_order_status');
        $this->ccd_payoneer_instructions = $this->get_option('ccd_payoneer_instructions');
        $this->has_fields = true;

        $this->ccd_payoneer_payment_options_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'ccd_payoneer_thankyou_page_function'));
        add_action('woocommerce_email_before_order_table', array($this, 'ccd_payoneer_email_instructions_function'), 15, 5);
    }

    public function ccd_payoneer_payment_options_fields()
    {
        $this->form_fields = array(
            'enabled'     =>    array(
                'title'        => esc_html('Enable/Disable', "ccd-payment-gateway-domain"),
                'type'         => 'checkbox',
                'default'    => 'yes'
            ),
            'ccd_payoneer_title'     => array(
                'title'     => esc_html('Title', "ccd-payment-gateway-domain"),
                'type'         => 'text',
                'default'    => esc_html('Payment With Payoneer', "ccd-payment-gateway-domain")
            ),
            'ccd_payoneer_description' => array(
                'title'        => esc_html('Description', "ccd-payment-gateway-domain"),
                'type'         => 'textarea',
                'default'    => esc_html('Please fill up this form to confirm the payment', "ccd-payment-gateway-domain"),
                'desc_tip'    => true
            ),
            'ccd_payoneer_instructions' => array(
                'title'           => esc_html('Thank you page message', "ccd-payment-gateway-domain"),
                'type'            => 'textarea',
                'description'     => esc_html('Thank you page message that will be added to the thank you page and emails.', "ccd-payment-gateway-domain"),
                'default'         => esc_html('Thanks for purchasing through Payoneer. We will check and give you update as soon as possible.', "ccd-payment-gateway-domain"),
                'desc_tip'        => true
            ),
            'ccd_payoneer_recipient_email'    => array(
                'title'            => esc_html('Payoneer Recipient Email', "ccd-payment-gateway-domain"),
                'description'     => esc_html('Add a Payoneer email which will be shown in checkout page', "ccd-payment-gateway-domain"),
                'type'            => 'text',
                'desc_tip'      => true
            ),
            'ccd_payoneer_order_status' => array(
                'title'       => esc_html('Order Status', "ccd-payment-gateway-domain"),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => esc_html('Choose whether status you wish after checkout.', "ccd-payment-gateway-domain"),
                'default'     => 'wc-on-hold',
                'desc_tip'    => true,
                'options'     => wc_get_order_statuses()
            )
        );
    }

    public function payment_fields()
    {
        global $woocommerce;
        // $bkash_charge = ($this->bkash_charge == 'yes') ? esc_html__(' Also note that 1.85% bKash "SEND MONEY" cost will be added with net price. Total amount you need to send us at', "stb") . ' ' . get_woocommerce_currency_symbol() . $woocommerce->cart->total : '';
        // echo wpautop(wptexturize(esc_html__($this->description, "ccd-payment-gateway-domain")) . $bkash_charge);
        if (isset($this->ccd_payoneer_recipient_email)) {
            echo wpautop(wptexturize("Payoneer Recipient Email : " . $this->ccd_payoneer_recipient_email));
        }

?>

        <div class="payment_box_ccd_child">
            <table>
                <tr>
                    <td><label for="ccd_payoneeer_sender_email"><?php esc_html_e('Payoneer Email', "ccd-payment-gateway-domain"); ?></label></td>
                    <td><input class="widefat" type="email" name="ccd_payoneeer_sender_email" id="ccd_payoneeer_sender_email" placeholder="Ex. example@example.com"></td>
                </tr>
                <tr>
                    <td><label for="ccd_payoneer_transaction_id"><?php esc_html_e('Transaction ID', "ccd-payment-gateway-domain"); ?></label></td>
                    <td><input class="widefat" type="text" name="ccd_payoneer_transaction_id" id="ccd_payoneer_transaction_id" placeholder="Ex. 8N7A6D5EE7M"></td>
                </tr>
            </table>
        </div>

<?php
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);

        $status = null;
        if ('wc-' === substr($this->ccd_payoneer_order_status, 0, 3)) {

            $status = substr($this->ccd_payoneer_order_status, 3);
        } else {

            $status = $this->ccd_payoneer_order_status;
        }

        // Mark as on-hold (we're awaiting the bKash)
        $order->update_status($status, esc_html('Checkout with bKash payment. ', "ccd-payment-gateway-domain"));

        // Reduce stock levels
        $order->reduce_order_stock();

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    public function ccd_payoneer_thankyou_page_function()
    {
        $order_id = get_query_var('order-received');
        $order = new WC_Order($order_id);
        if ($order->get_payment_method() == $this->id) {

            $thankyou = $this->ccd_payoneer_instructions;
            return $thankyou;
        } else {

            return esc_html__('Thank you. Your order has been received.', "ccd-payment-gateway-domain");
        }
    }


    public function ccd_payoneer_email_instructions_function($order, $sent_to_admin, $plain_text = false)
    {
        if ($order->get_payment_method() != $this->id)
            return;
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }
}
