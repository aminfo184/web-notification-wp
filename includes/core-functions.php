<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * ثبت Rewrite Rule برای Service Worker.
 * این تابع به وردپرس می‌گوید که درخواست برای /service-worker.js را به فایل واقعی در پلاگین هدایت کند.
 */
add_action('init', 'wnw_add_service_worker_rewrite_rule');
function wnw_add_service_worker_rewrite_rule() {
    add_rewrite_rule('^service-worker\.js$', WNW_PATH . 'service-worker.js?t=532', 'top');
}

/**
 * پس از تغییر rewrite rule ها، باید آن‌ها را مجدداً بارگذاری کرد.
 * این کار را در هوک فعال‌سازی انجام می‌دهیم.
 */
function wnw_flush_rewrite_rules_on_activate() {
    wnw_add_service_worker_rewrite_rule();
    flush_rewrite_rules();
}
// این تابع باید از هوک فعال‌سازی اصلی فراخوانی شود.

/**
 * بارگذاری اسکریپت‌های لازم در فرانت‌اند سایت.
 */
add_action('wp_enqueue_scripts', 'wnw_enqueue_frontend_scripts');
function wnw_enqueue_frontend_scripts() {
    // 1. اسکریپت اصلی برای ثبت اشتراک
    wp_enqueue_script(
        'wnw-subscribe-js',
        WNW_URL . 'assets/js/wnw-subscribe.js',
        [], // بدون وابستگی
        WNW_VERSION,
        true // در فوتر بارگذاری شود
    );

    // 2. ارسال داده‌های لازم از PHP به JavaScript
    $settings = get_option('wnw_settings', []);
    $public_key = $settings['public_key'] ?? '';

    wp_localize_script(
        'wnw-subscribe-js',
        'wnw_data', // نام آبجکتی که در جاوا اسکریپت در دسترس خواهد بود
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wnw_subscribe_nonce'),
            'public_key' => $public_key,
            'is_user_logged_in' => is_user_logged_in(),
        ]
    );
}
