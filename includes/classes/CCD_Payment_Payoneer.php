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
        $this->method_title         = esc_html__("Payoneer Gateway", "codecarebd-bkash-nagad-rocket-payoneer-gateway");
        $this->method_description     = esc_html__("Take Payments By Payoneer. So that we can easily accept payments by Confirming Transaction ID and Customer Billing Number.", "codecarebd-bkash-nagad-rocket-payoneer-gateway");
        $this->ccd_payoneer_recipient_email = $this->get_option('ccd_payoneer_recipient_email');
        $this->ccd_payoneer_account_type     = $this->get_option('ccd_payoneer_account_type');
        $this->ccd_payoneer_order_status = $this->get_option('ccd_payoneer_order_status');
        $this->ccd_payoneer_instructions = $this->get_option('ccd_payoneer_instructions');
        $this->has_fields = true;

        $this->ccd_payoneer_payment_options_fields();
        $this->form_fields = ccd_extend_form_fields($this->id, $this->form_fields);
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'ccd_payoneer_thankyou_page_function'), 10, 2);
        add_action('woocommerce_email_before_order_table', array($this, 'ccd_payoneer_email_instructions_function'), 15, 5);
    }

    public function ccd_payoneer_payment_options_fields()
    {
        $this->form_fields = array(
            'enabled'     =>    array(
                'title'        => esc_html__('Enable/Disable', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'type'         => 'checkbox',
                'default'    => 'yes'
            ),
            'ccd_payoneer_title'     => array(
                'title'     => esc_html__('Title', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'type'         => 'text',
                'default'    => esc_html__('Payment With Payoneer', "codecarebd-bkash-nagad-rocket-payoneer-gateway")
            ),
            'ccd_payoneer_description' => array(
                'title'        => esc_html__('Description', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'type'         => 'textarea',
                'default'    => esc_html__('Please fill up this form to confirm the payment', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'desc_tip'    => true
            ),
            'ccd_payoneer_instructions' => array(
                'title'           => esc_html__('Thank you page message', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'type'            => 'textarea',
                'description'     => esc_html__('Thank you page message that will be added to the thank you page and emails.', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'default'         => esc_html__('Thanks for purchasing through Payoneer. We will check and give you update as soon as possible.', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'desc_tip'        => true
            ),
            'ccd_payoneer_recipient_email'    => array(
                'title'            => esc_html__('Payoneer Recipient Email', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'description'     => esc_html__('Add a Payoneer email which will be shown in checkout page', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'type'            => 'text',
                'desc_tip'      => true
            ),
            'ccd_payoneer_order_status' => array(
                'title'       => esc_html__('Order Status', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => esc_html__('Choose whether status you wish after checkout.', "codecarebd-bkash-nagad-rocket-payoneer-gateway"),
                'default'     => 'wc-on-hold',
                'desc_tip'    => true,
                'options'     => wc_get_order_statuses()
            )
        );
    }

    public function payment_fields()
    {
        global $woocommerce;
        
        ccd_render_advance_notice();

        // $bkash_charge = ($this->bkash_charge == 'yes') ? esc_html__(' Also note that 1.85% bKash "SEND MONEY" cost will be added with net price. Total amount you need to send us at', "stb") . ' ' . get_woocommerce_currency_symbol() . $woocommerce->cart->total : '';
        // echo wpautop(wptexturize(esc_html($this->description)) . $bkash_charge);
        ccd_render_account_box($this->id);

?>

        <div class="payment_box_ccd_child">
            <table>
                <tr>
                    <td><label for="ccd_payoneeer_sender_email"><?php esc_html_e('Payoneer Email', "codecarebd-bkash-nagad-rocket-payoneer-gateway"); ?></label></td>
                    <td><input class="widefat" type="email" name="ccd_payoneeer_sender_email" id="ccd_payoneeer_sender_email" placeholder="Ex. example@example.com"></td>
                </tr>
                <tr>
                    <td><label for="ccd_payoneer_transaction_id"><?php esc_html_e('Transaction ID', "codecarebd-bkash-nagad-rocket-payoneer-gateway"); ?></label></td>
                    <td><input class="widefat" type="text" name="ccd_payoneer_transaction_id" id="ccd_payoneer_transaction_id" placeholder="Ex. 8N7A6D5EE7M"></td>
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

        if (!ccd_apply_advance_payment($order, 'Payoneer')) {
            ccd_apply_order_status($order, $this->ccd_payoneer_order_status, 'Payoneer');
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

    public function ccd_payoneer_thankyou_page_function($text = '', $order = null)
    {
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order(absint(get_query_var('order-received')));
        }
        if ($order && $order->get_payment_method() === $this->id && '' !== trim((string) $this->ccd_payoneer_instructions)) {
            return wp_kses_post($this->ccd_payoneer_instructions);
        }
        return $text;
    }


    public function ccd_payoneer_email_instructions_function($order, $sent_to_admin, $plain_text = false)
    {
        if ($order->get_payment_method() != $this->id)
            return;
        if ($this->ccd_payoneer_instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wpautop(wptexturize($this->ccd_payoneer_instructions)) . PHP_EOL;
        }
    }
}
