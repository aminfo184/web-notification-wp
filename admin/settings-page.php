<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * اضافه کردن صفحه "تنظیمات" به منوی اصلی پلاگین.
 */
function wnw_add_settings_page() {
    add_submenu_page(
        'web-notification-wp-dashboard',
        'تنظیمات',
        'تنظیمات',
        'manage_options',
        'wnw-settings',
        'wnw_settings_page_render'
    );
}

/**
 * رندر کردن محتوای صفحه تنظیمات.
 */
function wnw_settings_page_render() {
    if (!current_user_can('manage_options')) {
        wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
    }

    wp_enqueue_media();

    $options = get_option('wnw_settings', []);

    // آماده‌سازی داده‌ها برای ارسال به فایل قالب
    $data_to_pass = [
        'public_key'  => $options['public_key'] ?? '',
        'private_key' => $options['private_key'] ?? '',
        'email'       => $options['email'] ?? get_option('admin_email'),
        'icon'        => $options['icon'] ?? get_site_icon_url(192),
        'ttl'         => $options['ttl'] ?? 2419200,
        'urgency'     => $options['urgency'] ?? 'normal',
        'batch_size'  => $options['batch_size'] ?? 100,
        'gcm_key'     => $options['gcm_key'] ?? '',
    ];

    require_once WNW_PATH . 'templates/settings-template.php';
}

/**
 * NEW: A single AJAX handler for all settings page actions.
 * این تابع واحد تمام درخواست‌های فرم تنظیمات را مدیریت می‌کند.
 */
add_action('wp_ajax_wnw_handle_settings_actions', 'wnw_ajax_handle_settings_actions');
function wnw_ajax_handle_settings_actions() {
    // 1. بررسی Nonce در ابتدای کار
    check_ajax_referer('wnw_settings_action', 'wnw_nonce_field');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    // 2. تشخیص اینکه کدام دکمه کلیک شده است
    $action = $_POST['submit_action'] ?? '';

    switch ($action) {
        case 'save_settings':
            wnw_ajax_save_settings_logic();
            break;
        case 'generate_keys':
            wnw_ajax_generate_keys_logic();
            break;
        default:
            wp_send_json_error(['message' => 'عملیات نامعتبر.']);
    }
}

/**
 * منطق داخلی برای ساخت کلیدها.
 * این تابع دیگر یک AJAX handler مستقیم نیست.
 */
function wnw_ajax_generate_keys_logic() {
    if (!class_exists('\Minishlink\WebPush\VAPID')) {
        wp_send_json_error(['message' => 'کتابخانه WebPush یافت نشد.']);
    }

    try {
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        
        $options = get_option('wnw_settings', []);
        $options['public_key'] = $keys['publicKey'];
        $options['private_key'] = $keys['privateKey'];
        
        update_option('wnw_settings', $options);
        
        wp_send_json_success([
            'message'    => 'کلیدهای جدید VAPID با موفقیت ساخته و ذخیره شدند.',
            'publicKey'  => $keys['publicKey'],
            'privateKey' => $keys['privateKey'],
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'خطا در ساخت کلیدها: ' . $e->getMessage()]);
    }
}

/**
 * منطق داخلی برای ذخیره تنظیمات.
 * این تابع دیگر یک AJAX handler مستقیم نیست.
 */
function wnw_ajax_save_settings_logic() {
    $options = get_option('wnw_settings', []);
    $posted_data = $_POST['settings'] ?? [];

    if (isset($posted_data['email'])) {
        $email = sanitize_email($posted_data['email']);
        if (!empty($email) && !is_email($email)) {
            wp_send_json_error(['message' => 'لطفاً یک ایمیل معتبر وارد کنید.']);
        }
        $options['email'] = $email;
    }

    if (isset($posted_data['icon'])) {
        $icon_url = esc_url_raw($posted_data['icon']);
        $options['icon'] = !empty($icon_url) ? $icon_url : get_site_icon_url(192);
    }

    if (isset($posted_data['ttl'])) {
        $options['ttl'] = absint($posted_data['ttl']);
    }

    if (isset($posted_data['urgency']) && in_array($posted_data['urgency'], ['high', 'normal', 'low'])) {
        $options['urgency'] = sanitize_text_field($posted_data['urgency']);
    }

    if (isset($posted_data['batch_size'])) {
        $options['batch_size'] = absint($posted_data['batch_size']);
    }
    
    if (isset($posted_data['gcm_key'])) {
        $options['gcm_key'] = sanitize_text_field($posted_data['gcm_key']);
    }

    update_option('wnw_settings', $options);

    wp_send_json_success(['message' => 'تنظیمات با موفقیت ذخیره شد.', 'new_icon' => $options['icon']]);
}
