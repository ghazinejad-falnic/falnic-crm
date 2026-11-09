<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database introspection and MySQL trigger management.
 * All table/column identifiers are strictly whitelisted to prevent SQL injection.
 */
class Falnic_CRM_Database {

    private $core;

    public function __construct( $core ) {
        $this->core = $core;
    }

    public function core() {
        return $this->core;
    }

    /**
     * List database tables, optionally filtered by search term.
     */
    public function get_tables( $search = '' ) {
        global $wpdb;
        $sql = "SELECT TABLE_NAME FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'";
        if ( $search !== '' ) {
            $sql .= $wpdb->prepare( " AND TABLE_NAME LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
        }
        $sql .= " ORDER BY TABLE_NAME ASC LIMIT 200";
        $rows = $wpdb->get_col( $sql );
        $out  = array();
        foreach ( $rows as $table ) {
            $out[] = array(
                'name' => $table,
                'label' => $table,
            );
        }
        return $out;
    }

    /**
     * Return columns of a table: name, type, null, key, default, extra.
     */
    public function get_columns( $table ) {
        global $wpdb;
        if ( ! $this->core->security->validate_table( $table ) ) {
            return array();
        }

        $columns = $wpdb->get_results( $wpdb->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
             ORDER BY ORDINAL_POSITION",
            $table
        ), ARRAY_A );

        $out = array();
        foreach ( $columns as $col ) {
            $out[] = array(
                'name'   => $col['COLUMN_NAME'],
                'type'   => $col['COLUMN_TYPE'],
                'null'   => $col['IS_NULLABLE'],
                'key'    => $col['COLUMN_KEY'],
                'default'=> $col['COLUMN_DEFAULT'],
                'extra'  => $col['EXTRA'],
            );
        }
        return $out;
    }

    /**
     * Guess the primary key column: a column named 'id' else the first PRI key else first column.
     */
    public function detect_primary_key( $table ) {
        if ( ! $this->core->security->validate_table( $table ) ) {
            return '';
        }
        global $wpdb;
        $cols = $wpdb->get_results( $wpdb->prepare(
            "SELECT COLUMN_NAME, COLUMN_KEY, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
             ORDER BY ORDINAL_POSITION",
            $table
        ) );
        foreach ( $cols as $col ) {
            if ( strtolower( $col->COLUMN_NAME ) === 'id' ) {
                return $col->COLUMN_NAME;
            }
        }
        foreach ( $cols as $col ) {
            if ( $col->COLUMN_KEY === 'PRI' ) {
                return $col->COLUMN_NAME;
            }
        }
        if ( ! empty( $cols ) ) {
            return $cols[0]->COLUMN_NAME;
        }
        return '';
    }

    /**
     * Build a safe MySQL trigger name (max 64 chars).
     */
    public function trigger_name( $trigger_id, $suffix ) {
        return 'falnic_crm_' . intval( $trigger_id ) . '_' . $suffix;
    }

    /**
     * Drop all DB triggers for a given trigger definition.
     */
    public function drop_db_triggers( $trigger ) {
        global $wpdb;
        $ids = array( 'ai' => 'AFTER INSERT', 'au' => 'AFTER UPDATE', 'ad' => 'AFTER DELETE' );
        $event_map = array( 'insert' => 'ai', 'update' => 'au', 'delete' => 'ad' );

        $types = $this->parse_event_types( $trigger->event_types );
        foreach ( $types as $type ) {
            $suffix = $event_map[ $type ] ?? '';
            if ( $suffix === '' ) {
                continue;
            }
            $name = $this->trigger_name( $trigger->id, $suffix );
            $wpdb->query( "DROP TRIGGER IF EXISTS `{$name}`" );
        }
    }

    /**
     * Create (or recreate) MySQL database triggers for a trigger definition.
     * Returns array with 'success' bool and 'message'.
     */
    public function create_db_triggers( $trigger ) {
        global $wpdb;

        $this->drop_db_triggers( $trigger );

        $table    = $trigger->source_table;
        $pk       = $trigger->primary_key_column;
        $events   = $this->core->table_events;
        $tid      = intval( $trigger->id );
        $pk_col   = $this->core->security->validate_column( $table, $pk );

        if ( ! $this->core->security->validate_table( $table ) || ! $pk_col ) {
            return array( 'success' => false, 'message' => __( 'جدول منبع یا ستون کلید اصلی نامعتبر است.', 'falnic-crm' ) );
        }

        $event_map = array(
            'insert' => array( 'ai', 'AFTER INSERT',  'NEW' ),
            'update' => array( 'au', 'AFTER UPDATE',  'NEW' ),
            'delete' => array( 'ad', 'AFTER DELETE',  'OLD' ),
        );

        $types = $this->parse_event_types( $trigger->event_types );
        $errors = array();

        foreach ( $types as $type ) {
            if ( ! isset( $event_map[ $type ] ) ) {
                continue;
            }
            list( $suffix, $when, $row ) = $event_map[ $type ];
            $name = $this->trigger_name( $tid, $suffix );

            $sql = "CREATE TRIGGER `{$name}` {$when} ON `{$table}` FOR EACH ROW
                INSERT INTO `{$events}` (`trigger_id`, `record_id`, `event_type`, `status`, `created_at`)
                VALUES ({$tid}, {$row}.`{$pk_col}`, '{$type}', 'pending', NOW());";

            $result = $wpdb->query( $sql );
            if ( false === $result ) {
                $errors[] = $wpdb->last_error ?: __( 'خطای نامشخص در ایجاد تریگر دیتابیس.', 'falnic-crm' );
            }
        }

        if ( ! empty( $errors ) ) {
            return array( 'success' => false, 'message' => implode( '<br>', $errors ) );
        }

        return array( 'success' => true, 'message' => __( 'تریگرهای دیتابیس با موفقیت ایجاد شدند.', 'falnic-crm' ) );
    }

    /**
     * Sync DB triggers when table structure changes (called on save).
     */
    public function sync_db_triggers( $trigger ) {
        return $this->create_db_triggers( $trigger );
    }

    /**
     * Parse event_types string like "insert,update" into array.
     */
    public function parse_event_types( $types ) {
        if ( ! is_string( $types ) ) {
            $types = 'insert';
        }
        $out = array_map( 'trim', explode( ',', $types ) );
        $out = array_filter( $out, function( $t ) { return in_array( $t, array( 'insert', 'update', 'delete' ), true ); } );
        return array_values( $out );
    }
}
