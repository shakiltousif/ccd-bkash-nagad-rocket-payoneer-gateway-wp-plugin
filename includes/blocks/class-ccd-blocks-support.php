<?php

/**
 * WooCommerce Cart & Checkout Blocks integration for the CodeCareBD gateways.
 *
 * One instance is registered per gateway in ccd_gateway_config(). It exposes
 * the gateway to the block checkout, enqueues the shared client script and
 * passes the per-gateway data the script needs.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
    return;
}

class CCD_Blocks_Support extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
{
    /**
     * Gateway settings (woocommerce_<id>_settings).
     *
     * @var array
     */
    protected $settings = array();

    /**
     * @param string $name Gateway id (e.g. ccd_bkash).
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Load the gateway settings.
     */
    public function initialize()
    {
        $this->settings = ccd_gateway_settings($this->name);
    }

    /**
     * Whether the gateway is enabled (and, for gateways that need it, configured).
     *
     * @return bool
     */
    public function is_active()
    {
        if (!isset($this->settings['enabled']) || 'yes' !== $this->settings['enabled']) {
            return false;
        }
        $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
        if (isset($gateways[$this->name]) && method_exists($gateways[$this->name], 'ccd_is_configured')) {
            return $gateways[$this->name]->ccd_is_configured();
        }
        return true;
    }

    /**
     * Register and return the script handle(s) for this payment method.
     *
     * The same bundled script powers all gateways; it is registered once
     * and reused.
     *
     * @return string[]
     */
    public function get_payment_method_script_handles()
    {
        $handle = 'ccd-blocks-integration';

        if (!wp_script_is($handle, 'registered')) {
            wp_register_script(
                $handle,
                CCD_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/js/blocks/ccd-blocks.js',
                array('wc-blocks-registry', 'wc-blocks-checkout', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n', 'wp-data'),
                CCD_PAYMENT_GATEWAY_VERSION,
                true
            );

            wp_add_inline_script(
                $handle,
                'window.ccdBlocks = ' . wp_json_encode(array(
                    'gateways' => array_keys(ccd_gateway_config()),
                    'proof'    => function_exists('ccd_proof_script_data') ? ccd_proof_script_data() : null,
                )) . ';',
                'before'
            );

            if (function_exists('wp_set_script_translations')) {
                wp_set_script_translations($handle, 'ccd-payment-gateway-domain');
            }
        }

        return array($handle);
    }

    /**
     * Data exposed to the client script as wcSettings('<name>_data').
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        $config = ccd_gateway_config();
        if (!isset($config[$this->name])) {
            return array();
        }
        $config = $config[$this->name];

        $title       = !empty($this->settings[$config['title_key']]) ? $this->settings[$config['title_key']] : $config['title_default'];
        $description = isset($this->settings[$config['desc_key']]) ? $this->settings[$config['desc_key']] : '';
        $account     = ccd_get_account_display($this->name);

        $fields = array();
        foreach ($config['fields'] as $field) {
            $fields[] = array(
                'name'        => $field['name'],
                'label'       => $field['label'],
                'placeholder' => $field['placeholder'],
                'type'        => $field['type'],
            );
        }

        return array(
            'title'          => wp_strip_all_tags($title),
            'description'    => wp_strip_all_tags($description),
            'accountRows'    => $account['rows'],
            'qrCode'         => $account['qr'],
            'chargeNote'     => ccd_get_charge_note($this->name),
            'discountNote'   => ccd_get_discount_note($this->name),
            'anyCharge'      => ccd_any_charge_enabled(),
            'bankAccounts'   => 'ccd_bank' === $this->name ? ccd_bank_picker_accounts() : array(),
            'bankI18n'       => 'ccd_bank' === $this->name ? ccd_bank_picker_i18n() : array(),
            'proofMode'      => function_exists('ccd_proof_mode') ? ccd_proof_mode($this->name) : 'off',
            'icon'           => CCD_PAYMENT_GATEWAY_PLUGIN_URL . $config['icon'],
            'fields'         => $fields,
            'advancePayment' => ccd_get_advance_payment_display(),
            'supports'       => $this->get_supported_features(),
        );
    }

    /**
     * Supported block features.
     *
     * @return string[]
     */
    public function get_supported_features()
    {
        return array('products');
    }
}
