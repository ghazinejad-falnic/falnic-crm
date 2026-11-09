<?php
/**
 * Plugin Name: Falnic CRM — مدیریت تریگرها و ارسال داده به CRM
 * Plugin URI: https://falnic.com/plugins/falnic-crm
 * Description: ایجاد تریگرهای دیتابیس و ارسال داده از هر جدول وردپرس (پیش‌فرض، دستی یا افزونه‌ای) به CRM فالنیک به صورت ویژوال و برنامه‌ریزی شده.
 * Version: 1.0.0
 * Author: Falnic
 * Author URI: https://falnic.com
 * Text Domain: falnic-crm
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALNIC_CRM_VERSION', '1.0.0' );
define( 'FALNIC_CRM_MIN_PHP_VERSION', '7.4' );
define( 'FALNIC_CRM_PLUGIN_FILE', __FILE__ );
define( 'FALNIC_CRM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'FALNIC_CRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FALNIC_CRM_CRON_HOOK', 'falnic_crm_process_queue' );
define( 'FALNIC_CRM_CRON_INTERVAL', 'falnic_crm_every_minute' ); // custom cron interval (60s)

// Minimum PHP version guard.
if ( version_compare( PHP_VERSION, FALNIC_CRM_MIN_PHP_VERSION, '<' ) ) {
    add_action(
        'admin_notices',
        function() {
            echo '<div class="notice notice-error"><p>' .
                esc_html__( 'افزونه Falnic CRM نیاز به PHP نسخه ' . FALNIC_CRM_MIN_PHP_VERSION . ' یا بالاتر دارد.', 'falnic-crm' ) .
                '</p></div>';
        }
    );
    return;
}

require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-core.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-settings.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-database.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-triggers.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-queue.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-api.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-processor.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-dashboard.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-notifications.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-security.php';
require_once FALNIC_CRM_PLUGIN_PATH . 'includes/class-falnic-crm-hooks.php';

function falnic_crm() {
    return Falnic_CRM_Core::instance();
}

register_activation_hook( __FILE__, 'falnic_crm_activate' );
register_deactivation_hook( __FILE__, 'falnic_crm_deactivate' );

function falnic_crm_activate() {
    Falnic_CRM_Core::instance()->activate();
}

function falnic_crm_deactivate() {
    Falnic_CRM_Core::instance()->deactivate();
}

falnic_crm();
