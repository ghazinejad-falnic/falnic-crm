<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dashboard: statistics, charts and recent events.
 */
class Falnic_CRM_Dashboard {

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
        $stats = $this->get_stats();
        require FALNIC_CRM_PLUGIN_PATH . 'admin/partials/dashboard.php';
    }

    /**
     * Aggregated stats for dashboard and AJAX.
     */
    public function get_stats() {
        global $wpdb;

        $triggers_total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->core->table_triggers}" );
        $triggers_active  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->core->table_triggers} WHERE status = 'active'" );
        $triggers_inactive= $triggers_total - $triggers_active;

        $counts = $this->core->queue->counts();
        $events_total = array_sum( $counts );

        // Events over time (last 30 days).
        $trend = array();
        $days  = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
             FROM {$this->core->table_events}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 29 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        ), ARRAY_A );
        $fill  = array_fill( 0, 29, 0 );
        $today = (int) gmdate( 'z' );
        foreach ( $days as $row ) {
            $day = (int) gmdate( 'z', strtotime( $row['day'] ) );
            $offset = $today - $day;
            if ( $offset >= 0 && $offset <= 29 ) {
                $fill[ 29 - $offset ] = (int) $row['cnt'];
            }
        }
        $trend = $fill;

        // Success / fail over all time.
        $success = $counts['processed'];
        $failed  = $counts['failed'];

        // Per-trigger stats.
        $per_trigger = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.id, t.title, t.status,
                    SUM(CASE WHEN e.status = 'processed' THEN 1 ELSE 0 END) AS success,
                    SUM(CASE WHEN e.status = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN e.status = 'pending' THEN 1 ELSE 0 END) AS pending
             FROM {$this->core->table_triggers} t
             LEFT JOIN {$this->core->table_events} e ON e.trigger_id = t.id
             GROUP BY t.id
             ORDER BY t.id DESC
             LIMIT 10"
        ), ARRAY_A );

        // Recent errors.
        $recent_errors = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, t.title AS trigger_title
             FROM {$this->core->table_events} e
             LEFT JOIN {$this->core->table_triggers} t ON t.id = e.trigger_id
             WHERE e.status = 'failed'
             ORDER BY e.created_at DESC
             LIMIT 10"
        ), ARRAY_A );

        return array(
            'triggers'     => array( 'total' => $triggers_total, 'active' => $triggers_active, 'inactive' => $triggers_inactive ),
            'events'       => $counts,
            'events_total' => $events_total,
            'trend'        => array_values( $trend ),
            'success'      => $success,
            'failed'       => $failed,
            'per_trigger'  => $per_trigger,
            'recent_errors'=> $recent_errors,
        );
    }

    /**
     * AJAX stats endpoint.
     */
    public function stats_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array( 'success' => false, 'message' => esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ) );
        }
        return array( 'success' => true, 'stats' => $this->get_stats() );
    }
}
