<?php

/**
 * Config-driven base class for the gateways added in 1.5.0 (Upay, CellFin,
 * Bank Transfer, Bangla QR). Settings keys are "<gateway id>_<name>", and the
 * customer fields, labels and meta keys come from ccd_gateway_config().
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class CCD_Gateway_Base extends WC_Payment_Gateway
{
    /**
     * Entry from ccd_gateway_config().
     *
     * @var array
     */
    protected $ccd;

    public function __construct()
    {
        $config     = ccd_gateway_config();
        $this->ccd  = $config[$this->id];

        $this->method_title       = $this->ccd['label'];
        $this->method_description = $this->ccd_method_description();
        $this->has_fields         = true;
        $this->icon               = '';

        $this->init_form_fields();
        $this->form_fields = ccd_extend_form_fields($this->id, $this->form_fields);
        $this->init_settings();

        $this->title       = $this->get_option($this->id . '_title', $this->ccd['title_default']);
        $this->description = $this->get_option($this->id . '_description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'thankyou_text'), 10, 2);
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 15, 3);
    }

    /**
     * Admin description of the gateway.
     *
     * @return string
     */
    abstract protected function ccd_method_description();

    /**
     * Gateway-specific settings inserted after the common ones.
     *
     * @return array
     */
    abstract protected function ccd_account_fields();

    /**
     * Common settings.
     */
    public function init_form_fields()
    {
        $label = $this->ccd['label'];
        $fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'ccd-payment-gateway-domain'),
                'type'    => 'checkbox',
                /* translators: %s: payment method label */
                'label'   => sprintf(__('Enable %s', 'ccd-payment-gateway-domain'), $label),
                'default' => 'no',
            ),
            $this->id . '_title' => array(
                'title'   => __('Title', 'ccd-payment-gateway-domain'),
                'type'    => 'text',
                'default' => $this->ccd['title_default'],
            ),
            $this->id . '_description' => array(
                'title'    => __('Description', 'ccd-payment-gateway-domain'),
                'type'     => 'textarea',
                'default'  => __('Please complete the payment, then fill up this form to confirm it.', 'ccd-payment-gateway-domain'),
                'desc_tip' => true,
            ),
            $this->id . '_instructions' => array(
                'title'       => __('Thank you page message', 'ccd-payment-gateway-domain'),
                'type'        => 'textarea',
                'description' => __('Shown on the thank you page and in the customer order emails.', 'ccd-payment-gateway-domain'),
                /* translators: %s: payment method label */
                'default'     => sprintf(__('Thanks for paying with %s. We will verify your payment and update your order as soon as possible.', 'ccd-payment-gateway-domain'), $label),
                'desc_tip'    => true,
            ),
        );

        $fields = array_merge($fields, $this->ccd_account_fields());

        if (!empty($this->ccd['charge_key'])) {
            $fields[$this->ccd['charge_key']] = array(
                /* translators: %s: payment method label */
                'title'   => sprintf(__('Enable %s Charge', 'ccd-payment-gateway-domain'), $label),
                'type'    => 'checkbox',
                'default' => 'no',
            );
        }

        $fields[$this->id . '_order_status'] = array(
            'title'       => __('Order Status', 'ccd-payment-gateway-domain'),
            'type'        => 'select',
            'class'       => 'wc-enhanced-select',
            'description' => __('Order status after checkout, while you verify the payment.', 'ccd-payment-gateway-domain'),
            'default'     => 'wc-on-hold',
            'desc_tip'    => true,
            'options'     => wc_get_order_statuses(),
        );

        $this->form_fields = $fields;
    }

    /**
     * Whether the receiving details needed by customers are configured.
     *
     * @return bool
     */
    public function ccd_is_configured()
    {
        return '' !== trim((string) $this->get_option($this->ccd['number_key']));
    }

    /**
     * Hide the gateway at checkout until it is configured.
     *
     * @return bool
     */
    public function is_available()
    {
        return parent::is_available() && $this->ccd_is_configured();
    }

    /**
     * Receiving account details shown above the customer fields.
     */
    protected function ccd_render_receiving_details()
    {
        ccd_render_account_box($this->id);
    }

    /**
     * Checkout payment box.
     */
    public function payment_fields()
    {
        ccd_render_charge_note($this->id);
        ccd_render_advance_notice();

        if ($this->description) {
            echo wpautop(wptexturize(esc_html($this->description)));
        }

        $this->ccd_render_receiving_details();

        echo '<div class="payment_box_ccd_child"><table>';
        foreach ($this->ccd['fields'] as $field) {
            $mode = ('tel' === $field['type']) ? ' inputmode="numeric"' : '';
            printf(
                '<tr><td><label for="%1$s">%2$s <abbr class="required" title="%5$s">*</abbr></label></td><td><input class="widefat" type="%3$s" name="%1$s" id="%1$s" placeholder="%4$s" autocomplete="off"%6$s></td></tr>',
                esc_attr($field['name']),
                esc_html($field['label']),
                esc_attr('tel' === $field['type'] ? 'text' : $field['type']),
                esc_attr($field['placeholder']),
                esc_attr__('required', 'ccd-payment-gateway-domain'),
                $mode // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed string.
            );
        }
        echo '</table></div>';

        ccd_render_payment_proof_field($this->id);
    }

    /**
     * Process the payment: advance payment or configured status.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!ccd_apply_advance_payment($order, $this->ccd['label'])) {
            ccd_apply_order_status($order, $this->get_option($this->id . '_order_status', 'wc-on-hold'), $this->ccd['label']);
        }

        wc_reduce_stock_levels($order->get_id());
        WC()->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Thank you page message.
     *
     * @param string        $text  Current text.
     * @param WC_Order|null $order Order.
     * @return string
     */
    public function thankyou_text($text, $order = null)
    {
        $instructions = trim((string) $this->get_option($this->id . '_instructions'));
        if (is_a($order, 'WC_Order') && $order->get_payment_method() === $this->id && '' !== $instructions) {
            return wp_kses_post($instructions);
        }
        return $text;
    }

    /**
     * Customer email instructions.
     *
     * @param WC_Order $order         Order.
     * @param bool     $sent_to_admin Whether sent to admin.
     * @param bool     $plain_text    Plain text email.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        $instructions = trim((string) $this->get_option($this->id . '_instructions'));
        if ($sent_to_admin || '' === $instructions || !is_a($order, 'WC_Order') || $order->get_payment_method() !== $this->id) {
            return;
        }
        echo $plain_text ? esc_html(wp_strip_all_tags($instructions)) . PHP_EOL : wpautop(wptexturize(wp_kses_post($instructions))) . PHP_EOL;
    }
}

/**
 * Upay (UCB) mobile wallet.
 */
class CCD_Payment_Upay extends CCD_Gateway_Base
{
    public function __construct()
    {
        $this->id = 'ccd_upay';
        parent::__construct();
    }

    protected function ccd_method_description()
    {
        return __('Accept manual Upay (United Commercial Bank) payments. The customer sends money to your Upay number and submits their number and Transaction ID.', 'ccd-payment-gateway-domain');
    }

    protected function ccd_account_fields()
    {
        return array(
            'ccd_upay_number' => array(
                'title'       => __('Upay Number', 'ccd-payment-gateway-domain'),
                'type'        => 'text',
                'description' => __('Your 11-digit Upay mobile number shown to customers, e.g. 017XXXXXXXX.', 'ccd-payment-gateway-domain'),
                'desc_tip'    => true,
                'placeholder' => '017XXXXXXXX',
            ),
            'ccd_upay_account_type' => array(
                'title'   => __('Upay Account Type', 'ccd-payment-gateway-domain'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => array(
                    'Personal' => __('Personal', 'ccd-payment-gateway-domain'),
                    'Agent'    => __('Agent', 'ccd-payment-gateway-domain'),
                    'Merchant' => __('Merchant', 'ccd-payment-gateway-domain'),
                ),
                'default' => 'Personal',
            ),
        );
    }
}

/**
 * CellFin (Islami Bank Bangladesh) app transfers.
 */
class CCD_Payment_Cellfin extends CCD_Gateway_Base
{
    public function __construct()
    {
        $this->id = 'ccd_cellfin';
        parent::__construct();
    }

    protected function ccd_method_description()
    {
        return __('Accept manual payments from the CellFin app (Islami Bank Bangladesh). The customer sends money to your account or CellFin number and submits the transaction reference.', 'ccd-payment-gateway-domain');
    }

    protected function ccd_account_fields()
    {
        return array(
            'ccd_cellfin_number' => array(
                'title'       => __('Receiving Account or Mobile Number', 'ccd-payment-gateway-domain'),
                'type'        => 'text',
                'description' => __('Your Islami Bank account number or CellFin mobile number shown to customers.', 'ccd-payment-gateway-domain'),
                'desc_tip'    => true,
            ),
            'ccd_cellfin_account_type' => array(
                'title'   => __('Receiving Number Type', 'ccd-payment-gateway-domain'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => array(
                    'Account' => __('Bank account number', 'ccd-payment-gateway-domain'),
                    'Mobile'  => __('CellFin mobile number', 'ccd-payment-gateway-domain'),
                ),
                'default' => 'Account',
            ),
        );
    }
}

/**
 * Direct bank transfer (BEFTN / NPSB / RTGS) to a Bangladeshi bank account.
 */
class CCD_Payment_Bank extends CCD_Gateway_Base
{
    public function __construct()
    {
        $this->id = 'ccd_bank';
        parent::__construct();
    }

    protected function ccd_method_description()
    {
        return __('Accept manual bank transfers (BEFTN, NPSB or RTGS) to your bank account. The customer transfers the amount and submits the transfer reference.', 'ccd-payment-gateway-domain');
    }

    protected function ccd_account_fields()
    {
        return array(
            'ccd_bank_accounts' => array(
                'title'       => __('Bank Accounts', 'ccd-payment-gateway-domain'),
                'type'        => 'ccd_bank_accounts',
                'default'     => array(),
                'description' => __('Add one or more accounts. Account number is required; the routing number (9 digits) is needed for BEFTN/NPSB transfers from other banks. With more than one account, customers choose the account they paid into.', 'ccd-payment-gateway-domain'),
            ),
        );
    }

    /**
     * Configured when at least one account has an account number.
     *
     * @return bool
     */
    public function ccd_is_configured()
    {
        return !empty(ccd_bank_accounts());
    }

    /**
     * Accounts table on the settings screen.
     *
     * @param string $key  Field key.
     * @param array  $data Field config.
     * @return string
     */
    public function generate_ccd_bank_accounts_html($key, $data)
    {
        $accounts = $this->get_option($key, array());
        $accounts = is_array($accounts) && $accounts ? $accounts : array(array());
        $columns  = array(
            'bank_name'      => array(__('Bank Name', 'ccd-payment-gateway-domain'), 'Dutch-Bangla Bank PLC'),
            'account_name'   => array(__('Account Name', 'ccd-payment-gateway-domain'), ''),
            'account_number' => array(__('Account Number', 'ccd-payment-gateway-domain') . ' *', ''),
            'branch'         => array(__('Branch', 'ccd-payment-gateway-domain'), ''),
            'routing'        => array(__('Routing Number', 'ccd-payment-gateway-domain'), '090261234'),
        );
        $placeholders = array();
        foreach ($columns as $name => $column) {
            $placeholders[$name] = $column[1];
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php echo esc_html($data['title']); ?></th>
            <td class="forminp" id="ccd_bank_accounts">
                <div class="wc_input_table_wrapper">
                    <table class="widefat wc_input_table sortable" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="sort">&nbsp;</th>
                                <?php foreach ($columns as $column) : ?>
                                    <th><?php echo esc_html($column[0]); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="accounts">
                            <?php foreach (array_values($accounts) as $i => $account) : ?>
                                <tr class="account">
                                    <td class="sort"></td>
                                    <?php foreach ($columns as $name => $column) : ?>
                                        <td><input type="text" name="ccd_bank_accounts[<?php echo esc_attr($i); ?>][<?php echo esc_attr($name); ?>]" value="<?php echo esc_attr(isset($account[$name]) ? $account[$name] : ''); ?>" placeholder="<?php echo esc_attr($column[1]); ?>"></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6">
                                    <a href="#" class="add button"><?php esc_html_e('+ Add account', 'ccd-payment-gateway-domain'); ?></a>
                                    <a href="#" class="remove_rows button"><?php esc_html_e('Remove selected account(s)', 'ccd-payment-gateway-domain'); ?></a>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="description"><?php echo esc_html($data['description']); ?></p>
                <script>
                    jQuery(function ($) {
                        var $wrap = $('#ccd_bank_accounts');
                        var cols = <?php echo wp_json_encode($placeholders); ?>;
                        $wrap.on('click', 'a.add', function (e) {
                            e.preventDefault();
                            var size = $wrap.find('tbody .account').length;
                            var $row = $('<tr class="account"><td class="sort"></td></tr>');
                            $.each(cols, function (name, placeholder) {
                                $('<td></td>').append(
                                    $('<input type="text">').attr({ name: 'ccd_bank_accounts[' + size + '][' + name + ']', placeholder: placeholder })
                                ).appendTo($row);
                            });
                            $row.appendTo($wrap.find('tbody'));
                        });
                    });
                </script>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Read the accounts table from the submitted form. Empty rows are dropped;
     * detailed checks run in ccd_sanitize_gateway_settings().
     *
     * @param string $key   Field key.
     * @param mixed  $value Ignored (the table posts its own array).
     * @return array[]
     */
    public function validate_ccd_bank_accounts_field($key, $value)
    {
        // Nonce is verified by WooCommerce before gateway settings are saved.
        $rows     = isset($_POST['ccd_bank_accounts']) ? (array) wp_unslash($_POST['ccd_bank_accounts']) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $accounts = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $account = array();
            foreach (array('bank_name', 'account_name', 'account_number', 'branch', 'routing') as $name) {
                $account[$name] = isset($row[$name]) ? sanitize_text_field($row[$name]) : '';
            }
            if ('' !== implode('', $account)) {
                $accounts[] = $account;
            }
        }
        return $accounts;
    }

    /**
     * Receiving details plus, with several accounts, "which account did you pay into".
     */
    protected function ccd_render_receiving_details()
    {
        parent::ccd_render_receiving_details();

        $accounts = ccd_bank_picker_accounts();
        if (!$accounts) {
            return;
        }
        $i18n = ccd_bank_picker_i18n();

        // Searchable, paginated picker; ccd_scripts.js filters, pages and fills the hidden field.
        echo '<div class="ccd-bank-picker" data-page-size="5" data-i18n="' . esc_attr(wp_json_encode($i18n)) . '">';
        echo '<p class="ccd-bank-picker-title"><strong>' . esc_html($i18n['choose']) . '</strong> <abbr class="required" title="' . esc_attr__('required', 'ccd-payment-gateway-domain') . '">*</abbr></p>';
        echo '<input type="search" class="ccd-bank-search input-text" autocomplete="off" placeholder="' . esc_attr($i18n['search']) . '" aria-label="' . esc_attr($i18n['search']) . '">';
        echo '<div class="ccd-bank-list" role="listbox" aria-label="' . esc_attr($i18n['choose']) . '">';
        foreach ($accounts as $account) {
            echo '<button type="button" class="ccd-bank-option" role="option" aria-selected="false" data-index="' . esc_attr($account['index']) . '" data-search="' . esc_attr($account['search']) . '" data-account="' . esc_attr(wp_json_encode($account)) . '">';
            echo '<span class="ccd-bank-option-name">' . esc_html($account['bank_name'] ? $account['bank_name'] : $account['label']) . '</span>';
            echo '<span class="ccd-bank-option-meta">' . esc_html(trim($account['branch'] . ' · A/C ' . $account['account_number'], ' ·')) . '</span>';
            echo '</button>';
        }
        echo '</div>';
        echo '<p class="ccd-bank-empty" hidden>' . esc_html($i18n['none']) . '</p>';
        echo '<p class="ccd-bank-footer"><small class="ccd-bank-count"></small> <button type="button" class="ccd-bank-more button">' . esc_html($i18n['more']) . '</button></p>';
        echo '<div class="ccd-bank-selected" hidden></div>';
        echo '<input type="hidden" name="ccd_bank_paid_to" class="ccd-bank-paid-to" value="">';
        echo '</div>';
    }
}

/**
 * Bangla QR: Bangladesh Bank's interoperable QR, payable from any bank or MFS app.
 */
class CCD_Payment_BanglaQR extends CCD_Gateway_Base
{
    public function __construct()
    {
        $this->id = 'ccd_banglaqr';
        parent::__construct();
    }

    protected function ccd_method_description()
    {
        return __('Show your Bangla QR code at checkout. Customers can scan and pay from any bank or mobile banking app (bKash, Nagad, Rocket, Upay, bank apps), then submit the transaction ID.', 'ccd-payment-gateway-domain');
    }

    protected function ccd_account_fields()
    {
        return array(
            'ccd_banglaqr_merchant_name' => array(
                'title'       => __('Merchant Name', 'ccd-payment-gateway-domain'),
                'type'        => 'text',
                'description' => __('The merchant name customers see in their app after scanning, so they can confirm they are paying you.', 'ccd-payment-gateway-domain'),
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Bangla QR needs the QR image; the merchant name is optional.
     *
     * @return bool
     */
    public function ccd_is_configured()
    {
        return '' !== trim((string) $this->get_option($this->id . '_qr_code'));
    }
}
