<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Queue processor: runs on WP Cron (asynchronous to the user).
 * Reads pending events, builds the mapped JSON payload, sends to CRM, and logs results.
 */
class Falnic_CRM_Processor {

    private $core;

    public function __construct( $core ) {
        $this->core = $core;
        add_action( FALNIC_CRM_CRON_HOOK, array( $this, 'process_queue' ) );
    }

    public function core() {
        return $this->core;
    }

    /**
     * Process pending queue events in batches.
     */
    public function process_queue() {
        $max_events = $this->max_events_per_run();
        $pending    = $this->core->queue->get_pending( $max_events );

        if ( empty( $pending ) ) {
            return;
        }

        global $wpdb;
        $sent = $failed = 0;

        foreach ( $pending as $event ) {
            $trigger = $this->core->triggers->get_by_id( $event['trigger_id'] );
            if ( ! $trigger || $trigger->status !== 'active' ) {
                $this->core->queue->mark_failed( $event['id'], 'trigger inactive or missing' );
                $failed++;
                continue;
            }

            // Load the source record (supports optional JOIN via mapping).
            $record = $this->load_record( $trigger, $event['record_id'], $event['event_type'] );
            if ( ! $record ) {
                $this->core->queue->mark_failed( $event['id'], 'source record not found' );
                $failed++;
                continue;
            }

            $mapping = $this->parse_mapping( $trigger->mapping );
            $payload = $this->build_payload( $mapping, $record );

            $endpoint = get_option( 'falnic_crm_endpoint_url', '' );
            $api_key  = $this->core->security->decrypt( get_option( 'falnic_crm_api_key', '' ) );
            $headers  = get_option( 'falnic_crm_headers', '' );

            $result = $this->core->api->send( $endpoint, $payload, $api_key, $headers );

            if ( $result['success'] ) {
                $this->core->queue->mark_processed( $event['id'], $result['response'] );
                $sent++;
            } else {
                // Retry up to 3 attempts; after that mark failed permanently and notify.
                $attempts = intval( $event['attempts'] ) + 1;
                if ( $attempts >= 3 ) {
                    $this->core->queue->mark_failed( $event['id'], $result['message'] );
                    $this->core->notifications->notify_send_error( $trigger, $event, $result['message'] );
                } else {
                    $wpdb->update(
                        $this->core->table_events,
                        array( 'status' => 'pending', 'error_message' => $result['message'], 'attempts' => $attempts ),
                        array( 'id' => intval( $event['id'] ) ),
                        array( '%s', '%s', '%d' ),
                        array( '%d' )
                    );
                }
                $failed++;
            }
        }

        // Alert if queue backlog is high.
        $pending_after = $this->core->queue->count_pending();
        $threshold = intval( get_option( 'falnic_crm_queue_alert_threshold', 100 ) );
        if ( $pending_after > $threshold ) {
            $this->core->notifications->notify_backlog( $pending_after, $threshold );
        }

        $this->core->security->log_activity(
            'queue_processed',
            sprintf( __( 'ارسال %d رویداد موفق و %d ناموفق.', 'falnic-crm' ), $sent, $failed )
        );
    }

    /**
     * Load the source record for an event. Allows an optional JOIN clause defined in mapping.
     */
    private function load_record( $trigger, $record_id, $event_type ) {
        global $wpdb;

        $table  = $trigger->source_table;
        $pk     = $this->core->security->validate_column( $table, $trigger->primary_key_column );
        if ( ! $this->core->security->validate_table( $table ) || ! $pk ) {
            return null;
        }

        $mapping = $this->parse_mapping( $trigger->mapping );
        $join    = '';
        foreach ( $mapping as $spec ) {
            if ( ! empty( $spec['join'] ) ) {
                $join .= ' ' . $wpdb->esc_sql( $spec['join'] );
            }
        }

        $row_column = ( $event_type === 'delete' ) ? 'OLD.' : 'NEW.';
        // We don't have NEW/OLD here; use the stored record_id from the events table.
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` {$join} WHERE `{$pk}` = %d LIMIT 1",
            intval( $record_id )
        ) );

        return $record ? (array) $record : null;
    }

    /**
     * Build the lead payload from mapping: static values or dynamic column values.
     */
    public function build_payload( array $mapping, array $record ) {
        $payload = array();
        foreach ( $mapping as $param => $spec ) {
            if ( empty( $param ) ) {
                continue;
            }
            $type  = $spec['type'] ?? 'static';
            $value = '';

            if ( $type === 'dynamic' && ! empty( $spec['column'] ) && isset( $record[ $spec['column'] ] ) ) {
                $value = $record[ $spec['column'] ];
                if ( $value === null || $value === '' ) {
                    $value = $spec['default'] ?? '';
                }
            } else {
                $value = $spec['value'] ?? '';
                if ( $value === '' && ! empty( $spec['default'] ) ) {
                    $value = $spec['default'];
                }
            }

            $payload[ $param ] = $value;
        }

        return apply_filters( 'falnic_crm_build_payload', $payload, $mapping, $record );
    }

    public function parse_mapping( $mapping ) {
        if ( ! is_string( $mapping ) ) {
            return is_array( $mapping ) ? $mapping : array();
        }
        $decoded = json_decode( $mapping, true );
        return is_array( $decoded ) ? $decoded : array();
    }

    public function max_events_per_run() {
        return (int) apply_filters( 'falnic_crm_max_events_per_run', intval( get_option( 'falnic_crm_max_events_per_run', 50 ) ) );
    }
}
