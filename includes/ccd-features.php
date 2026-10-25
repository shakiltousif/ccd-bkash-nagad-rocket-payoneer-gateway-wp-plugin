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
 * Per-gateway configuration: the single source of truth for labels, settings
 * keys, customer fields, validation rules and order meta keys.
 *
 * The bKash, Nagad, Rocket and Payoneer option keys, POST field names and meta
 * keys are kept exactly as released in earlier versions so existing settings
 * and orders keep working.
 *
 * Field rules: mobile, rocket (11-digit mobile or 12-digit Rocket account),
 * mobile_or_account, email, trx (transaction ID), ref (bank reference), text.
 *
 * @return array
 */
function ccd_gateway_config()
{
    static $config = null;
    if (null !== $config) {
        return $config;
    }

    $mobile_placeholder = 'Ex. 017XXXXXXXX';
    $trx_placeholder    = 'Ex. 8N7A6D5EE7M';

    $config = array(
        'ccd_bkash' => array(
            'label'         => 'bKash',
            'class'         => 'CCD_Payment_Bkash',
            'icon'          => 'assets/img/bkash.png',
            'title_key'     => 'bkash_title',
            'title_default' => 'Payment With bKash',
            'desc_key'      => 'bkash_description',
            'number_key'    => 'bkash_number',
            'type_key'      => 'bkash_account_type',
            'charge_key'    => 'bkash_charge',
            'charge_rate'   => 1.85,
            'fields'        => array(
                array('name' => 'bkash_number', 'meta' => '_bkash_number', 'label' => __('bKash Number', 'ccd-payment-gateway-domain'), 'placeholder' => $mobile_placeholder, 'type' => 'tel', 'rule' => 'mobile'),
                array('name' => 'bkash_transaction_id', 'meta' => '_bkash_transaction', 'label' => __('bKash Transaction ID', 'ccd-payment-gateway-domain'), 'placeholder' => $trx_placeholder, 'type' => 'text', 'rule' => 'trx'),
            ),
        ),
        'ccd_nagad' => array(
            'label'         => 'Nagad',
            'class'         => 'CCD_Payment_Nagad',
            'icon'          => 'assets/img/nagad.png',
            'title_key'     => 'ccd_nagad_title',
            'title_default' => 'Payment With Nagad',
            'desc_key'      => 'ccd_nagad_description',
            'number_key'    => 'ccd_nagad_number',
            'type_key'      => 'ccd_nagad_account_type',
            'charge_key'    => 'ccd_nagad_charge',
            'charge_rate'   => 1.45,
            'fields'        => array(
                array('name' => 'ccd_nagad_number', 'meta' => '_ccd_nagad_number', 'label' => __('Nagad Number', 'ccd-payment-gateway-domain'), 'placeholder' => $mobile_placeholder, 'type' => 'tel', 'rule' => 'mobile'),
                array('name' => 'ccd_nagad_transaction_id', 'meta' => '_ccd_nagad_transaction', 'label' => __('Nagad Transaction ID', 'ccd-payment-gateway-domain'), 'placeholder' => 'Ex. 7AB3CD9E', 'type' => 'text', 'rule' => 'trx'),
            ),
        ),
        'ccd_rocket' => array(
            'label'         => 'Rocket',
            'class'         => 'CCD_Payment_Rocket',
            'icon'          => 'assets/img/rocket.png',
            'title_key'     => 'ccd_rocket_title',
            'title_default' => 'Payment With Rocket',
            'desc_key'      => 'ccd_rocket_description',
            'number_key'    => 'ccd_rocket_number',
            'type_key'      => 'ccd_rocket_account_type',
            'charge_key'    => 'ccd_rocket_charge',
            'charge_rate'   => 1.8,
            'fields'        => array(
                array('name' => 'ccd_rocket_number', 'meta' => '_ccd_rocket_number', 'label' => __('Rocket Number', 'ccd-payment-gateway-domain'), 'placeholder' => 'Ex. 017XXXXXXXX or 017XXXXXXXXX', 'type' => 'tel', 'rule' => 'rocket'),
                array('name' => 'ccd_rocket_transaction_id', 'meta' => '_ccd_rocket_transaction', 'label' => __('Rocket Transaction ID', 'ccd-payment-gateway-domain'), 'placeholder' => 'Ex. 1234567890', 'type' => 'text', 'rule' => 'trx'),
            ),
        ),
        'ccd_payoneer' => array(
            'label'         => 'Payoneer',
            'class'         => 'CCD_Payment_Payoneer',
            'icon'          => 'assets/img/payoneer.png',
            'title_key'     => 'ccd_payoneer_title',
            'title_default' => 'Payment With Payoneer',
            'desc_key'      => 'ccd_payoneer_description',
            'number_key'    => 'ccd_payoneer_recipient_email',
            'type_key'      => '',
            'charge_key'    => '',
            'charge_rate'   => 0,
            'fields'        => array(
                array('name' => 'ccd_payoneeer_sender_email', 'meta' => '_ccd_payoneeer_sender_email', 'label' => __('Payoneer Email', 'ccd-payment-gateway-domain'), 'placeholder' => 'Ex. example@example.com', 'type' => 'email', 'rule' => 'email'),
                array('name' => 'ccd_payoneer_transaction_id', 'meta' => '_ccd_payoneer_transaction_id', 'label' => __('Transaction ID', 'ccd-payment-gateway-domain'), 'placeholder' => $trx_placeholder, 'type' => 'text', 'rule' => 'trx'),
            ),
        ),
        'ccd_upay' => array(
            'label'         => 'Upay',
            'class'         => 'CCD_Payment_Upay',
            'icon'          => 'assets/img/upay.png',
            'title_key'     => 'ccd_upay_title',
            'title_default' => 'Payment With Upay',
            'desc_key'      => 'ccd_upay_description',
            'number_key'    => 'ccd_upay_number',
            'type_key'      => 'ccd_upay_account_type',
            'charge_key'    => 'ccd_upay_charge',
            'charge_rate'   => 1.4,
            'fields'        => array(
                array('name' => 'ccd_upay_number', 'meta' => '_ccd_upay_number', 'label' => __('Upay Number', 'ccd-payment-gateway-domain'), 'placeholder' => $mobile_placeholder, 'type' => 'tel', 'rule' => 'mobile'),
                array('name' => 'ccd_upay_transaction_id', 'meta' => '_ccd_upay_transaction', 'label' => __('Upay Transaction ID', 'ccd-payment-gateway-domain'), 'placeholder' => $trx_placeholder, 'type' => 'text', 'rule' => 'trx'),
            ),
        ),
        'ccd_cellfin' => array(
            'label'         => 'CellFin',
            'class'         => 'CCD_Payment_Cellfin',
            'icon'          => 'assets/img/cellfin.png',
            'title_key'     => 'ccd_cellfin_title',
            'title_default' => 'Payment With CellFin (Islami Bank)',
            'desc_key'      => 'ccd_cellfin_description',
            'number_key'    => 'ccd_cellfin_number',
            'type_key'      => 'ccd_cellfin_account_type',
            'charge_key'    => 'ccd_cellfin_charge',
            'charge_rate'   => 0,
            'fields'        => array(
                array('name' => 'ccd_cellfin_number', 'meta' => '_ccd_cellfin_number', 'label' => __('Your CellFin Mobile or Account Number', 'ccd-payment-gateway-domain'), 'placeholder' => $mobile_placeholder, 'type' => 'tel', 'rule' => 'mobile_or_account'),
                array('name' => 'ccd_cellfin_transaction_id', 'meta' => '_ccd_cellfin_transaction', 'label' => __('CellFin Transaction ID / Reference', 'ccd-payment-gateway-domain'), 'placeholder' => $trx_placeholder, 'type' => 'text', 'rule' => 'ref'),
            ),
        ),
        'ccd_bank' => array(
            'label'         => 'Bank Transfer',
            'class'         => 'CCD_Payment_Bank',
            'icon'          => 'assets/img/bank.svg',
            'title_key'     => 'ccd_bank_title',
            'title_default' => 'Bank Transfer (BEFTN / NPSB)',
            'desc_key'      => 'ccd_bank_description',
            'number_key'    => 'ccd_bank_accounts',
            'type_key'      => '',
            'charge_key'    => 'ccd_bank_charge',
            'charge_rate'   => 0,
            'fields'        => array(
                array('name' => 'ccd_bank_sender_account', 'meta' => '_ccd_bank_sender_account', 'label' => __('Your Bank Account Name or Number', 'ccd-payment-gateway-domain'), 'placeholder' => 'Ex. Rahim Uddin / 1234567890', 'type' => 'text', 'rule' => 'text'),
                array('name' => 'ccd_bank_transaction_id', 'meta' => '_ccd_bank_transaction', 'label' => __('Transfer Reference / Transaction ID', 'ccd-payment-gateway-domain'), 'placeholder' => 'Ex. FT26298XXXXX', 'type' => 'text', 'rule' => 'ref'),
            ),
        ),
        'ccd_banglaqr' => array(
            'label'         => 'Bangla QR',
            'class'         => 'CCD_Payment_BanglaQR',
            'icon'          => 'assets/img/banglaqr.png',
            'title_key'     => 'ccd_banglaqr_title',
            'title_default' => 'Pay with Bangla QR (any bank or MFS app)',
            'desc_key'      => 'ccd_banglaqr_description',
            'number_key'    => 'ccd_banglaqr_merchant_name',
            'type_key'      => '',
            'charge_key'    => 'ccd_banglaqr_charge',
            'charge_rate'   => 0,
            'fields'        => array(
                array('name' => 'ccd_banglaqr_payer', 'meta' => '_ccd_banglaqr_payer', 'label' => __('Mobile or Account Number You Paid From', 'ccd-payment-gateway-domain'), 'placeholder' => $mobile_placeholder, 'type' => 'tel', 'rule' => 'mobile_or_account'),
                array('name' => 'ccd_banglaqr_transaction_id', 'meta' => '_ccd_banglaqr_transaction', 'label' => __('Transaction ID', 'ccd-payment-gateway-domain'), 'placeholder' => $trx_placeholder, 'type' => 'text', 'rule' => 'trx'),
            ),
        ),
    );

    return $config;
}

/**
 * Transaction ID field name of a gateway.
 *
 * @param string $gateway_id Gateway id.
 * @return string
 */
function ccd_trx_field($gateway_id)
{
    $config = ccd_gateway_config();
    return isset($config[$gateway_id]['fields'][1]['name']) ? $config[$gateway_id]['fields'][1]['name'] : '';
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

    // Merchant accounts ("Make Payment") in addition to Agent / Personal.
    if (!empty($config['type_key']) && isset($fields[$config['type_key']]['options']) && !isset($fields[$config['type_key']]['options']['Merchant'])) {
        $fields[$config['type_key']]['options']['Merchant'] = __('Merchant', 'ccd-payment-gateway-domain');
    }

    $fields['ccd_section_more'] = array(
        'title'       => __('Extra options', 'ccd-payment-gateway-domain'),
        'type'        => 'title',
        'description' => '',
    );

    $fields[$gateway_id . '_payment_proof'] = array(
        'title'       => __('Payment Screenshot', 'ccd-payment-gateway-domain'),
        'type'        => 'select',
        'class'       => 'wc-enhanced-select',
        'options'     => array(
            'off'      => __('Off', 'ccd-payment-gateway-domain'),
            'optional' => __('Optional - customer may upload a screenshot or PDF', 'ccd-payment-gateway-domain'),
            'required' => __('Required - customer must upload a screenshot or PDF', 'ccd-payment-gateway-domain'),
        ),
        'default'     => 'off',
        'description' => sprintf(
            /* translators: %s: maximum file size */
            __('Let customers attach a payment screenshot (JPG, PNG, WebP or PDF, max %s) at checkout, in addition to the transaction ID. Files are stored privately and only shop managers can view them.', 'ccd-payment-gateway-domain'),
            size_format(min(5 * MB_IN_BYTES, wp_max_upload_size()))
        ),
    );

    $fields[$gateway_id . '_discount_type'] = array(
        'title'   => __('Prepayment Discount Type', 'ccd-payment-gateway-domain'),
        'type'    => 'select',
        'class'   => 'wc-enhanced-select',
        'options' => array(
            'percent' => __('Percentage of order total', 'ccd-payment-gateway-domain'),
            'fixed'   => __('Fixed amount', 'ccd-payment-gateway-domain'),
        ),
        'default' => 'percent',
    );
    $fields[$gateway_id . '_discount_amount'] = array(
        'title'             => __('Prepayment Discount', 'ccd-payment-gateway-domain'),
        'type'              => 'number',
        'default'           => '',
        'placeholder'       => '0',
        'custom_attributes' => array('step' => '0.01', 'min' => '0'),
        'description'       => sprintf(
            /* translators: %s: payment method label */
            __('Optional discount for customers who pay with %s, to encourage prepayment over cash on delivery. Leave empty or 0 for no discount.', 'ccd-payment-gateway-domain'),
            $config['label']
        ),
    );

    $fields[$gateway_id . '_min_amount'] = array(
        'title'             => __('Minimum Order Amount', 'ccd-payment-gateway-domain'),
        'type'              => 'number',
        'default'           => '',
        'placeholder'       => __('No minimum', 'ccd-payment-gateway-domain'),
        'custom_attributes' => array('step' => '0.01', 'min' => '0'),
        'description'       => __('Hide this payment method when the order amount (products after coupons, before shipping) is below this value. Leave empty for no limit.', 'ccd-payment-gateway-domain'),
    );
    $fields[$gateway_id . '_max_amount'] = array(
        'title'             => __('Maximum Order Amount', 'ccd-payment-gateway-domain'),
        'type'              => 'number',
        'default'           => '',
        'placeholder'       => __('No maximum', 'ccd-payment-gateway-domain'),
        'custom_attributes' => array('step' => '0.01', 'min' => '0'),
        'description'       => __('Hide this payment method when the order amount is above this value, e.g. your wallet\'s daily send money limit. Leave empty for no limit.', 'ccd-payment-gateway-domain'),
    );

    return $fields;
}

/**
 * Saved bank accounts of the Bank Transfer gateway (only those with an account number).
 *
 * @return array[] Each: bank_name, account_name, account_number, branch, routing.
 */
function ccd_bank_accounts()
{
    $settings = ccd_gateway_settings('ccd_bank');
    $accounts = isset($settings['ccd_bank_accounts']) && is_array($settings['ccd_bank_accounts']) ? $settings['ccd_bank_accounts'] : array();
    return array_values(array_filter($accounts, function ($account) {
        return is_array($account) && !empty($account['account_number']);
    }));
}

/**
 * Accounts for the checkout picker (only when there is more than one).
 *
 * Each item: index, bank_name, account_name, account_number, branch,
 * routing, label and a lowercase search string.
 *
 * @return array[]
 */
function ccd_bank_picker_accounts()
{
    $accounts = ccd_bank_accounts();
    if (count($accounts) < 2) {
        return array();
    }
    $items = array();
    foreach ($accounts as $i => $account) {
        $items[] = array(
            'index'          => $i,
            'bank_name'      => (string) $account['bank_name'],
            'account_name'   => (string) $account['account_name'],
            'account_number' => (string) $account['account_number'],
            'branch'         => (string) $account['branch'],
            'routing'        => (string) $account['routing'],
            'label'          => ccd_bank_account_label($account),
            'search'         => function_exists('mb_strtolower')
                ? mb_strtolower(implode(' ', array($account['bank_name'], $account['account_name'], $account['account_number'], $account['branch'], $account['routing'])))
                : strtolower(implode(' ', array($account['bank_name'], $account['account_name'], $account['account_number'], $account['branch'], $account['routing']))),
        );
    }
    return $items;
}

/**
 * Translated strings for the bank account picker.
 *
 * @return array
 */
function ccd_bank_picker_i18n()
{
    return array(
        'choose'      => __('Choose the account you paid into', 'ccd-payment-gateway-domain'),
        'search'      => __('Search bank, branch, account name or number', 'ccd-payment-gateway-domain'),
        'more'        => __('Show more accounts', 'ccd-payment-gateway-domain'),
        'none'        => __('No account matches your search.', 'ccd-payment-gateway-domain'),
        'change'      => __('Change account', 'ccd-payment-gateway-domain'),
        'bank'        => __('Bank :', 'ccd-payment-gateway-domain'),
        'accountName' => __('Account Name :', 'ccd-payment-gateway-domain'),
        'number'      => __('Account Number :', 'ccd-payment-gateway-domain'),
        'branch'      => __('Branch :', 'ccd-payment-gateway-domain'),
        'routing'     => __('Routing Number :', 'ccd-payment-gateway-domain'),
        'copy'        => __('Copy', 'ccd-payment-gateway-domain'),
        'copied'      => __('Copied!', 'ccd-payment-gateway-domain'),
        /* translators: 1: shown count, 2: total count */
        'count'       => __('Showing %1$s of %2$s', 'ccd-payment-gateway-domain'),
    );
}

/**
 * Short label for a bank account, e.g. "Dutch-Bangla Bank PLC - A/C 1234567890123".
 *
 * @param array $account Account.
 * @return string
 */
function ccd_bank_account_label($account)
{
    $bank = !empty($account['bank_name']) ? $account['bank_name'] : __('Bank account', 'ccd-payment-gateway-domain');
    /* translators: 1: bank name, 2: account number */
    return sprintf(__('%1$s - A/C %2$s', 'ccd-payment-gateway-domain'), $bank, $account['account_number']);
}

/**
 * Validate the "account you paid into" choice of Bank Transfer.
 *
 * @param array $data Submitted values.
 * @return string[] Errors.
 */
function ccd_bank_paid_to_errors($data)
{
    $accounts = ccd_bank_accounts();
    if (count($accounts) < 2) {
        return array();
    }
    $choice = isset($data['ccd_bank_paid_to']) ? (string) $data['ccd_bank_paid_to'] : '';
    if ('' === $choice || !ctype_digit($choice) || !isset($accounts[(int) $choice])) {
        return array(__('Please select the bank account you paid into.', 'ccd-payment-gateway-domain'));
    }
    return array();
}

/**
 * Label of the account the customer paid into (single account: that account).
 *
 * @param array $data Submitted values.
 * @return string
 */
function ccd_bank_paid_to_label($data)
{
    $accounts = ccd_bank_accounts();
    if (1 === count($accounts)) {
        return ccd_bank_account_label($accounts[0]);
    }
    $choice = isset($data['ccd_bank_paid_to']) ? (string) $data['ccd_bank_paid_to'] : '';
    return ('' !== $choice && ctype_digit($choice) && isset($accounts[(int) $choice])) ? ccd_bank_account_label($accounts[(int) $choice]) : '';
}

//#################################
// INPUT NORMALISATION AND VALIDATION
//#################################

/**
 * Convert Bangla digits (০-৯) to ASCII digits.
 *
 * @param string $value Input.
 * @return string
 */
function ccd_ascii_digits($value)
{
    return strtr((string) $value, array(
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ));
}

/**
 * Normalise a Bangladeshi mobile / account number: Bangla digits to ASCII,
 * drop spaces, dashes and dots, and turn +880 / 880 into a leading 0.
 *
 * @param string $value Input.
 * @return string
 */
function ccd_normalize_number($value)
{
    $value = preg_replace('/[^0-9+]/', '', ccd_ascii_digits(trim((string) $value)));
    $value = ltrim($value, '+');
    if (13 <= strlen($value) && 0 === strpos($value, '880')) {
        $value = substr($value, 2);
    }
    return $value;
}

/**
 * Normalise a submitted field according to its rule.
 *
 * @param string $rule  Field rule.
 * @param string $value Raw value.
 * @return string
 */
function ccd_normalize_field($rule, $value)
{
    $value = trim(sanitize_text_field((string) $value));

    switch ($rule) {
        case 'mobile':
        case 'rocket':
        case 'mobile_or_account':
            return ccd_normalize_number($value);
        case 'email':
            return sanitize_email($value);
        case 'trx':
            return preg_replace('/\s+/', '', ccd_ascii_digits($value));
        case 'ref':
            return preg_replace('/\s+/', ' ', ccd_ascii_digits($value));
        default:
            return $value;
    }
}

/**
 * Validate one normalised field value.
 *
 * @param string $rule  Field rule.
 * @param string $value Normalised value.
 * @return bool
 */
function ccd_is_valid_field($rule, $value)
{
    switch ($rule) {
        case 'mobile':
            return (bool) preg_match('/^01[3-9][0-9]{8}$/', $value);
        case 'rocket':
            // 11-digit mobile number, or 12-digit Rocket account (mobile number + check digit).
            return (bool) preg_match('/^01[3-9][0-9]{8,9}$/', $value);
        case 'mobile_or_account':
            return (bool) preg_match('/^01[3-9][0-9]{8}$/', $value) || (bool) preg_match('/^[0-9]{10,20}$/', $value);
        case 'email':
            return (bool) is_email($value);
        case 'trx':
            return (bool) preg_match('/^[A-Za-z0-9._\/-]{5,40}$/', $value);
        case 'ref':
            return (bool) preg_match('/^[A-Za-z0-9 ._\/#-]{4,60}$/', $value);
        case 'text':
            $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            return $length >= 2 && $length <= 100;
        default:
            return '' !== $value;
    }
}

/**
 * Error message for an invalid field.
 *
 * @param array  $field   Field config.
 * @param string $label   Gateway label.
 * @param bool   $missing Whether the value is empty.
 * @return string
 */
function ccd_field_error($field, $label, $missing)
{
    if ($missing) {
        /* translators: %s: field label, e.g. "bKash Number" */
        return sprintf(__('Please enter %s.', 'ccd-payment-gateway-domain'), $field['label']);
    }

    switch ($field['rule']) {
        case 'mobile':
            /* translators: %s: payment method label */
            return sprintf(__('Please enter a valid 11-digit %s mobile number, e.g. 017XXXXXXXX.', 'ccd-payment-gateway-domain'), $label);
        case 'rocket':
            return __('Please enter a valid Rocket number: your 11-digit mobile number or 12-digit Rocket account number.', 'ccd-payment-gateway-domain');
        case 'mobile_or_account':
            return __('Please enter a valid mobile number (017XXXXXXXX) or account number (10-20 digits).', 'ccd-payment-gateway-domain');
        case 'email':
            /* translators: %s: payment method label */
            return sprintf(__('Please enter a valid %s email address.', 'ccd-payment-gateway-domain'), $label);
        case 'trx':
            /* translators: %s: payment method label */
            return sprintf(__('Please enter a valid %s transaction ID (5-40 letters or numbers, as shown in your app or SMS).', 'ccd-payment-gateway-domain'), $label);
        case 'ref':
            return __('Please enter a valid transaction reference (at least 4 characters).', 'ccd-payment-gateway-domain');
        default:
            /* translators: %s: field label */
            return sprintf(__('Please enter a valid %s.', 'ccd-payment-gateway-domain'), $field['label']);
    }
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
        if (ccd_get_charge_settings($gateway_id) || ccd_get_discount_settings($gateway_id)) {
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
    $config     = ccd_gateway_config();
    if (!$gateway_id || !isset($config[$gateway_id])) {
        return;
    }

    $label    = $config[$gateway_id]['label'];
    $decimals = wc_get_price_decimals();
    $base     = (float) $cart->get_cart_contents_total();

    // Prepayment discount first, so the charge applies to the discounted amount.
    $discount = ccd_get_discount_settings($gateway_id);
    if ($discount && $base > 0) {
        $amount = 'fixed' === $discount['type'] ? $discount['amount'] : $base * $discount['amount'] / 100;
        $amount = round(min($amount, $base), $decimals);
        if ($amount > 0) {
            $cart->add_fee(
                /* translators: %s: payment method label */
                sprintf(__('%s Discount', 'ccd-payment-gateway-domain'), $label),
                -$amount,
                false
            );
            $base -= $amount;
        }
    }

    $charge = ccd_get_charge_settings($gateway_id);
    if (!$charge) {
        return;
    }

    $surcharge = 'fixed' === $charge['type']
        ? $charge['amount']
        : $base * $charge['amount'] / 100;
    $surcharge = round($surcharge, $decimals);

    if ($surcharge <= 0) {
        return;
    }

    $cart->add_fee(
        /* translators: %s: payment method label */
        sprintf(__('%s Charge', 'ccd-payment-gateway-domain'), $label),
        $surcharge,
        true,
        ''
    );
}

/**
 * Prepayment discount settings of a gateway, or null when none.
 *
 * @param string $gateway_id Gateway id.
 * @return array{type:string,amount:float}|null
 */
function ccd_get_discount_settings($gateway_id)
{
    $settings = ccd_gateway_settings($gateway_id);
    $amount   = isset($settings[$gateway_id . '_discount_amount']) ? (float) $settings[$gateway_id . '_discount_amount'] : 0;
    if ($amount <= 0) {
        return null;
    }
    $type = (isset($settings[$gateway_id . '_discount_type']) && 'fixed' === $settings[$gateway_id . '_discount_type']) ? 'fixed' : 'percent';
    return array('type' => $type, 'amount' => $amount);
}

/**
 * Customer-facing discount note.
 *
 * @param string $gateway_id Gateway id.
 * @return string
 */
function ccd_get_discount_note($gateway_id)
{
    $discount = ccd_get_discount_settings($gateway_id);
    if (!$discount) {
        return '';
    }
    $config = ccd_gateway_config();
    $amount = 'fixed' === $discount['type']
        ? html_entity_decode(wp_strip_all_tags(wc_price($discount['amount'])), ENT_QUOTES, 'UTF-8')
        : wc_format_localized_decimal($discount['amount']) . '%';

    return sprintf(
        /* translators: 1: discount (e.g. 5% or ৳50), 2: payment method label */
        __('Pay with %2$s and get %1$s off.', 'ccd-payment-gateway-domain'),
        $amount,
        $config[$gateway_id]['label']
    );
}

//#################################
// MINIMUM / MAXIMUM ORDER AMOUNT PER GATEWAY
//#################################

add_filter('woocommerce_available_payment_gateways', 'ccd_filter_gateways_by_amount');
/**
 * Hide gateways when the cart amount is outside their min/max range.
 *
 * Applies to the classic checkout and the Store API (block checkout).
 * The amount is the product total after coupons, before shipping and fees,
 * so our own charge/discount never changes which gateways are offered.
 *
 * @param array $gateways Available gateways.
 * @return array
 */
function ccd_filter_gateways_by_amount($gateways)
{
    if (!is_array($gateways) || !function_exists('WC') || !WC()->cart || is_admin() && !wp_doing_ajax() && !(defined('REST_REQUEST') && REST_REQUEST)) {
        return $gateways;
    }

    $amount = (float) WC()->cart->get_cart_contents_total() + (float) WC()->cart->get_cart_contents_tax();

    foreach (array_keys(ccd_gateway_config()) as $gateway_id) {
        if (!isset($gateways[$gateway_id])) {
            continue;
        }
        $settings = ccd_gateway_settings($gateway_id);
        $min      = isset($settings[$gateway_id . '_min_amount']) ? (float) $settings[$gateway_id . '_min_amount'] : 0;
        $max      = isset($settings[$gateway_id . '_max_amount']) ? (float) $settings[$gateway_id . '_max_amount'] : 0;

        if (($min > 0 && $amount < $min) || ($max > 0 && $amount > $max)) {
            unset($gateways[$gateway_id]);
        }
    }

    return $gateways;
}

//#################################
// SETTINGS VALIDATION (prevent misconfiguration)
//#################################

add_action('woocommerce_init', 'ccd_register_settings_sanitizers');
/**
 * Hook the sanitizer for every gateway's settings save.
 */
function ccd_register_settings_sanitizers()
{
    foreach (array_keys(ccd_gateway_config()) as $gateway_id) {
        add_filter('woocommerce_settings_api_sanitized_fields_' . $gateway_id, function ($settings) use ($gateway_id) {
            return ccd_sanitize_gateway_settings($gateway_id, $settings);
        });
    }
}

/**
 * Validate gateway settings on save. Invalid values are corrected or cleared
 * and the admin sees an error explaining what to fix.
 *
 * @param string $gateway_id Gateway id.
 * @param array  $settings   Sanitized settings about to be saved.
 * @return array
 */
function ccd_sanitize_gateway_settings($gateway_id, $settings)
{
    $config = ccd_gateway_config();
    if (!isset($config[$gateway_id]) || !is_array($settings)) {
        return $settings;
    }
    $label  = $config[$gateway_id]['label'];
    $errors = array();
    $enabled = isset($settings['enabled']) && 'yes' === $settings['enabled'];

    // Receiving number / email. An invalid value is not saved; the previous one is kept.
    $number_key = $config[$gateway_id]['number_key'];
    $previous   = ccd_gateway_settings($gateway_id);
    $previous   = isset($previous[$number_key]) && is_string($previous[$number_key]) ? $previous[$number_key] : '';
    if ('ccd_bank' === $gateway_id) {
        $clean = array();
        $rows  = isset($settings['ccd_bank_accounts']) && is_array($settings['ccd_bank_accounts']) ? $settings['ccd_bank_accounts'] : array();
        foreach (array_values($rows) as $i => $account) {
            $row    = $i + 1;
            $number = ccd_normalize_number(isset($account['account_number']) ? $account['account_number'] : '');
            if ('' === $number) {
                /* translators: %d: row number */
                $errors[] = sprintf(__('Bank account %d has no account number and was removed.', 'ccd-payment-gateway-domain'), $row);
                continue;
            }
            if (!preg_match('/^[0-9]{10,20}$/', $number)) {
                /* translators: %d: row number */
                $errors[] = sprintf(__('Bank account %d: account number must be 10-20 digits. This account was not saved.', 'ccd-payment-gateway-domain'), $row);
                continue;
            }
            $routing = preg_replace('/\D/', '', ccd_ascii_digits(isset($account['routing']) ? $account['routing'] : ''));
            if ('' !== $routing && 9 !== strlen($routing)) {
                /* translators: %d: row number */
                $errors[] = sprintf(__('Bank account %d: routing number must be exactly 9 digits. It was cleared.', 'ccd-payment-gateway-domain'), $row);
                $routing  = '';
            }
            $clean[] = array(
                'bank_name'      => isset($account['bank_name']) ? $account['bank_name'] : '',
                'account_name'   => isset($account['account_name']) ? $account['account_name'] : '',
                'account_number' => $number,
                'branch'         => isset($account['branch']) ? $account['branch'] : '',
                'routing'        => $routing,
            );
        }
        $settings['ccd_bank_accounts'] = $clean;
        if ($enabled && !$clean) {
            $errors[] = __('Bank Transfer is enabled but has no bank account. It stays hidden at checkout until you add one.', 'ccd-payment-gateway-domain');
        }
    } elseif (isset($settings[$number_key]) && '' !== trim($settings[$number_key])) {
        $value        = trim($settings[$number_key]);
        $error_before = count($errors);
        if (in_array($gateway_id, array('ccd_bkash', 'ccd_nagad', 'ccd_upay'), true)) {
            $value = ccd_normalize_number($value);
            if (!ccd_is_valid_field('mobile', $value)) {
                /* translators: %s: payment method label */
                $errors[] = sprintf(__('%s number must be an 11-digit mobile number like 017XXXXXXXX.', 'ccd-payment-gateway-domain'), $label);
            }
        } elseif ('ccd_rocket' === $gateway_id) {
            $value = ccd_normalize_number($value);
            if (!ccd_is_valid_field('rocket', $value)) {
                $errors[] = __('Rocket number must be your 11-digit mobile number or 12-digit Rocket account number.', 'ccd-payment-gateway-domain');
            }
        } elseif ('ccd_cellfin' === $gateway_id) {
            $value = ccd_normalize_number($value);
            if (!ccd_is_valid_field('mobile_or_account', $value)) {
                /* translators: %s: payment method label */
                $errors[] = sprintf(__('%s account number must contain 10-20 digits (or an 11-digit mobile number).', 'ccd-payment-gateway-domain'), $label);
            }
        } elseif ('ccd_payoneer' === $gateway_id && !is_email($value)) {
            $errors[] = __('Payoneer recipient email is not a valid email address.', 'ccd-payment-gateway-domain');
        }
        if (count($errors) > $error_before) {
            $errors[] = '' !== $previous
                /* translators: %s: previous value */
                ? sprintf(__('The previous value (%s) was kept.', 'ccd-payment-gateway-domain'), $previous)
                : __('The value was not saved.', 'ccd-payment-gateway-domain');
            $value = $previous;
        }
        $settings[$number_key] = $value;
    } elseif ($enabled && 'ccd_banglaqr' !== $gateway_id) {
        /* translators: %s: payment method label */
        $errors[] = sprintf(__('%s is enabled but has no receiving number/account. Customers will not know where to send money.', 'ccd-payment-gateway-domain'), $label);
    }

    // QR code must be an image URL.
    $qr_key = $gateway_id . '_qr_code';
    if (!empty($settings[$qr_key])) {
        $url = esc_url_raw(trim($settings[$qr_key]));
        if (!$url || !preg_match('/\.(png|jpe?g|webp|gif|svg)(\?.*)?$/i', $url)) {
            $errors[] = __('QR code must be an image URL (PNG, JPG, WebP, GIF or SVG). Use the "Select image" button.', 'ccd-payment-gateway-domain');
            $url = '';
        }
        $settings[$qr_key] = $url;
    }
    if ('ccd_banglaqr' === $gateway_id && $enabled && empty($settings[$qr_key])) {
        $errors[] = __('Bangla QR needs your QR code image. It stays hidden at checkout until you add one.', 'ccd-payment-gateway-domain');
    }

    // Non-negative amounts; percentages capped at 100.
    foreach (array('_charge', '_discount') as $kind) {
        $amount_key = $gateway_id . $kind . '_amount';
        if (!isset($settings[$amount_key]) || '' === $settings[$amount_key]) {
            continue;
        }
        $amount = (float) $settings[$amount_key];
        $type   = isset($settings[$gateway_id . $kind . '_type']) ? $settings[$gateway_id . $kind . '_type'] : 'percent';
        if ($amount < 0) {
            $errors[] = __('Charge and discount amounts cannot be negative.', 'ccd-payment-gateway-domain');
            $amount   = 0;
        }
        if ('percent' === $type && $amount > 100) {
            $errors[] = __('A percentage charge or discount cannot be more than 100%.', 'ccd-payment-gateway-domain');
            $amount   = 100;
        }
        $settings[$amount_key] = wc_format_decimal($amount);
    }

    // Min / max order amount.
    $min_key = $gateway_id . '_min_amount';
    $max_key = $gateway_id . '_max_amount';
    $min     = isset($settings[$min_key]) && '' !== $settings[$min_key] ? max(0, (float) $settings[$min_key]) : '';
    $max     = isset($settings[$max_key]) && '' !== $settings[$max_key] ? max(0, (float) $settings[$max_key]) : '';
    if ('' !== $min && '' !== $max && $max > 0 && $min > $max) {
        $errors[] = __('Minimum order amount is higher than the maximum, so this payment method would never show. The maximum was cleared.', 'ccd-payment-gateway-domain');
        $max      = '';
    }
    if (isset($settings[$min_key])) {
        $settings[$min_key] = '' === $min ? '' : wc_format_decimal($min);
    }
    if (isset($settings[$max_key])) {
        $settings[$max_key] = '' === $max ? '' : wc_format_decimal($max);
    }

    if ($errors && class_exists('WC_Admin_Settings')) {
        foreach (array_unique($errors) as $error) {
            WC_Admin_Settings::add_error($label . ': ' . $error);
        }
    }

    return $settings;
}

add_action('admin_notices', 'ccd_misconfiguration_notice');
/**
 * Warn on the Payments screens about enabled gateways customers cannot use.
 */
function ccd_misconfiguration_notice()
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || 'woocommerce_page_wc-settings' !== $screen->id || !isset($_GET['tab']) || 'checkout' !== $_GET['tab']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $problems = array();
    foreach (ccd_gateway_config() as $gateway_id => $config) {
        $settings = ccd_gateway_settings($gateway_id);
        if (empty($settings['enabled']) || 'yes' !== $settings['enabled']) {
            continue;
        }
        if ('ccd_banglaqr' === $gateway_id) {
            if (empty($settings[$gateway_id . '_qr_code'])) {
                $problems[] = sprintf(
                    /* translators: %s: payment method label */
                    __('%s is enabled but has no QR code image, so it is hidden at checkout.', 'ccd-payment-gateway-domain'),
                    $config['label']
                );
            }
        } elseif (empty($settings[$config['number_key']])) {
            $problems[] = sprintf(
                /* translators: %s: payment method label */
                __('%s is enabled but has no receiving number/account.', 'ccd-payment-gateway-domain'),
                $config['label']
            );
        }
    }

    if ($problems) {
        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('CodeCareBD Payment Gateway:', 'ccd-payment-gateway-domain') . '</strong></p><ul style="list-style:disc;margin-left:20px;">';
        foreach ($problems as $problem) {
            echo '<li>' . esc_html($problem) . '</li>';
        }
        echo '</ul></div>';
    }
}

add_action('woocommerce_blocks_loaded', 'ccd_register_store_api_cart_data');
/**
 * Block checkout: expose the advance payment notice in the Store API cart
 * response, so it is recalculated after every cart update (address,
 * shipping or payment method change) instead of being fixed at page load.
 */
function ccd_register_store_api_cart_data()
{
    if (!function_exists('woocommerce_store_api_register_endpoint_data') || !class_exists('\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema')) {
        return;
    }
    woocommerce_store_api_register_endpoint_data(array(
        'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
        'namespace'       => 'ccd-payment-gateway',
        'data_callback'   => function () {
            return array('advance' => ccd_get_advance_payment_display());
        },
        'schema_callback' => function () {
            return array(
                'advance' => array(
                    'description' => __('Advance payment notice for the current cart.', 'ccd-payment-gateway-domain'),
                    'type'        => array('object', 'null'),
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
            );
        },
        'schema_type'     => ARRAY_A,
    ));
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

    $trx_field = ccd_trx_field($gateway_id);
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

    $trx_field = ccd_trx_field($gateway_id);
    $trx_rule  = isset($config[$gateway_id]['fields'][1]['rule']) ? $config[$gateway_id]['fields'][1]['rule'] : 'trx';
    $trx       = isset($data[$trx_field]) ? ccd_normalize_field($trx_rule, $data[$trx_field]) : '';

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
    $result   = array('text' => '', 'value' => '', 'qr' => '', 'rows' => array());

    if (!isset($config[$gateway_id])) {
        return $result;
    }
    $config = $config[$gateway_id];
    $get    = function ($key) use ($settings) {
        return isset($settings[$key]) ? trim((string) $settings[$key]) : '';
    };

    if ('ccd_bank' === $gateway_id) {
        $bank_rows = array(
            'bank_name'      => array(__('Bank :', 'ccd-payment-gateway-domain'), false),
            'account_name'   => array(__('Account Name :', 'ccd-payment-gateway-domain'), false),
            'account_number' => array(__('Account Number :', 'ccd-payment-gateway-domain'), true),
            'branch'         => array(__('Branch :', 'ccd-payment-gateway-domain'), false),
            'routing'        => array(__('Routing Number :', 'ccd-payment-gateway-domain'), true),
        );
        // Several accounts are shown with the searchable picker instead (ccd_bank_picker_accounts()).
        $accounts = ccd_bank_accounts();
        if (1 === count($accounts)) {
            foreach ($bank_rows as $key => $row) {
                if (!empty($accounts[0][$key])) {
                    $result['rows'][] = array('label' => $row[0], 'value' => $accounts[0][$key], 'copy' => $row[1]);
                }
            }
        }
    } else {
        $value = $get($config['number_key']);
        if ('' !== $value) {
            $type = !empty($config['type_key']) ? $get($config['type_key']) : '';
            switch ($gateway_id) {
                case 'ccd_payoneer':
                    $label = __('Payoneer Recipient Email :', 'ccd-payment-gateway-domain');
                    break;
                case 'ccd_banglaqr':
                    $label = __('Merchant Name :', 'ccd-payment-gateway-domain');
                    break;
                case 'ccd_cellfin':
                    $label = ('Mobile' === $type) ? __('CellFin Number :', 'ccd-payment-gateway-domain') : __('Islami Bank Account Number :', 'ccd-payment-gateway-domain');
                    break;
                default:
                    $label = trim(sprintf('%s %s Number :', $config['label'], $type));
            }
            $result['text']   = $label;
            $result['value']  = $value;
            $result['rows'][] = array('label' => $label, 'value' => $value, 'copy' => 'ccd_banglaqr' !== $gateway_id);
        }
    }

    if ('' !== $get($gateway_id . '_qr_code')) {
        $result['qr'] = esc_url_raw($get($gateway_id . '_qr_code'));
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

    foreach ($account['rows'] as $row) {
        if (isset($row['heading'])) {
            echo '<p class="ccd-account-heading"><strong>' . esc_html($row['heading']) . '</strong></p>';
            continue;
        }
        echo '<p class="ccd-account-info">' . esc_html($row['label']) . ' <strong class="ccd-account-value">' . esc_html($row['value']) . '</strong>';
        if ($row['copy']) {
            echo ' <button type="button" class="ccd-copy-btn" data-ccd-copy="' . esc_attr($row['value']) . '" data-ccd-copied="' . esc_attr__('Copied!', 'ccd-payment-gateway-domain') . '">' . esc_html__('Copy', 'ccd-payment-gateway-domain') . '</button>';
        }
        echo '</p>';
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
    $discount = ccd_get_discount_note($gateway_id);
    if ('' !== $discount) {
        echo '<div class="ccd_discount_note">' . wpautop(esc_html($discount)) . '</div>';
    }

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
