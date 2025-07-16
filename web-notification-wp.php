<?php

/**
 * Plugin Name:       Web Notification WP
 * Plugin URI:        https://zhinotech.com/
 * Description:       ارسال پوش نوتیفیکیشن در وردپرس با قابلیت‌های پیشرفته.
 * Version:           1.1.0
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

// ۱. تعریف ثابت‌های پلاگین
define('WNW_VERSION', '1.1.0');
define('WNW_PATH', plugin_dir_path(__FILE__));
define('WNW_URL', plugin_dir_url(__FILE__));

// ۲. بارگذاری Composer Autoloader
if (file_exists(WNW_PATH . 'vendor/autoload.php')) {
    require_once WNW_PATH . 'vendor/autoload.php';
}

// ۳. بارگذاری تمام فایل‌های اصلی پلاگین
// این فایل‌ها همیشه، چه در پیشخوان و چه در فرانت‌اند، مورد نیاز هستند.
require_once WNW_PATH . 'includes/core-functions.php';
require_once WNW_PATH . 'includes/db-schema.php';
require_once WNW_PATH . 'includes/subscription-handler.php';
require_once WNW_PATH . 'includes/api-routes.php';
require_once WNW_PATH . 'includes/cron-handler.php';
require_once WNW_PATH . 'includes/background-processor.php';
require_once WNW_PATH . 'includes/functions.php';

// ۴. ثبت هوک‌های فعال‌سازی و غیرفعال‌سازی
register_activation_hook(__FILE__, 'wnw_plugin_activate');
register_deactivation_hook(__FILE__, 'wnw_plugin_deactivate');

function wnw_plugin_activate() {
    wnw_create_tables();
    flush_rewrite_rules();
}

function wnw_plugin_deactivate() {
    flush_rewrite_rules();
}

// ۵. بارگذاری فایل‌های مربوط به پیشخوان فقط در صورت نیاز
if (is_admin()) {
    require_once WNW_PATH . 'admin/dashboard-page.php';
    require_once WNW_PATH . 'admin/settings-page.php';
    require_once WNW_PATH . 'admin/notification-page.php';
    require_once WNW_PATH . 'admin/subscribers-page.php';
    require_once WNW_PATH . 'admin/campaign-page.php';

    add_action('admin_menu', 'wnw_admin_menu');
    function wnw_admin_menu() {
        wnw_add_dashboard_page();
        wnw_add_notification_page();
        wnw_add_subscribers_page();
        wnw_add_campaign_page();
        wnw_add_settings_page();
    }
}