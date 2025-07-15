<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * این تابع جداول مورد نیاز پلاگین را در دیتابیس ایجاد می‌کند.
 * از dbDelta برای ایجاد و به‌روزرسانی امن جداول استفاده می‌شود.
 */
function wnw_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $prefix = $wpdb->prefix . 'wn_';

    // جدول 1: wn_notifications (برای ذخیره قالب‌های نوتیفیکیشن)
    $sql_notifications = "
    CREATE TABLE {$prefix}notifications (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        internal_name VARCHAR(100) NOT NULL,
        title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        message TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        url TEXT,
        image TEXT,
        status ENUM('active', 'archived') DEFAULT 'active',
        created_at DATETIME NOT NULL,
        total_sent INT UNSIGNED DEFAULT 0,
        total_failed INT UNSIGNED DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // جدول 2: wn_subscriptions (برای مدیریت اشتراک‌های کاربران)
    $sql_subscriptions = "
    CREATE TABLE {$prefix}subscriptions (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        guest_token VARCHAR(64) DEFAULT NULL,
        endpoint MEDIUMTEXT NOT NULL,
        public_key MEDIUMTEXT,
        auth_token MEDIUMTEXT,
        browser VARCHAR(100),
        os VARCHAR(100),
        ip_address VARCHAR(100),
        status ENUM('active', 'expired') DEFAULT 'active',
        created_at DATETIME NOT NULL,
        updated_at DATETIME,
        PRIMARY KEY (id),
        UNIQUE KEY endpoint_unique (endpoint(255)),
        KEY user_id_idx (user_id),
        KEY guest_token_idx (guest_token)
    ) $charset_collate;";

    // جدول 3: wn_queue (برای صف ارسال و گزارش‌گیری)
    $sql_queue = "
    CREATE TABLE {$prefix}queue (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        notification_id BIGINT(20) UNSIGNED NOT NULL,
        subscription_id BIGINT(20) UNSIGNED NOT NULL,
        status ENUM('queued', 'processing', 'sent', 'failed', 'expired') DEFAULT 'queued',
        scheduled_for DATETIME NOT NULL,
        attempts TINYINT(3) UNSIGNED DEFAULT 0,
        last_attempt_at DATETIME,
        sent_at DATETIME,
        status_message TEXT,
        PRIMARY KEY (id),
        KEY status_schedule_idx (status, scheduled_for),
        KEY notification_id_idx (notification_id),
        KEY subscription_id_idx (subscription_id)
        -- Foreign keys are not added via dbDelta, but are good for reference.
    ) $charset_collate;";

    // اجرای کوئری‌ها
    dbDelta($sql_notifications);
    dbDelta($sql_subscriptions);
    dbDelta($sql_queue);
}
