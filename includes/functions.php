<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * AJAX handler for searching users who have active subscriptions.
 */
add_action('wp_ajax_wnw_search_users', function () {
    check_ajax_referer('wnw_campaign_nonce', 'nonce');
    global $wpdb;

    $term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    if (empty($term)) {
        wp_send_json([]);
    }

    $users_table = $wpdb->users;
    $subs_table = $wpdb->prefix . 'wn_subscriptions';
    $like_term = '%' . $wpdb->esc_like($term) . '%';

    // FIX: This query now only returns users who have at least one active subscription.
    $users = $wpdb->get_results($wpdb->prepare(
        "
        SELECT DISTINCT u.ID as id, u.display_name as text
        FROM {$users_table} u
        INNER JOIN {$subs_table} sub ON u.ID = sub.user_id
        WHERE sub.status = 'active'
        AND (u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)
        LIMIT 10
        ",
        $like_term, $like_term, $like_term
    ));

    wp_send_json(['results' => $users]);
});

// AJAX: ساخت کلیدهای VAPID
function wnw_generate_vapid_keys_ajax() {
    // بررسی nonce
    if (!wp_verify_nonce($_POST['_ajax_nonce'], 'wnw_generate_vapid_keys_nonce')) {
        wp_send_json_error('خطای امنیتی - nonce نامعتبر');
        return;
    }
    
    // بررسی دسترسی
    if (!current_user_can('manage_options')) {
        wp_send_json_error('دسترسی غیرمجاز');
        return;
    }
    
    // بررسی وجود کلاس VAPID
    if (!class_exists(\Minishlink\WebPush\VAPID::class)) {
        echo 'hi';
        wp_send_json_error('کلاس VAPID یافت نشد. مطمئن شوید پکیج نصب شده است.');
        return;
    }
    
    try {
        // ساخت کلیدهای جدید
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        
        // ذخیره در تنظیمات
        $settings = get_option('wnw_settings', []);
        $settings['public_key'] = $keys['publicKey'];
        $settings['private_key'] = $keys['privateKey'];
        update_option('wnw_settings', $settings);
        
        // پاسخ موفق
        wp_send_json_success([
            'public_key' => $keys['publicKey'],
            'private_key' => $keys['privateKey'],
            'message' => 'کلیدهای VAPID با موفقیت ایجاد شدند'
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error('خطای داخلی: ' . $e->getMessage());
    }
}

// AJAX: ذخیره تنظیمات
function wnw_save_settings_ajax() {
    // بررسی nonce
    if (!wp_verify_nonce($_POST['_ajax_nonce'], 'wnw_save_settings_nonce')) {
        wp_send_json_error('خطای امنیتی - nonce نامعتبر');
        return;
    }
    
    // بررسی دسترسی
    if (!current_user_can('manage_options')) {
        wp_send_json_error('دسترسی غیرمجاز');
        return;
    }
    
    try {
        // دریافت تنظیمات فعلی
        $options = get_option('wnw_settings', []);
        
        // ذخیره ایمیل
        if (isset($_POST['email'])) {
            $email = sanitize_email($_POST['email']);
            if (!is_email($email) && !empty($email)) {
                wp_send_json_error('ایمیل وارد شده معتبر نیست');
                return;
            }
            $options['email'] = $email;
        }
        
        // ذخیره آیکون
        if (isset($_POST['icon'])) {
            $icon = esc_url_raw($_POST['icon']);
            
            // اگر آیکون خالی باشد، از آیکون سایت استفاده کن
            if (empty($icon)) {
                $icon_id = get_option('site_icon');
                $icon = $icon_id ? wp_get_attachment_image_url($icon_id, 'full') : '';
            }
            
            $options['icon'] = $icon;
        }
        
        // ذخیره تنظیمات
        update_option('wnw_settings', $options);
        
        wp_send_json_success('تنظیمات با موفقیت ذخیره شد');
        
    } catch (Exception $e) {
        wp_send_json_error('خطای داخلی: ' . $e->getMessage());
    }
}
