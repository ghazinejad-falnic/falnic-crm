<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core bootstrap: admin menu, assets, AJAX, cron, install/uninstall.
 */
class Falnic_CRM_Core {

    private static $instance = null;

    public $table_triggers;
    public $table_events;
    public $table_logs;

    public $settings;
    public $database;
    public $triggers;
    public $queue;
    public $api;
    public $processor;
    public $dashboard;
    public $notifications;
    public $security;
    public $hooks;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;

        $this->table_triggers = $wpdb->prefix . 'falnic_crm_triggers';
        $this->table_events   = $wpdb->prefix . 'falnic_crm_events';
        $this->table_logs     = $wpdb->prefix . 'falnic_crm_logs';

        // Sub-systems.
        $this->settings      = new Falnic_CRM_Settings( $this );
        $this->database      = new Falnic_CRM_Database( $this );
        $this->triggers      = new Falnic_CRM_Triggers( $this );
        $this->queue         = new Falnic_CRM_Queue( $this );
        $this->api           = new Falnic_CRM_API( $this );
        $this->processor     = new Falnic_CRM_Processor( $this );
        $this->dashboard     = new Falnic_CRM_Dashboard( $this );
        $this->notifications = new Falnic_CRM_Notifications( $this );
        $this->security      = new Falnic_CRM_Security( $this );
        $this->hooks         = new Falnic_CRM_Hooks( $this );

        add_action( 'init', array( $this, 'init' ) );
        add_action( 'cron_schedules', array( $this, 'cron_schedules' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX endpoints (manage_options only).
        $ajax = array(
            'falnic_crm_get_tables'        => 'ajax_get_tables',
            'falnic_crm_get_columns'       => 'ajax_get_columns',
            'falnic_crm_test_connection'   => 'ajax_test_connection',
            'falnic_crm_preview_payload'   => 'ajax_preview_payload',
            'falnic_crm_save_trigger'      => 'ajax_save_trigger',
            'falnic_crm_toggle_trigger'    => 'ajax_toggle_trigger',
            'falnic_crm_delete_trigger'    => 'ajax_delete_trigger',
            'falnic_crm_dashboard_stats'   => 'ajax_dashboard_stats',
        );
        foreach ( $ajax as $hook => $method ) {
            add_action( 'wp_ajax_' . $hook, array( $this, $method ) );
        }
    }

    public function init() {
        load_plugin_textdomain( 'falnic-crm', false, dirname( plugin_basename( FALNIC_CRM_PLUGIN_FILE ) ) . '/languages' );

        // Ensure tables exist even if activation hook was skipped.
        if ( $this->tables_missing() ) {
            $this->install_tables();
        }

        if ( ! wp_next_scheduled( FALNIC_CRM_CRON_HOOK ) ) {
            $this->schedule_cron();
        }
    }

    public function activate() {
        $this->install_tables();
        $this->schedule_cron();
        $this->security->log_activity( 'plugin_activated', 'افزونه Falnic CRM فعال شد.' );
    }

    public function deactivate() {
        wp_clear_scheduled_hook( FALNIC_CRM_CRON_HOOK );
        $this->security->log_activity( 'plugin_deactivated', 'افزونه Falnic CRM غیرفعال شد.' );
    }

    public function tables_missing() {
        global $wpdb;
        foreach ( array( $this->table_triggers, $this->table_events, $this->table_logs ) as $table ) {
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
                return true;
            }
        }
        return false;
    }

    public function install_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->table_triggers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL DEFAULT '',
            description text NOT NULL,
            source_table varchar(128) NOT NULL DEFAULT '',
            primary_key_column varchar(128) NOT NULL DEFAULT 'id',
            event_types varchar(64) NOT NULL DEFAULT 'insert',
            condition_sql text NOT NULL,
            mapping longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY source_table (source_table)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->table_events} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            trigger_id bigint(20) unsigned NOT NULL,
            record_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type varchar(20) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            payload longtext,
            response longtext,
            error_message text,
            attempts int unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY trigger_id (trigger_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->table_logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action varchar(64) NOT NULL DEFAULT '',
            details longtext,
            user_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }

        add_option( 'falnic_crm_version', FALNIC_CRM_VERSION );
    }

    public function cron_schedules( $schedules ) {
        $schedules[ FALNIC_CRM_CRON_INTERVAL ] = array(
            'interval' => 60,
            'display'  => __( 'یک‌در دقیقه (فالنیک CRM)', 'falnic-crm' ),
        );
        return $schedules;
    }

    public function schedule_cron() {
        if ( ! wp_next_scheduled( FALNIC_CRM_CRON_HOOK ) ) {
            wp_schedule_event( time(), FALNIC_CRM_CRON_INTERVAL, FALNIC_CRM_CRON_HOOK );
        }
    }

    public function unschedule_cron() {
        wp_clear_scheduled_hook( FALNIC_CRM_CRON_HOOK );
    }

    /**
     * WP Cron callback: process pending queue events (asynchronous to the user).
     */
    public function run_cron() {
        $this->processor->process_queue();
    }

    public function admin_menu() {
        $hook = add_menu_page(
            __( 'فالنیک CRM', 'falnic-crm' ),
            __( 'فالنیک CRM', 'falnic-crm' ),
            'manage_options',
            'falnic-crm',
            array( $this, 'render_dashboard' ),
            'dashicons-cart',
            55
        );

        add_submenu_page( 'falnic-crm', __( 'داشبورد', 'falnic-crm' ), __( 'داشبورد', 'falnic-crm' ), 'manage_options', 'falnic-crm', array( $this, 'render_dashboard' ) );
        add_submenu_page( 'falnic-crm', __( 'تنظیمات اتصال به CRM', 'falnic-crm' ), __( 'تنظیمات', 'falnic-crm' ), 'manage_options', 'falnic-crm-settings', array( $this->settings, 'render_page' ) );
        add_submenu_page( 'falnic-crm', __( 'مدیریت تریگرها', 'falnic-crm' ), __( 'تریگرها', 'falnic-crm' ), 'manage_options', 'falnic-crm-triggers', array( $this->triggers, 'render_page' ) );
        add_submenu_page( 'falnic-crm', __( 'لاگ‌های فعالیت', 'falnic-crm' ), __( 'لاگ‌ها', 'falnic-crm' ), 'manage_options', 'falnic-crm-logs', array( $this, 'render_logs' ) );
    }

    public function admin_init() {
        // Register settings for the settings page.
        register_setting( 'falnic_crm_settings_group', 'falnic_crm_endpoint_url' );
        register_setting( 'falnic_crm_settings_group', 'falnic_crm_api_key' );
        register_setting( 'falnic_crm_settings_group', 'falnic_crm_headers' );
        register_setting( 'falnic_crm_settings_group', 'falnic_crm_max_events_per_run' );
        register_setting( 'falnic_crm_settings_group', 'falnic_crm_queue_alert_threshold' );
    }

    public function enqueue_assets( $hook ) {
        // Only load assets on Falnic CRM admin pages.
        // Check both $hook and $_GET['page'] because query-string actions
        // (e.g. ?action=add) can change the $hook suffix in some WP versions.
        $page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
        if ( false === strpos( $hook, 'falnic-crm' ) && false === strpos( $page, 'falnic-crm' ) ) {
            return;
        }

        wp_enqueue_style( 'falnic-crm-admin', FALNIC_CRM_PLUGIN_URL . 'admin/css/admin.css', array(), FALNIC_CRM_VERSION );
        wp_enqueue_script( 'falnic-crm-admin', FALNIC_CRM_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery' ), FALNIC_CRM_VERSION, true );

        wp_localize_script( 'falnic-crm-admin', 'falnicCrm', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'falnic_crm_nonce' ),
            'strings'   => array(
                'saving'      => __( 'ذخیره کردن...', 'falnic-crm' ),
                'saved'       => __( 'ذخیره شد.', 'falnic-crm' ),
                'error'       => __( 'خطایی رخ داد.', 'falnic-crm' ),
                'confirm_del' => __( 'آیا از حذف این تریگر اطمینان دارید؟', 'falnic-crm' ),
            ),
        ) );

        // Chart.js only on dashboard.
        if ( false !== strpos( $hook, 'toplevel_page_falnic-crm' ) ) {
            wp_enqueue_script( 'falnic-crm-chart', 'https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js', array(), '2.9.4', true );
        }
    }

    public function render_dashboard() {
        $this->dashboard->render_page();
    }

    public function render_logs() {
        if ( ! $this->security->can_manage() ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ) );
        }

        global $wpdb;
        $paged    = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $per_page = 25;
        $offset   = ( $paged - 1 ) * $per_page;

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_logs} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );
        $total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_logs}" );
        $pages  = ceil( $total / $per_page );

        require FALNIC_CRM_PLUGIN_PATH . 'admin/partials/logs.php';
    }

    /* ---------- AJAX ---------- */

    public function ajax_get_tables() {
        $this->security->check_nonce();
        $search = sanitize_text_field( $_POST['search'] ?? '' );
        wp_send_json_success( $this->database->get_tables( $search ) );
    }

    public function ajax_get_columns() {
        $this->security->check_nonce();
        $table = $this->security->validate_table( sanitize_text_field( $_POST['table'] ?? '' ) );
        if ( ! $table ) {
            wp_send_json_error( __( 'جدول نامعتبر است.', 'falnic-crm' ) );
        }
        wp_send_json_success( $this->database->get_columns( $table ) );
    }

    public function ajax_test_connection() {
        $this->security->check_nonce();
        wp_send_json( $this->settings->test_connection() );
    }

    public function ajax_preview_payload() {
        $this->security->check_nonce();
        wp_send_json( $this->triggers->preview_payload() );
    }

    public function ajax_save_trigger() {
        $this->security->check_nonce();
        wp_send_json( $this->triggers->save_ajax() );
    }

    public function ajax_toggle_trigger() {
        $this->security->check_nonce();
        wp_send_json( $this->triggers->toggle_ajax() );
    }

    public function ajax_delete_trigger() {
        $this->security->check_nonce();
        wp_send_json( $this->triggers->delete_ajax() );
    }

    public function ajax_dashboard_stats() {
        $this->security->check_nonce();
        wp_send_json_success( $this->dashboard->get_stats() );
    }
}
