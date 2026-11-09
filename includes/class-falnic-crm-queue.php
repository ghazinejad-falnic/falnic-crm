<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event queue model: register, retrieve, and process CRM sync events.
 */
class Falnic_CRM_Queue {

    private $core;

    public function __construct( $core ) {
        $this->core = $core;
    }

    public function core() {
        return $this->core;
    }

    /**
     * Register a new event when a database trigger fires.
     */
    public function add_event( $trigger_id, $record_id, $event_type, $payload = '' ) {
        global $wpdb;
        $wpdb->insert(
            $this->core->table_events,
            array(
                'trigger_id'  => intval( $trigger_id ),
                'record_id'   => intval( $record_id ),
                'event_type'  => sanitize_text_field( $event_type ),
                'status'      => 'pending',
                'payload'     => is_scalar( $payload ) ? (string) $payload : wp_json_encode( $payload ),
                'response'    => '',
                'error_message'=> '',
                'attempts'    => 0,
                'created_at'  => current_time( 'mysql' ),
                'processed_at'=> null,
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
        return (int) $wpdb->insert_id;
    }

    /**
     * Get pending events, oldest first, limited.
     */
    public function get_pending( $limit = 50 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->core->table_events}
             WHERE status = 'pending'
             ORDER BY created_at ASC
             LIMIT %d",
            intval( $limit )
        ), ARRAY_A );
    }

    /**
     * Get counts by status.
     */
    public function counts() {
        global $wpdb;
        $counts = array();
        foreach ( array( 'pending', 'processed', 'failed' ) as $status ) {
            $counts[ $status ] = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->core->table_events} WHERE status = %s",
                $status
            ) );
        }
        return $counts;
    }

    public function count_pending() {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->core->table_events} WHERE status = 'pending'"
        ) );
    }

    /**
     * Mark an event as processed and store CRM response.
     */
    public function mark_processed( $event_id, $response ) {
        global $wpdb;
        $wpdb->update(
            $this->core->table_events,
            array(
                'status'       => 'processed',
                'response'     => is_scalar( $response ) ? (string) $response : wp_json_encode( $response ),
                'processed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => intval( $event_id ) ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Mark an event as failed with an error message and increment attempts.
     */
    public function mark_failed( $event_id, $error_message ) {
        global $wpdb;
        $wpdb->update(
            $this->core->table_events,
            array(
                'status'        => 'failed',
                'error_message' => sanitize_textarea_field( $error_message ),
            ),
            array( 'id' => intval( $event_id ) ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->core->table_events} SET attempts = attempts + 1 WHERE id = %d",
            intval( $event_id )
        ) );
    }

    /**
     * Delete processed events older than $days (cleanup).
     */
    public function delete_processed( $days = 30 ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->core->table_events}
             WHERE status = 'processed'
             AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            intval( $days )
        ) );
    }

    /**
     * Get recent events for dashboard table.
     */
    public function recent( $limit = 15 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, t.title AS trigger_title
             FROM {$this->core->table_events} e
             LEFT JOIN {$this->core->table_triggers} t ON t.id = e.trigger_id
             ORDER BY e.created_at DESC
             LIMIT %d",
            intval( $limit )
        ), ARRAY_A );
    }
}
