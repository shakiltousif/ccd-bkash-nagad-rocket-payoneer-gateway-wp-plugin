<?php

class CCD_Payment_Rocket extends WC_Payment_Gateway
{

    public $ccd_rocket_number;
    public $ccd_rocket_account_type;
    public $ccd_rocket_charge;
    public $cdd_rocket_order_status;
    public $ccd_rocket_instructions;

    public function __construct()
    {
        $this->id                     = 'ccd_rocket';
        $this->title                 = $this->get_option('ccd_rocket_title', 'Payment With Rocket');
        $this->description             = $this->get_option('ccd_rocket_description', 'Please fill up this form to confirm the payment');
        $this->method_title         = esc_html("Rocket Gateway", "ccd-payment-gateway-domain");
        $this->method_description     = esc_html("Take Payments By Rocket. So that we can easily accept payments by Confirming Transaction ID and Customer Billing Number.", "ccd-payment-gateway-domain");
        $this->ccd_rocket_number = $this->get_option('ccd_rocket_number');
        $this->ccd_rocket_account_type     = $this->get_option('ccd_rocket_account_type');
        $this->ccd_rocket_charge = $this->get_option('ccd_rocket_charge');
        $this->cdd_rocket_order_status = $this->get_option('cdd_rocket_order_status');
        $this->ccd_rocket_instructions = $this->get_option('ccd_rocket_instructions');
        $this->has_fields = true;

        $this->ccd_rocket_payment_options_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_filter('woocommerce_thankyou_order_received_text', array($this, 'ccd_rocket_thankyou_page_function'));
        add_action('woocommerce_email_before_order_table', array($this, 'ccd_rocket_email_instructions_function'), 15, 5);
    }

    public function ccd_rocket_payment_options_fields()
    {
        $this->form_fields = array(
            'enabled'     =>    array(
                'title'        => esc_html('Enable/Disable', "ccd-payment-gateway-domain"),
                'type'         => 'checkbox',
                'default'    => 'yes'
            ),
            'ccd_rocket_title'     => array(
                'title'     => esc_html('Title', "ccd-payment-gateway-domain"),
                'type'         => 'text',
                'default'    => esc_html('Payment With Rocket', "ccd-payment-gateway-domain")
            ),
            'ccd_rocket_description' => array(
                'title'        => esc_html('Description', "ccd-payment-gateway-domain"),
                'type'         => 'textarea',
                'default'    => esc_html('Please fill up this form to confirm the payment', "ccd-payment-gateway-domain"),
                'desc_tip'    => true
            ),
            'ccd_rocket_instructions' => array(
                'title'           => esc_html('Thank you page message', "ccd-payment-gateway-domain"),
                'type'            => 'textarea',
                'description'     => esc_html('Thank you page message that will be added to the thank you page and emails.', "ccd-payment-gateway-domain"),
                'default'         => esc_html('Thanks for purchasing through Rocket. We will check and give you update as soon as possible.', "ccd-payment-gateway-domain"),
                'desc_tip'        => true
            ),
            'ccd_rocket_number'    => array(
                'title'            => esc_html('Rocket Number', "ccd-payment-gateway-domain"),
                'description'     => esc_html('Add a Rocket mobile no which will be shown in checkout page', "ccd-payment-gateway-domain"),
                'type'            => 'text',
                'desc_tip'      => true
            ),
            'ccd_rocket_account_type'    => array(
                'title'            => esc_html('Rocket Account Type', "ccd-payment-gateway-domain"),
                'type'            => 'select',
                'class'           => 'wc-enhanced-select',
                'description'     => esc_html('Select Rocket account type', "ccd-payment-gateway-domain"),
                'options'    => array(
                    'Agent'        => esc_html('Agent', "ccd-payment-gateway-domain"),
                    'Personal'    => esc_html('Personal', "ccd-payment-gateway-domain")
                ),
                'desc_tip'      => true
            ),
            'ccd_rocket_charge'     =>    array(
                'title'            => esc_html__('Enable Rocket Charge', "ccd-payment-gateway-domain"),
                'type'             => 'checkbox',
                'label'            => esc_html__('Add 1.8% Rocket "Send Money" charge to the net price', "ccd-payment-gateway-domain"),
                'default'        => 'no',
                'description'     => esc_html__('If a product price is 1000 then customer have to pay ( 1000 + 18 ) = 1018 Here 18.5 is Rocket send money charge', "ccd-payment-gateway-domain"),
                'desc_tip'        => true

            ),
            'cdd_rocket_order_status' => array(
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

        $ccd_rocket_charge = '';

        if (($this->ccd_rocket_charge == 'yes')) {
            $ccd_rocket_charge = '<div class="ccd_extra_charge_note">' . wpautop(wptexturize(esc_html__(' Note: 1.85% Rocket "Send Money" cost will be added with the net price. Total amount: ', "ccd-payment-gateway-domain") . ' ' . get_woocommerce_currency_symbol() . $woocommerce->cart->total)) . '</div>';
        }

        echo $ccd_rocket_charge;

        echo wpautop(wptexturize(esc_html__($this->description, "ccd-payment-gateway-domain")) . $ccd_rocket_charge);

        if (isset($this->ccd_rocket_account_type)) {
            echo wpautop(wptexturize("Rocket " . $this->ccd_rocket_account_type . " Number : " . $this->ccd_rocket_number));
        }
?>
        <div class="payment_box_ccd_child">
            <table>
                <tr>
                    <td><label for="ccd_rocket_number"><?php esc_html_e('Rocket Number', "ccd-payment-gateway-domain"); ?></label></td>
                    <td><input class="widefat" type="text" name="ccd_rocket_number" id="ccd_rocket_number" placeholder="Ex. 018XXXXXXXX"></td>
                </tr>
                <tr>
                    <td><label for="ccd_rocket_transaction_id"><?php esc_html_e('Rocket Transaction ID', "ccd-payment-gateway-domain"); ?></label></td>
                    <td><input class="widefat" type="text" name="ccd_rocket_transaction_id" id="ccd_rocket_transaction_id" placeholder="Ex. 8N7A6D5EE7M"></td>
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
        if ('wc-' === substr($this->cdd_rocket_order_status, 0, 3)) {

            $status = substr($this->cdd_rocket_order_status, 3);
        } else {

            $status = $this->cdd_rocket_order_status;
        }

        // Mark as on-hold (we're awaiting the bKash)
        $order->update_status($status, esc_html('Checkout with Rocket payment. ', "ccd-payment-gateway-domain"));

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

    public function ccd_rocket_thankyou_page_function()
    {
        $order_id = get_query_var('order-received');
        $order = new WC_Order($order_id);
        if ($order->get_payment_method() == $this->id) {

            $thankyou = $this->ccd_rocket_instructions;
            return $thankyou;
        } else {

            return esc_html__('Thank you. Your order has been received.', "ccd-payment-gateway-domain");
        }
    }


    public function ccd_rocket_email_instructions_function($order, $sent_to_admin, $plain_text = false)
    {
        if ($order->get_payment_method() != $this->id)
            return;
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }
}
