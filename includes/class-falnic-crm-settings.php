<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CRM connection settings page (endpoint, API key, headers).
 */
class Falnic_CRM_Settings {

    private $core;

    public function __construct( $core ) {
        $this->core = $core;
    }

    public function core() {
        return $this->core;
    }

    public function render_page() {
        if ( ! $this->core->security->can_manage() ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ), 403 );
        }

        $saved = false;
        if ( isset( $_POST['falnic_crm_save_settings'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'falnic_crm_settings' ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ), 403 );
            }

            $endpoint = esc_url_raw( sanitize_text_field( $_POST['falnic_crm_endpoint_url'] ?? '' ) );
            $api_key  = sanitize_text_field( $_POST['falnic_crm_api_key'] ?? '' );
            $headers  = sanitize_textarea_field( $_POST['falnic_crm_headers'] ?? '' );
            $max      = intval( $_POST['falnic_crm_max_events_per_run'] ?? 50 );
            $threshold= intval( $_POST['falnic_crm_queue_alert_threshold'] ?? 100 );

            update_option( 'falnic_crm_endpoint_url', $endpoint );
            update_option( 'falnic_crm_api_key', $this->core->security->encrypt( $api_key ) );
            update_option( 'falnic_crm_headers', $headers );
            update_option( 'falnic_crm_max_events_per_run', max( 1, $max ) );
            update_option( 'falnic_crm_queue_alert_threshold', max( 1, $threshold ) );

            $this->core->security->log_activity( 'settings_saved', 'تنظیمات اتصال به CRM ذخیره شد.' );
            $saved = true;
        }

        $endpoint = get_option( 'falnic_crm_endpoint_url', 'https://api.falnic.com/apiCrm/public/api/v1/crm' );
        $api_key  = $this->core->security->decrypt( get_option( 'falnic_crm_api_key', '' ) );
        $headers  = get_option( 'falnic_crm_headers', 'X-ALLOW-CRM-CORE-WRITE: 1' );
        $max      = get_option( 'falnic_crm_max_events_per_run', 50 );
        $threshold= get_option( 'falnic_crm_queue_alert_threshold', 100 );

        require FALNIC_CRM_PLUGIN_PATH . 'admin/partials/settings.php';
    }

    /**
     * Test connection by sending a minimal payload to the endpoint.
     */
    public function test_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array( 'success' => false, 'message' => esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ) );
        }

        $endpoint = esc_url_raw( sanitize_text_field( $_POST['endpoint_url'] ?? get_option( 'falnic_crm_endpoint_url', '' ) ) );
        $api_key  = $this->core->security->decrypt( sanitize_text_field( $_POST['api_key'] ?? get_option( 'falnic_crm_api_key', '' ) ) );
        $headers  = sanitize_textarea_field( $_POST['headers'] ?? get_option( 'falnic_crm_headers', '' ) );

        if ( $endpoint === '' ) {
            return array( 'success' => false, 'message' => esc_html__( 'آدرس endpoint را وارد کنید.', 'falnic-crm' ) );
        }

        $result = $this->core->api->send( $endpoint, array( 'test' => true, 'source' => 'falnic-crm' ), $api_key, $headers );
        return $result;
    }
}
