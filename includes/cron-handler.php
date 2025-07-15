<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * سیستم Cron Handler (Legacy Support + Integration)
 * این فایل برای سازگاری با سیستم قدیمی و تکمیل سیستم جدید نگه داری می‌شود
 */

// --- Helper functions for locking ---
function wnw_is_processing_locked() {
    return get_transient('wnw_queue_processing_lock');
}
function wnw_lock_processing() {
    set_transient('wnw_queue_processing_lock', true, MINUTE_IN_SECONDS * 5); // Lock for max 5 mins
}
function wnw_unlock_processing() {
    delete_transient('wnw_queue_processing_lock');
}

// --- Hook برای wp-cron (backup) ---
add_action('wnw_process_queue_hook', 'wnw_process_notification_queue_legacy');

/**
 * Legacy queue processor - فقط در صورتی که سیستم جدید کار نکند
 */
function wnw_process_notification_queue_legacy() {
    // بررسی اینکه آیا سیستم جدید فعال است
    $new_processor_status = get_option('wnw_process_status', 'stopped');
    if ($new_processor_status === 'running') {
        return; // سیستم جدید در حال کار است، کاری نکن
    }
    
    // اگر سیستم جدید کار نمی‌کند، از پردازشگر جدید استفاده کن
    if (class_exists('WNW_Background_Processor')) {
        $processor = WNW_Background_Processor::getInstance();
        $processor->process_queue_batch();
    } else {
        // fallback به سیستم قدیمی
        wnw_legacy_process_queue();
    }
}

/**
 * سیستم قدیمی (Fallback)
 */
function wnw_legacy_process_queue() {
    // 1. Check for lock
    if (wnw_is_processing_locked()) {
        return; // Another process is already running.
    }

    // 2. Set the lock
    wnw_lock_processing();

    global $wpdb;
    $queue_table = $wpdb->prefix . 'wn_queue';
    $notif_table = $wpdb->prefix . 'wn_notifications';
    $subs_table = $wpdb->prefix . 'wn_subscriptions';

    $settings = get_option('wnw_settings', []);
    $batch_size = $settings['batch_size'] ?? 100;
    
    // 3. Main processing loop: Runs until the queue is empty.
    do {
        $items_to_process = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$queue_table} WHERE status = 'queued' AND scheduled_for <= %s ORDER BY id ASC LIMIT %d",
            current_time('mysql'),
            $batch_size
        ));

        if (empty($items_to_process)) {
            break; // Exit the loop if nothing is left to process
        }

        // استفاده از تابع جدید برای پردازش
        if (function_exists('wnw_send_instant_notification_from_queue')) {
            wnw_send_instant_notification_from_queue($items_to_process);
        } else {
            // fallback به کد قدیمی
            wnw_legacy_process_items($items_to_process);
        }

    } while (!empty($items_to_process));

    // 4. Release the lock
    wnw_unlock_processing();
}

/**
 * پردازش legacy آیتم‌ها
 */
function wnw_legacy_process_items($items) {
    global $wpdb;
    
    $settings = get_option('wnw_settings', []);
    $queue_table = $wpdb->prefix . 'wn_queue';
    $notif_table = $wpdb->prefix . 'wn_notifications';
    $subs_table = $wpdb->prefix . 'wn_subscriptions';
    
    // Lock these specific items
    $item_ids = wp_list_pluck($items, 'id');
    $ids_placeholder = implode(',', array_fill(0, count($item_ids), '%d'));
    $wpdb->query($wpdb->prepare("UPDATE {$queue_table} SET status = 'processing', last_attempt_at = %s WHERE id IN ({$ids_placeholder})", current_time('mysql'), ...$item_ids));

    $auth = [
        'VAPID' => [
            'subject' => $settings['email'] ?? get_option('admin_email'),
            'publicKey' => $settings['public_key'] ?? '',
            'privateKey' => $settings['private_key'] ?? '',
        ],
    ];

    if (empty($auth['VAPID']['publicKey']) || empty($auth['VAPID']['privateKey'])) {
        $wpdb->query($wpdb->prepare("UPDATE {$queue_table} SET status = 'failed', status_message = 'VAPID keys not configured.' WHERE id IN ({$ids_placeholder})", ...$item_ids));
        return;
    }

    $webPush = new Minishlink\WebPush\WebPush($auth);
    $webPush->setReuseVAPIDHeaders(true);

    foreach ($items as $item) {
        $notification_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$notif_table} WHERE id = %d", $item->notification_id));
        $subscription_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$subs_table} WHERE id = %d", $item->subscription_id));

        if (!$notification_data || !$subscription_data) {
            $wpdb->update($queue_table, ['status' => 'failed', 'status_message' => 'Template or subscription not found.'], ['id' => $item->id]);
            continue;
        }

        $payload = json_encode([
            'title' => $notification_data->title,
            'body'  => $notification_data->message,
            'icon'  => $notification_data->icon ?? ($settings['icon'] ?? ''),
            'url'   => $notification_data->url,
            'image' => $notification_data->image,
        ]);

        $subscription = Minishlink\WebPush\Subscription::create([
            'endpoint' => $subscription_data->endpoint,
            'publicKey' => $subscription_data->public_key,
            'authToken' => $subscription_data->auth_token,
        ]);

        $webPush->queueNotification(
            $subscription,
            $payload,
            ['TTL' => $settings['ttl'] ?? 2419200, 'urgency' => $settings['urgency'] ?? 'normal']
        );
    }

    $reports = $webPush->flush();

    foreach ($reports as $report) {
        $endpoint = $report->getEndpoint();
        $sub_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$subs_table} WHERE endpoint = %s", $endpoint));
        $queue_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$queue_table} WHERE subscription_id = %d AND status = 'processing'", $sub_id));

        if (!$queue_item) continue;

        $update_data = [];
        if ($report->isSuccess()) {
            $update_data['status'] = 'sent';
            $update_data['sent_at'] = current_time('mysql');
            $update_data['status_message'] = 'Success';
            $wpdb->query($wpdb->prepare("UPDATE {$notif_table} SET total_sent = total_sent + 1 WHERE id = %d", $queue_item->notification_id));
        } else {
            $update_data['status'] = 'failed';
            $update_data['status_message'] = $report->getReason();
            $wpdb->query($wpdb->prepare("UPDATE {$notif_table} SET total_failed = total_failed + 1 WHERE id = %d", $queue_item->notification_id));
        }

        if ($report->isSubscriptionExpired()) {
            $update_data['status'] = 'expired';
            $wpdb->update($subs_table, ['status' => 'expired'], ['id' => $sub_id]);
        }

        $wpdb->update($queue_table, $update_data, ['id' => $queue_item->id]);
    }
}

/**
 * Trigger برای شروع پردازش از سیستم جدید
 */
function wnw_trigger_new_processor() {
    if (class_exists('WNW_Background_Processor')) {
        $processor = WNW_Background_Processor::getInstance();
        $processor->start_background_processing();
    }
}

/**
 * تابع کمکی برای اضافه کردن آیتم به صف
 */
function wnw_add_to_queue($notification_id, $subscription_ids, $scheduled_for = null) {
    global $wpdb;
    
    if (!$scheduled_for) {
        $scheduled_for = current_time('mysql');
    }
    
    $queue_table = $wpdb->prefix . 'wn_queue';
    $added = 0;
    
    foreach ($subscription_ids as $subscription_id) {
        $result = $wpdb->insert(
            $queue_table,
            [
                'notification_id' => $notification_id,
                'subscription_id' => $subscription_id,
                'status' => 'queued',
                'scheduled_for' => $scheduled_for,
                'attempts' => 0
            ],
            ['%d', '%d', '%s', '%s', '%d']
        );
        
        if ($result) {
            $added++;
        }
    }
    
    // اگر آیتم جدید اضافه شد، سیستم جدید را فعال کن
    if ($added > 0) {
        wnw_trigger_new_processor();
    }
    
    return $added;
}

/**
 * Immediately triggers the background process via a non-blocking request.
 */
function wnw_spawn_runner() {
    // ابتدا سعی کن سیستم جدید را فعال کنی
    wnw_trigger_new_processor();
    
    // سپس fallback به سیستم قدیمی
    $url = add_query_arg([
        'action' => 'wnw_process_queue_hook',
        '_wpnonce' => wp_create_nonce('wnw_cron_nonce')
    ], admin_url('admin-ajax.php'));

    // Use a non-blocking request to prevent the user from waiting.
    wp_remote_post($url, [
        'blocking'  => false,
        'sslverify' => apply_filters('https_local_ssl_verify', false),
    ]);
}

// Add a security check for the spawned runner
add_action('wp_ajax_nopriv_wnw_process_queue_hook', function() {
    if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'wnw_cron_nonce')) {
        wp_die('Security check failed.');
    }
    do_action('wnw_process_queue_hook');
    wp_die();
});

/**
 * تابع جدید برای پردازش آیتم‌های صف از طریق سیستم جدید
 */
function wnw_send_instant_notification_from_queue($queue_items) {
    if (empty($queue_items)) {
        return false;
    }
    
    global $wpdb;
    
    // گروه‌بندی آیتم‌ها بر اساس notification_id
    $notifications = [];
    foreach ($queue_items as $item) {
        if (!isset($notifications[$item->notification_id])) {
            $notifications[$item->notification_id] = [];
        }
        $notifications[$item->notification_id][] = $item;
    }
    
    // پردازش هر گروه نوتیفیکیشن
    foreach ($notifications as $notification_id => $items) {
        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wn_notifications WHERE id = %d",
            $notification_id
        ));
        
        if (!$notification) {
            continue;
        }
        
        $notification_data = [
            'title' => $notification->title,
            'message' => $notification->message,
            'url' => $notification->url,
            'image' => $notification->image
        ];
        
        $subscription_ids = wp_list_pluck($items, 'subscription_id');
        
        // ارسال از طریق سیستم جدید
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$wpdb->prefix}wn_subscriptions 
             WHERE id IN (" . implode(',', array_fill(0, count($subscription_ids), '%d')) . ")",
            ...$subscription_ids
        ));
        
        if (!empty($user_ids)) {
            $result = wnw_send_instant_notification($user_ids, $notification_data);
            
            // به‌روزرسانی وضعیت آیتم‌های صف
            foreach ($items as $item) {
                $wpdb->update(
                    $wpdb->prefix . 'wn_queue',
                    [
                        'status' => $result['success'] ? 'sent' : 'failed',
                        'sent_at' => $result['success'] ? current_time('mysql') : null,
                        'status_message' => $result['success'] ? 'ارسال موفق' : $result['message']
                    ],
                    ['id' => $item->id]
                );
            }
        }
    }
    
    return true;
}