<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Security helpers: encryption, activity log, capability checks, input validation.
 */
class Falnic_CRM_Security {

    private $core;

    public function __construct( $core ) {
        $this->core = $core;

        add_action( 'admin_init', array( $this, 'block_direct_access' ) );
    }

    public function core() {
        return $this->core;
    }

    /**
     * Only users who can manage options may enter admin pages.
     */
    public function can_manage() {
        return is_admin() && current_user_can( 'manage_options' );
    }

    public function check_nonce() {
        if ( ! $this->can_manage() ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ), 403 );
        }
        $nonce = $_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'falnic_crm_nonce' ) ) {
            wp_die( esc_html__( 'تأیید اعتبار ناموفق است.', 'falnic-crm' ), 403 );
        }
    }

    /**
     * Validate a table name against the real database (whitelist) to prevent SQL injection.
     */
    public function validate_table( $table ) {
        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) ) {
            return false;
        }
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        return $exists === $table ? $table : false;
    }

    /**
     * Validate a column name for a given table.
     */
    public function validate_column( $table, $column ) {
        if ( ! $table || ! preg_match( '/^[a-zA-Z0-9_]+$/', $column ) ) {
            return false;
        }
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1",
            $table,
            $column
        ) );
        return $exists === $column ? $column : false;
    }

    /**
     * Encrypt sensitive values (API key, etc.) using OpenSSL.
     */
    public function encrypt( $value ) {
        if ( $value === '' || $value === null ) {
            return $value;
        }
        $key    = $this->get_key();
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        if ( false === $cipher ) {
            return $value;
        }
        return base64_encode( $iv . $cipher );
    }

    public function decrypt( $value ) {
        if ( $value === '' || $value === null ) {
            return $value;
        }
        $data   = base64_decode( $value, true );
        if ( false === $data || strlen( $data ) < 16 ) {
            return $value;
        }
        $iv     = substr( $data, 0, 16 );
        $cipher = substr( $data, 16 );
        $key    = $this->get_key();
        return openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
    }

    private function get_key() {
        static $key = null;
        if ( null !== $key ) {
            return $key;
        }
        $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';
        $key  = hash( 'sha256', $salt . '|' . site_url() . '|falnic-crm', true );
        return $key;
    }

    /**
     * Log an admin activity / plugin event.
     */
    public function log_activity( $action, $details = '', $user_id = 0 ) {
        global $wpdb;
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        $wpdb->insert(
            $this->core()->table_logs,
            array(
                'action'    => sanitize_text_field( $action ),
                'details'   => is_scalar( $details ) ? (string) $details : wp_json_encode( $details ),
                'user_id'   => intval( $user_id ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s' )
        );
    }

    /**
     * Prevent direct access to admin pages by unprivileged users.
     */
    public function block_direct_access() {
        if ( ! $this->can_manage() && isset( $_GET['page'] ) && strpos( $_GET['page'], 'falnic-crm' ) === 0 ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ), 403 );
        }
    }

    /**
     * Sanitize mapping JSON safely.
     */
    public function sanitize_mapping( $mapping ) {
        if ( ! is_array( $mapping ) ) {
            $mapping = array();
        }
        $clean = array();
        foreach ( $mapping as $param => $spec ) {
            $param = sanitize_text_field( $param );
            if ( $param === '' ) {
                continue;
            }
            if ( ! is_array( $spec ) ) {
                $spec = array( 'type' => 'static', 'value' => $spec );
            }
            $clean[ $param ] = array(
                'type'    => in_array( $spec['type'] ?? 'static', array( 'static', 'dynamic' ), true ) ? $spec['type'] : 'static',
                'value'   => sanitize_text_field( $spec['value'] ?? '' ),
                'column'  => sanitize_text_field( $spec['column'] ?? '' ),
                'default' => sanitize_text_field( $spec['default'] ?? '' ),
            );
        }
        return $clean;
    }
}
