<?php
/**
 * Fires when the plugin is deleted. Sends a deletion/erasure request to the
 * telemetry backend (only if the site had opted in) and removes local data.
 */
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$shakvaro_insights = __DIR__ . '/vendor/shakvaro-wp-insights/src/Insights.php';
if (file_exists($shakvaro_insights) && ! class_exists('\Shakvaro\WP\Insights\Insights', false)) {
    require_once $shakvaro_insights;
}
if (class_exists('\Shakvaro\WP\Insights\Insights', false)) {
    \Shakvaro\WP\Insights\Insights::uninstall(array(
        'slug'           => 'codecarebd-bkash-nagad-rocket-payoneer-gateway',
        'api_key'        => 'pk_codecarebd_bkash_nag_y44dKm9du3BifdGZ',
        'signing_secret' => 'aZp4Z11ignSgGT7XDsfJ0GDvXsfGST4IIHxR4IphgwvnKCBA',
        'endpoint'       => 'https://track.shakvaro.cloud',
    ));
}
