<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Extensibility: default hooks and filters so developers can extend the plugin
 * (custom CRM drivers, payload shaping, SMS, SQL, etc.).
 */
class Falnic_CRM_Hooks {

    private $core;

    public function __construct( $core ) {
        $this->core = $core;
        $this->register_default_filters();
    }

    public function core() {
        return $this->core;
    }

    private function register_default_filters() {
        // Example: allow a custom CRM driver to short-circuit the cURL sender.
        add_filter( 'falnic_crm_send_shortcircuit', array( $this, 'custom_driver_shortcircuit' ), 10, 4 );

        // Example: append a source site identifier to every lead payload.
        add_filter( 'falnic_crm_payload', array( $this, 'add_site_meta' ), 10, 2 );

        // Log every payload before send (audit trail).
        add_filter( 'falnic_crm_build_payload', array( $this, 'log_payload' ), 10, 3 );
    }

    public function custom_driver_shortcircuit( $short, $endpoint, $json_body, $headers ) {
        // Return null to fall back to default cURL; or return a result array to handle sending yourself.
        return null;
    }

    public function add_site_meta( array $payload, $endpoint ) {
        $payload['_source_site'] = get_bloginfo( 'name' );
        $payload['_source_url']  = site_url();
        return $payload;
    }

    public function log_payload( array $payload, array $mapping, array $record ) {
        // Intentionally lightweight: only log in debug mode.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Falnic CRM] payload: ' . wp_json_encode( $payload ) );
        }
        return $payload;
    }
}
