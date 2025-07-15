<?php
if (!defined('ABSPATH')) {
    exit;
}

// اضافه کردن صفحه داشبورد به منو
function wnw_add_dashboard_page() {
    add_menu_page(
        'وب نوتیفیکیشن',
        'وب نوتیفیکیشن',
        'manage_options',
        'web-notification-wp-dashboard',
        'wnw_dashboard_page',
        'dashicons-bell',
        30
    );
}

// محتوای صفحه داشبورد
function wnw_dashboard_page() {
    // بررسی دسترسی
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز');
    }
    
    // پردازش فرم
    // if (isset($_POST['submit'])) {
    //     wnw_process_dashboard_form();
    // }
    
    // نمایش template
    include WNW_PATH . 'templates/dashboard-template.php';
}

// پردازش فرم داشبورد
// function wnw_process_dashboard_form() {
//     // بررسی nonce
//     if (!wp_verify_nonce($_POST['dashboard_nonce'], 'wnw_dashboard_nonce')) {
//         wp_die('خطای امنیتی');
//     }
    
//     // پردازش داده‌ها
//     $dashboard_data = sanitize_text_field($_POST['dashboard_data']);
//     update_option('wnw_dashboard_data', $dashboard_data);
    
//     // پیام موفقیت
//     add_settings_error('wnw_messages', 'dashboard_updated', 'تنظیمات با موفقیت ذخیره شد.', 'updated');
// }