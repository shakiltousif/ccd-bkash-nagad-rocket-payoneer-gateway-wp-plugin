<?php

class CCD_Payment_Bkash extends WC_Payment_Gateway
{


    public $bkash_number;
    public $bkash_account_type;
    public $bkash_charge;
    public $bkash_order_status;
    public $bkash_instructions;

    public function __construct()
    {
        $this->id                     = 'ccd_bkash';
        $this->title                  = $this->get_option('bkash_title', 'Payment With bKash');
        $this->description            = $this->get_option('bkash_description', 'Please fill up this form to confirm the payment');
        $this->method_title           = esc_html("bKash Gateway", "ccd-payment-gateway-domain");
        $this->method_description     = esc_html("Take Payments By Bkash. So that we can easily accept payments by Confirming Transaction ID and Customer Billing Number.", "ccd-payment-gateway-domain");

        // Explicitly declare the properties here.
        $this->bkash_number           = $this->get_option('bkash_number');
        $this->bkash_account_type     = $this->get_option('bkash_account_type');
        $this->bkash_charge           = $this->get_option('bkash_charge');
        $this->bkash_order_status     = $this->get_option('bkash_order_status');
        $this->bkash_instructions     = $this->get_option('bkash_instructions');

        $this->has_fields             = true;

        $this->ccd_bkash_payment_options_fields();
        $this->form_fields = ccd_extend_form_fields($this->id, $this->form_fields);
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'ccd_bkash_thankyou_page_function'), 10, 2);
        add_action('woocommerce_email_before_order_table', array($this, 'ccd_bkash_email_instructions_function'), 15, 5);
    }

    public function ccd_bkash_payment_options_fields()
    {
        $this->form_fields = array(
            'enabled'     =>    array(
                'title'        => esc_html('Enable/Disable', "ccd-payment-gateway-domain"),
                'type'         => 'checkbox',
                'default'    => 'yes'
            ),
            'bkash_title'     => array(
                'title'     => esc_html('Title', "ccd-payment-gateway-domain"),
                'type'         => 'text',
                'default'    => esc_html('Payment With bKash', "ccd-payment-gateway-domain")
            ),
            'bkash_description' => array(
                'title'        => esc_html('Description', "ccd-payment-gateway-domain"),
                'type'         => 'textarea',
                'default'    => esc_html('Please fill up this form to confirm the payment', "ccd-payment-gateway-domain"),
                'desc_tip'    => true
            ),
            'bkash_instructions' => array(
                'title'           => esc_html('Thank you page message', "ccd-payment-gateway-domain"),
                'type'            => 'textarea',
                'description'     => esc_html('Thank you page message that will be added to the thank you page and emails.', "ccd-payment-gateway-domain"),
                'default'         => esc_html('Thanks for purchasing through bKash. We will check and give you update as soon as possible.', "ccd-payment-gateway-domain"),
                'desc_tip'        => true
            ),
            'bkash_number'    => array(
                'title'            => esc_html('bKash Number', "ccd-payment-gateway-domain"),
                'description'     => esc_html('Add a bKash mobile no which will be shown in checkout page', "ccd-payment-gateway-domain"),
                'type'            => 'text',
                'desc_tip'      => true
            ),
            'bkash_account_type'    => array(
                'title'            => esc_html('Bkash Account Type', "ccd-payment-gateway-domain"),
                'type'            => 'select',
                'class'           => 'wc-enhanced-select',
                'description'     => esc_html('Select bKash account type', "ccd-payment-gateway-domain"),
                'options'    => array(
                    'Agent'        => esc_html('Agent', "ccd-payment-gateway-domain"),
                    'Personal'    => esc_html('Personal', "ccd-payment-gateway-domain")
                ),
                'desc_tip'      => true,
                'default'    => 'Agent'
            ),
            'bkash_charge'     =>    array(
                'title'            => esc_html__('Enable bKash Charge', "ccd-payment-gateway-domain"),
                'type'             => 'checkbox',
                'label'            => esc_html__('Add 1.85% bKash "Send Money" charge to the net price', "ccd-payment-gateway-domain"),
                'default'        => 'no',
                'description'     => esc_html__('If a product price is 1000 then customer have to pay ( 1000 + 18.5 ) = 1018.5 Here 18.5 is bKash send money charge', "ccd-payment-gateway-domain"),
                'desc_tip'        => true
            ),
            'bkash_order_status' => array(
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

        ccd_render_charge_note($this->id);

        ccd_render_advance_notice();

        echo wpautop(wptexturize(esc_html__($this->description, "ccd-payment-gateway-domain")));
        ccd_render_account_box($this->id);

?>

        <div class="payment_box_ccd_child">
            <table>
                <tr>
                    <td><label for="bkash_number"><?php esc_html_e('bKash Number', "ccd-payment-gateway-domain"); ?></label></td>
                    <td><input class="widefat" type="text" name="bkash_number" id="bkash_number" placeholder="Ex. 018XXXXXXXX"></td>
                </tr>
                <tr>
                    <td><label for="bkash_transaction_id"><?php esc_html_e('bKash Transaction ID', "ccd-payment-gateway-domain"); ?></label></td>
                    <td><input class="widefat" type="text" name="bkash_transaction_id" id="bkash_transaction_id" placeholder="Ex. 8N7A6D5EE7M"></td>
                </tr>
            </table>
        </div>
        <?php ccd_render_payment_proof_field($this->id); ?>

<?php
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = wc_get_order($order_id);

        if (!ccd_apply_advance_payment($order, 'bKash')) {
            ccd_apply_order_status($order, $this->bkash_order_status, 'bKash');
        }

        // Reduce stock levels
        wc_reduce_stock_levels($order->get_id());

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    public function ccd_bkash_thankyou_page_function($text = '', $order = null)
    {
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order(absint(get_query_var('order-received')));
        }
        if ($order && $order->get_payment_method() === $this->id && '' !== trim((string) $this->bkash_instructions)) {
            return wp_kses_post($this->bkash_instructions);
        }
        return $text;
    }


    public function ccd_bkash_email_instructions_function($order, $sent_to_admin, $plain_text = false)
    {
        if ($order->get_payment_method() != $this->id)
            return;
        if ($this->bkash_instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wpautop(wptexturize($this->bkash_instructions)) . PHP_EOL;
        }
    }
}
