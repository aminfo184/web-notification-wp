<?php

/**
 * Plugin Name:       Web Notification WP
 * Plugin URI:        https://zhinotech.com/
 * Description:       ارسال پوش نوتیفیکیشن در وردپرس با قابلیت‌های پیشرفته.
 * Version:           1.0.0ش
 * Author:            ZHinotech
 * Author URI:        https://zhinotech.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       web-notification-wp
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// 1. Define Constants
define('WNW_VERSION', '1.0.0');
define('WNW_PATH', plugin_dir_path(__FILE__));
define('WNW_URL', plugin_dir_url(__FILE__));
define('WNW_FILE', __FILE__);

// 2. Load Composer Autoloader
if (file_exists(WNW_PATH . 'vendor/autoload.php')) {
    require_once WNW_PATH . 'vendor/autoload.php';
}

// 3. Register Activation/Deactivation Hooks
register_activation_hook(WNW_FILE, 'wnw_plugin_activate');
register_deactivation_hook(WNW_FILE, 'wnw_plugin_deactivate');

/**
 * Plugin activation function.
 */
function wnw_plugin_activate()
{
    require_once WNW_PATH . 'includes/db-schema.php';
    wnw_create_tables();

    // حذف cron قدیمی و استفاده از سیستم جدید
    // if (!wp_next_scheduled('wnw_process_queue_hook')) {
    //     wp_schedule_event(time(), 'every_minute', 'wnw_process_queue_hook');
    // }

    require_once WNW_PATH . 'includes/core-functions.php';
    wnw_flush_rewrite_rules_on_activate();
}

/**
 * Plugin deactivation function.
 */
function wnw_plugin_deactivate()
{
    // پاک کردن همه cron jobs مربوط به پلاگین
    $timestamp = wp_next_scheduled('wnw_process_queue_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'wnw_process_queue_hook');
    }

    wp_clear_scheduled_hook('wnw_background_process_step');

    // متوقف کردن پردازش پس‌زمینه
    update_option('wnw_process_status', 'stopped');

    flush_rewrite_rules();
}

/**
 * Load all plugin files.
 */
function wnw_load_plugin_files()
{
    // Load include files
    $includes = [
        'api-routes.php',
        'core-functions.php',
        'background-processor.php',
        'cron-handler.php',
        'functions.php',
        'settings.php',
        'subscription-handler.php',
    ];
    foreach ($includes as $file) {
        require_once WNW_PATH . 'includes/' . $file;
    }

    // Load admin pages if in admin area
    if (is_admin()) {
        $admin_pages = [
            'dashboard-page.php',
            'settings-page.php',
            'notification-page.php',
            'subscribers-page.php',
            'campaign-page.php'
        ];
        foreach ($admin_pages as $page) {
            require_once WNW_PATH . 'admin/' . $page;
        }
    }
}
add_action('plugins_loaded', 'wnw_load_plugin_files');

/**
 * Add custom cron interval.
 */
add_filter('cron_schedules', 'wnw_add_cron_interval');
function wnw_add_cron_interval($schedules)
{
    if (!isset($schedules['every_minute'])) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => __('Every Minute'),
        ];
    }
    return $schedules;
}

// راه‌اندازی پلاگین
add_action('plugins_loaded', 'wnw_init');
function wnw_init()
{
    // فقط در ادمین
    if (is_admin()) {
        wnw_admin_init();
    }
}

// راه‌اندازی بخش ادمین
function wnw_admin_init()
{
    add_action('admin_menu', 'wnw_admin_menu');
}

function wnw_admin_menu()
{
    // صفحه اصلی
    wnw_add_dashboard_page();

    // صفحات فرعی
    wnw_add_notification_page();
    wnw_add_subscribers_page();
    wnw_add_campaign_page();
    wnw_add_settings_page();
}
