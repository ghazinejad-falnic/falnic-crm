<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Trigger management: list, add, edit, toggle, delete trigger definitions
 * and their MySQL database triggers.
 */
class Falnic_CRM_Triggers {

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

        global $wpdb;

        $message   = '';
        $message_type = 'notice-success';

        // Handle bulk actions from the form.
        if ( isset( $_POST['falnic_crm_trigger_action'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'falnic_crm_triggers' ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ), 403 );
            }
            $action = sanitize_text_field( $_POST['falnic_crm_trigger_action'] );
            $ids    = array_map( 'intval', $_POST['trigger_ids'] ?? array() );
            foreach ( $ids as $id ) {
                $trigger = $this->get_by_id( $id );
                if ( ! $trigger ) {
                    continue;
                }
                if ( $action === 'activate' ) {
                    $this->set_status( $id, 'active' );
                    $this->core->security->log_activity( 'trigger_activated', 'تریگر فعال شد: #' . $id . ' — ' . $trigger->title );
                } elseif ( $action === 'deactivate' ) {
                    $this->set_status( $id, 'inactive' );
                    $this->core->database->drop_db_triggers( $trigger );
                    $this->core->security->log_activity( 'trigger_deactivated', 'تریگر غیرفعال شد: #' . $id . ' — ' . $trigger->title );
                } elseif ( $action === 'delete' ) {
                    $this->delete( $id );
                    $this->core->security->log_activity( 'trigger_deleted', 'تریگر حذف شد: #' . $id . ' — ' . $trigger->title );
                }
            }
            $message = __( ' عملیات با موفقیت انجام شد.', 'falnic-crm' );
        }

        // List triggers with optional search/pagination.
        $search   = sanitize_text_field( $_GET['s'] ?? '' );
        $paged    = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $per_page = 20;
        $offset   = ( $paged - 1 ) * $per_page;

        $where = '';
        $args  = array();
        if ( $search !== '' ) {
            $where = 'WHERE title LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $triggers = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->core->table_triggers} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
            ...array_merge( $args, array( $per_page, $offset ) )
        ) );
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->core->table_triggers} {$where}",
            $args
        ) );
        $pages = ceil( $total / $per_page );

        require FALNIC_CRM_PLUGIN_PATH . 'admin/partials/triggers.php';
    }

    /**
     * Render the add/edit trigger form (visual mapping UI).
     */
    public function render_form( $id = 0 ) {
        if ( ! $this->core->security->can_manage() ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ), 403 );
        }

        global $wpdb;
        $trigger = null;
        if ( $id ) {
            $trigger = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$this->core->table_triggers} WHERE id = %d",
                intval( $id )
            ) );
            if ( ! $trigger ) {
                wp_die( esc_html__( 'تریگر یافت نشد.', 'falnic-crm' ) );
            }
        }

        $mapping = array();
        if ( $trigger ) {
            $mapping = maybe_unserialize( $trigger->mapping );
            if ( ! is_array( $mapping ) ) {
                $mapping = array();
            }
        }

        require FALNIC_CRM_PLUGIN_PATH . 'admin/partials/trigger-form.php';
    }

    public function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->core->table_triggers} WHERE id = %d",
            intval( $id )
        ) );
    }

    public function set_status( $id, $status ) {
        global $wpdb;
        $wpdb->update(
            $this->core->table_triggers,
            array( 'status' => $status ),
            array( 'id' => intval( $id ) ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * AJAX: save trigger definition + mapping, then (re)create DB triggers.
     */
    public function save_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array( 'success' => false, 'message' => esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ) );
        }

        $title     = sanitize_text_field( $_POST['title'] ?? '' );
        $desc      = sanitize_textarea_field( $_POST['description'] ?? '' );
        $table     = $this->core->security->validate_table( sanitize_text_field( $_POST['source_table'] ?? '' ) );
        $pk        = sanitize_text_field( $_POST['primary_key_column'] ?? 'id' );
        $event_types = sanitize_text_field( $_POST['event_types'] ?? 'insert' );
        $condition = sanitize_textarea_field( $_POST['condition_sql'] ?? '' );
        $mapping   = $this->core->security->sanitize_mapping( $_POST['mapping'] ?? array() );
        $status    = in_array( $_POST['status'] ?? 'active', array( 'active', 'inactive' ), true ) ? $_POST['status'] : 'active';
        $id        = intval( $_POST['id'] ?? 0 );

        if ( $title === '' ) {
            return array( 'success' => false, 'message' => esc_html__( 'عنوان تریجر را وارد کنید.', 'falnic-crm' ) );
        }
        if ( ! $table ) {
            return array( 'success' => false, 'message' => esc_html__( 'جدول منبع نامعتبر است.', 'falnic-crm' ) );
        }
        if ( ! $this->core->security->validate_column( $table, $pk ) ) {
            return array( 'success' => false, 'message' => esc_html__( 'ستون کلید اصلی نامعتبر است.', 'falnic-crm' ) );
        }

        global $wpdb;
        $now = current_time( 'mysql' );
        $created_by = get_current_user_id();

        $data = array(
            'title'             => $title,
            'description'       => $desc,
            'source_table'      => $table,
            'primary_key_column'=> $pk,
            'event_types'       => $event_types,
            'condition_sql'     => $condition,
            'mapping'           => wp_json_encode( $mapping ),
            'status'            => $status,
            'updated_at'        => $now,
        );

        $is_new = ( $id === 0 );
        if ( $is_new ) {
            $data['created_at']  = $now;
            $data['created_by']  = $created_by;
            $wpdb->insert( $this->core->table_triggers, $data, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ) );
            $id = (int) $wpdb->insert_id;
        } else {
            $wpdb->update( $this->core->table_triggers, $data, array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
        }

        // (Re)create MySQL triggers only when active.
        $trigger = (object) array_merge( array( 'id' => $id ), $data );
        if ( $status === 'active' ) {
            $result = $this->core->database->create_db_triggers( $trigger );
            if ( ! $result['success'] ) {
                // Deactivate trigger so the queue is not filled with broken events.
                $this->set_status( $id, 'inactive' );
                return array( 'success' => false, 'message' => $result['message'] );
            }
        } else {
            $this->core->database->drop_db_triggers( $trigger );
        }

        $this->core->security->log_activity(
            $is_new ? 'trigger_created' : 'trigger_updated',
            'تریگر: ' . $title . ' (#' . $id . ')'
        );

        return array( 'success' => true, 'message' => __( 'ذخیره شد.', 'falnic-crm' ), 'id' => $id );
    }

    public function toggle_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array( 'success' => false, 'message' => esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ) );
        }
        $id    = intval( $_POST['id'] ?? 0 );
        $trigger = $this->get_by_id( $id );
        if ( ! $trigger ) {
            return array( 'success' => false, 'message' => __( 'تریگر یافت نشد.', 'falnic-crm' ) );
        }
        $new_status = $trigger->status === 'active' ? 'inactive' : 'active';
        $this->set_status( $id, $new_status );

        if ( $new_status === 'active' ) {
            $result = $this->core->database->create_db_triggers( $trigger );
            if ( ! $result['success'] ) {
                $this->set_status( $id, 'inactive' );
                return array( 'success' => false, 'message' => $result['message'] );
            }
        } else {
            $this->core->database->drop_db_triggers( $trigger );
        }

        $this->core->security->log_activity( 'trigger_toggled', 'وضعیت تریگر تغییر کرد: #' . $id . ' → ' . $new_status );
        return array( 'success' => true, 'status' => $new_status );
    }

    public function delete_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array( 'success' => false, 'message' => esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ) );
        }
        $id = intval( $_POST['id'] ?? 0 );
        $trigger = $this->get_by_id( $id );
        if ( ! $trigger ) {
            return array( 'success' => false, 'message' => __( 'تریگر یافت نشد.', 'falnic-crm' ) );
        }
        $this->delete( $id );
        $this->core->security->log_activity( 'trigger_deleted', 'تریگر حذف شد: #' . $id . ' — ' . $trigger->title );
        return array( 'success' => true );
    }

    private function delete( $id ) {
        global $wpdb;
        $trigger = $this->get_by_id( $id );
        if ( $trigger ) {
            $this->core->database->drop_db_triggers( $trigger );
        }
        $wpdb->delete( $this->core->table_triggers, array( 'id' => intval( $id ) ), array( '%d' ) );
        $wpdb->delete( $this->core->table_events, array( 'trigger_id' => intval( $id ) ), array( '%d' ) );
    }

    /**
     * AJAX: preview the payload that would be sent for the latest record of the source table.
     */
    public function preview_payload() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array( 'success' => false, 'message' => esc_html__( 'دسترسی غیرمجاز.', 'falnic-crm' ) );
        }

        $table    = $this->core->security->validate_table( sanitize_text_field( $_POST['source_table'] ?? '' ) );
        $pk       = sanitize_text_field( $_POST['primary_key_column'] ?? 'id' );
        $mapping  = $this->core->security->sanitize_mapping( $_POST['mapping'] ?? array() );

        if ( ! $table ) {
            return array( 'success' => false, 'message' => esc_html__( 'جدول منبع نامعتبر است.', 'falnic-crm' ) );
        }

        global $wpdb;
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` ORDER BY `{$pk}` DESC LIMIT 1"
        ) );
        if ( ! $record ) {
            return array( 'success' => true, 'payload' => array(), 'message' => __( 'رکوردی برای پیش‌نمایش یافت نشد.', 'falnic-crm' ) );
        }

        $payload = $this->core->processor->build_payload( $mapping, (array) $record );
        return array( 'success' => true, 'payload' => $payload );
    }
}
