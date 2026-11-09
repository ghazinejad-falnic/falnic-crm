<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Uninstall: drop custom tables and remove options.
 */
global $wpdb;

$tables = array(
    $wpdb->prefix . 'falnic_crm_triggers',
    $wpdb->prefix . 'falnic_crm_events',
    $wpdb->prefix . 'falnic_crm_logs',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

wp_clear_scheduled_hook( 'falnic_crm_process_queue' );

$options = array(
    'falnic_crm_endpoint_url',
    'falnic_crm_api_key',
    'falnic_crm_headers',
    'falnic_crm_max_events_per_run',
    'falnic_crm_queue_alert_threshold',
    'falnic_crm_version',
);

foreach ( $options as $option ) {
    delete_option( $option );
}
