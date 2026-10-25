<?php

/**
 * Payment Reports: totals per payment method and per receiving account,
 * a transaction list, and CSV export. WooCommerce > Payment Reports.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'ccd_reports_menu', 60);
/**
 * Register the reports page.
 */
function ccd_reports_menu()
{
    add_submenu_page(
        'woocommerce',
        __('Payment Reports', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Payment Reports', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        'view_woocommerce_reports',
        'ccd-payment-reports',
        'ccd_reports_page'
    );
}

/**
 * Report filters from the request, with safe defaults (last 30 days, all statuses but cancelled/failed).
 *
 * @param array $src Request array.
 * @return array{from:string,to:string,method:string,status:string}
 */
function ccd_reports_filters($src)
{
    $date = function ($key, $default) use ($src) {
        $value = isset($src[$key]) ? sanitize_text_field(wp_unslash($src[$key])) : '';
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $default;
    };
    $to   = $date('to', wp_date('Y-m-d'));
    $from = $date('from', wp_date('Y-m-d', strtotime('-29 days', strtotime($to))));
    if ($from > $to) {
        list($from, $to) = array($to, $from);
    }
    $method = isset($src['method']) ? sanitize_key(wp_unslash($src['method'])) : '';
    $config = ccd_gateway_config();
    $status = isset($src['status']) ? sanitize_key(wp_unslash($src['status'])) : '';
    return array(
        'from'   => $from,
        'to'     => $to,
        'method' => isset($config[$method]) ? $method : '',
        'status' => in_array($status, array('verify', 'paid'), true) ? $status : '',
    );
}

/**
 * Order IDs matching the filters (newest first).
 *
 * @param array $f Filters.
 * @return int[]
 */
function ccd_reports_order_ids($f)
{
    if ('verify' === $f['status']) {
        $statuses = array_map(function ($s) {
            return 'wc-' . $s;
        }, ccd_verify_statuses());
    } elseif ('paid' === $f['status']) {
        $statuses = array('wc-processing', 'wc-completed');
    } else {
        $statuses = array_diff(array_keys(wc_get_order_statuses()), array('wc-cancelled', 'wc-failed', 'wc-refunded'));
    }
    return wc_get_orders(array(
        'limit'          => -1,
        'return'         => 'ids',
        'type'           => 'shop_order',
        'status'         => array_values($statuses),
        'payment_method' => $f['method'] ? array($f['method']) : array_keys(ccd_gateway_config()),
        'date_created'   => $f['from'] . '...' . $f['to'],
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));
}

/**
 * One report row for an order.
 *
 * @param WC_Order $order Order.
 * @return array
 */
function ccd_reports_row($order)
{
    $config = ccd_gateway_config();
    $method = $order->get_payment_method();
    $fields = isset($config[$method]) ? $config[$method]['fields'] : array();
    $paid_to = $order->get_meta('_ccd_paid_to') ? $order->get_meta('_ccd_paid_to') : $order->get_meta('_ccd_bank_paid_to');
    if (!$paid_to && isset($config[$method]) && !empty($config[$method]['number_key'])) {
        // Single receiving account: the configured number.
        $accounts = function_exists('ccd_receiving_accounts') ? ccd_receiving_accounts($method) : array();
        if (1 === count($accounts)) {
            $d       = ccd_describe_account($method, $accounts[0]);
            $paid_to = $d['paid_to'] ? $d['paid_to'] : $d['details'][0]['value'];
        }
    }
    return array(
        'id'       => $order->get_id(),
        'number'   => $order->get_order_number(),
        'date'     => $order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d H:i') : '',
        'method'   => isset($config[$method]) ? $config[$method]['label'] : $method,
        'method_id' => $method,
        'sender'   => isset($fields[0]) ? (string) $order->get_meta($fields[0]['meta']) : '',
        'trx'      => isset($fields[1]) ? (string) $order->get_meta($fields[1]['meta']) : '',
        'paid_to'  => (string) $paid_to,
        'amount'   => (float) $order->get_total(),
        'status'   => wc_get_order_status_name($order->get_status()),
        'proof'    => (bool) $order->get_meta('_ccd_payment_proof'),
        'customer' => $order->get_formatted_billing_full_name(),
    );
}

/**
 * Rows for all matching orders, loaded in batches.
 *
 * @param array $f Filters.
 * @return array[]
 */
function ccd_reports_rows($f)
{
    $rows = array();
    foreach (array_chunk(ccd_reports_order_ids($f), 200) as $chunk) {
        foreach ($chunk as $id) {
            $order = wc_get_order($id);
            if ($order) {
                $rows[] = ccd_reports_row($order);
            }
        }
    }
    return $rows;
}

/**
 * Totals grouped by a row key.
 *
 * @param array[] $rows Rows.
 * @param string  $key  Group key.
 * @return array label => [count, amount]
 */
function ccd_reports_group($rows, $key)
{
    $groups = array();
    foreach ($rows as $row) {
        $label = '' !== $row[$key] ? $row[$key] : __('(not recorded)', 'codecarebd-bkash-nagad-rocket-payoneer-gateway');
        if (!isset($groups[$label])) {
            $groups[$label] = array(0, 0.0);
        }
        $groups[$label][0]++;
        $groups[$label][1] += $row['amount'];
    }
    uasort($groups, function ($a, $b) {
        return $b[1] <=> $a[1];
    });
    return $groups;
}

/**
 * Reports page.
 */
function ccd_reports_page()
{
    if (!current_user_can('view_woocommerce_reports')) {
        wp_die(esc_html__('Sorry, you are not allowed to view this page.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'));
    }
    $f      = ccd_reports_filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
    $rows   = ccd_reports_rows($f);
    $total  = array_sum(wp_list_pluck($rows, 'amount'));
    $page   = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $per    = 50;
    $pages  = max(1, (int) ceil(count($rows) / $per));
    $page   = min($page, $pages);
    $config = ccd_gateway_config();
    $money  = function ($amount) {
        return wp_kses_post(wc_price($amount));
    };
    $csv = wp_nonce_url(add_query_arg(array_merge(array('action' => 'ccd_report_csv'), $f), admin_url('admin-post.php')), 'ccd_report_csv');
    ?>
    <div class="wrap ccd-reports">
        <h1><?php esc_html_e('Payment Reports', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></h1>
        <form method="get" style="margin:12px 0;">
            <input type="hidden" name="page" value="ccd-payment-reports">
            <label><?php esc_html_e('From', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?> <input type="date" name="from" value="<?php echo esc_attr($f['from']); ?>"></label>
            <label><?php esc_html_e('To', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?> <input type="date" name="to" value="<?php echo esc_attr($f['to']); ?>"></label>
            <select name="method">
                <option value=""><?php esc_html_e('All payment methods', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></option>
                <?php foreach ($config as $id => $c) : ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($f['method'], $id); ?>><?php echo esc_html($c['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value=""><?php esc_html_e('All (except cancelled, failed, refunded)', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></option>
                <option value="verify" <?php selected($f['status'], 'verify'); ?>><?php esc_html_e('Needs payment verification', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></option>
                <option value="paid" <?php selected($f['status'], 'paid'); ?>><?php esc_html_e('Paid (processing / completed)', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></option>
            </select>
            <button class="button"><?php esc_html_e('Filter', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></button>
            <a class="button button-primary" href="<?php echo esc_url($csv); ?>"><?php esc_html_e('Export CSV', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></a>
        </form>

        <p class="ccd-reports-summary">
            <?php
            /* translators: 1: number of orders, 2: total amount */
            echo wp_kses_post(sprintf(__('%1$s orders, %2$s in total.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), '<strong>' . number_format_i18n(count($rows)) . '</strong>', '<strong>' . wc_price($total) . '</strong>'));
            ?>
        </p>

        <div style="display:flex;flex-wrap:wrap;gap:24px;">
            <?php foreach (array('method' => __('By payment method', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), 'paid_to' => __('By receiving number / account', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')) as $key => $heading) : ?>
                <div style="flex:1 1 360px;">
                    <h2><?php echo esc_html($heading); ?></h2>
                    <table class="widefat striped">
                        <thead><tr><th><?php echo esc_html('method' === $key ? __('Payment method', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') : __('Paid To', 'codecarebd-bkash-nagad-rocket-payoneer-gateway')); ?></th><th><?php esc_html_e('Orders', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th><th><?php esc_html_e('Amount', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th></tr></thead>
                        <tbody>
                            <?php $groups = ccd_reports_group($rows, $key); ?>
                            <?php if (!$groups) : ?>
                                <tr><td colspan="3"><?php esc_html_e('No orders in this period.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($groups as $label => $g) : ?>
                                <tr><td><?php echo esc_html($label); ?></td><td><?php echo esc_html(number_format_i18n($g[0])); ?></td><td><?php echo $money($g[1]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post. ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 style="margin-top:24px;"><?php esc_html_e('Transactions', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Order', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th>
                    <th><?php esc_html_e('Date', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th>
                    <th><?php esc_html_e('Payment method', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th>
                    <th><?php esc_html_e('Sender', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th>
                    <th><?php esc_html_e('Transaction ID', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th>
                    <th><?php esc_html_e('Paid To', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th>
                    <th><?php esc_html_e('Amount', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th>
                    <th><?php esc_html_e('Status', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th>
                    <th><?php esc_html_e('Screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows) : ?>
                    <tr><td colspan="9"><?php esc_html_e('No orders in this period.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'); ?></td></tr>
                <?php endif; ?>
                <?php foreach (array_slice($rows, ($page - 1) * $per, $per) as $r) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url(wc_get_order($r['id'])->get_edit_order_url()); ?>">#<?php echo esc_html($r['number']); ?></a> <?php echo esc_html($r['customer']); ?></td>
                        <td><?php echo esc_html($r['date']); ?></td>
                        <td><?php echo esc_html($r['method']); ?></td>
                        <td><?php echo esc_html($r['sender']); ?></td>
                        <td><?php echo esc_html($r['trx']); ?></td>
                        <td><?php echo esc_html($r['paid_to']); ?></td>
                        <td><?php echo $money($r['amount']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post. ?></td>
                        <td><?php echo esc_html($r['status']); ?></td>
                        <td><?php echo $r['proof'] ? esc_html__('Yes', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') : '&ndash;'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($pages > 1) : ?>
            <div class="tablenav"><div class="tablenav-pages">
                <?php
                echo wp_kses_post(paginate_links(array(
                    'base'    => add_query_arg('paged', '%#%'),
                    'format'  => '',
                    'current' => $page,
                    'total'   => $pages,
                )));
                ?>
            </div></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Neutralise spreadsheet formulas in a CSV cell (CSV injection).
 *
 * @param string $value Cell value.
 * @return string
 */
function ccd_reports_csv_cell($value)
{
    $value = (string) $value;
    // Check the first visible character: leading spaces / control characters do not stop
    // spreadsheets from evaluating a formula. Covers full-width variants and DDE "|".
    $first = ltrim($value, " \t\r\n\0\x0B");
    return preg_match('/^(?:[=+\-@|]|\x{FF1D}|\x{FF0B}|\x{FF0D}|\x{FF20})/u', $first) || $first !== $value
        ? "'" . $value
        : $value;
}


/**
 * One RFC 4180 CSV line: every field quoted, inner quotes doubled, no escape character.
 * fputcsv() treats "\" as an escape, which spreadsheets do not, so a crafted value could
 * break out of its cell; PHP 7.3 (our minimum) cannot turn that off.
 *
 * @param array $fields Field values.
 * @return string
 */
function ccd_reports_csv_line($fields)
{
    $cells = array();
    foreach ($fields as $field) {
        $field   = str_replace(array("\r\n", "\r", "\n"), ' ', (string) $field);
        $cells[] = '"' . str_replace('"', '""', $field) . '"';
    }
    return implode(',', $cells) . "\r\n";
}

add_action('admin_post_ccd_report_csv', 'ccd_reports_csv');
/**
 * Stream the transaction list as CSV.
 */
function ccd_reports_csv()
{
    check_admin_referer('ccd_report_csv');
    if (!current_user_can('view_woocommerce_reports')) {
        wp_die(esc_html__('Sorry, you are not allowed to do that.', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'), '', array('response' => 403));
    }
    $f    = ccd_reports_filters($_GET);
    $rows = ccd_reports_rows($f);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments-' . $f['from'] . '-to-' . $f['to'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Bangla text correctly.
    fwrite($out, ccd_reports_csv_line(array(
        __('Order', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Date', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Customer', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Payment method', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Sender', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Transaction ID', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Paid To', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Amount', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Currency', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Status', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
        __('Screenshot', 'codecarebd-bkash-nagad-rocket-payoneer-gateway'),
    )));
    $currency = get_woocommerce_currency();
    foreach ($rows as $r) {
        fwrite($out, ccd_reports_csv_line(array_map('ccd_reports_csv_cell', array(
            $r['number'], $r['date'], $r['customer'], $r['method'], $r['sender'], $r['trx'], $r['paid_to'],
            wc_format_decimal($r['amount'], wc_get_price_decimals()), $currency, $r['status'],
            $r['proof'] ? __('Yes', 'codecarebd-bkash-nagad-rocket-payoneer-gateway') : '',
        ))));
    }
    fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
    exit;
}
