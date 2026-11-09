<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Notifications: admin email + SMS (placeholder driver, filterable).
 */
class Falnic_CRM_Notifications {

    private $core;

    public function __construct( $core ) {
        $this->core = $core;
    }

    public function core() {
        return $this->core;
    }

    /**
     * Notify admins when a trigger cannot be created or data cannot be sent.
     */
    public function notify_error( $title, $message, $data = array() ) {
        $this->send_email( $title, $message, $data );
        $this->send_sms( $title . ': ' . $message );
        $this->core->security->log_activity( 'admin_notified', $title . ' — ' . $message );
    }

    public function notify_send_error( $trigger, $event, $error ) {
        $title   = esc_html__( 'خطا در ارسال داده به CRM فالنیک', 'falnic-crm' );
        $message = sprintf(
            __( 'تریگر: %s (#%d)\nرویداد: #%d\nخطا: %s', 'falnic-crm' ),
            $trigger->title,
            intval( $trigger->id ),
            intval( $event['id'] ),
            $error
        );
        $this->notify_error( $title, $message, array( 'trigger_id' => intval( $trigger->id ), 'event_id' => intval( $event['id'] ) ) );
    }

    public function notify_backlog( $pending, $threshold ) {
        $title   = esc_html__( 'تخلفات صف در افزونه فالنیک CRM', 'falnic-crm' );
        $message = sprintf(
            __( 'تعداد رویدادهای در انتظار ارسال: %d (حد آستانه: %d)', 'falnic-crm' ),
            intval( $pending ),
            intval( $threshold )
        );
        $this->notify_error( $title, $message );
    }

    /**
     * Send an email to site administrators.
     */
    private function send_email( $title, $message, $data = array() ) {
        $admins = get_users( array( 'role' => 'administrator', 'fields' => array( 'user_email' ) ) );
        $emails = array_map( function( $u ) { return $u->user_email; }, $admins );
        $emails = array_filter( $emails );
        if ( empty( $emails ) ) {
            return;
        }
        $subject = '[' . get_bloginfo( 'name' ) . '] ' . $title;
        $body    = $message . "\n\n" . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        $body    = apply_filters( 'falnic_crm_notification_email_body', $body, $title, $data );
        wp_mail( $emails, $subject, $body );
    }

    /**
     * SMS placeholder. Developers can hook 'falnic_crm_sms_driver' to integrate a real provider.
     */
    private function send_sms( $message ) {
        $driver = apply_filters( 'falnic_crm_sms_driver', false );
        if ( $driver ) {
            $driver( $message );
        }
        $this->core->security->log_activity( 'sms_placeholder', $message );
    }
}
