<?php
if (!defined('ABSPATH')) {
    exit;
}

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * سیستم جدید پردازش پس‌زمینه صف ارسال نوتیفیکیشن
 * جایگزین سیستم wp-cron
 */
class WNW_Background_Processor {
    
    private static $instance = null;
    private $is_processing = false;
    private $process_lock_key = 'wnw_process_lock';
    private $process_status_key = 'wnw_process_status';
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // افزودن AJAX handlers
        add_action('wp_ajax_wnw_start_processing', [$this, 'ajax_start_processing']);
        add_action('wp_ajax_wnw_stop_processing', [$this, 'ajax_stop_processing']);
        add_action('wp_ajax_wnw_process_queue_step', [$this, 'ajax_process_queue_step']);
        add_action('wp_ajax_wnw_get_process_status', [$this, 'ajax_get_process_status']);
        
        // شروع خودکار پردازش در صورت وجود آیتم‌های در انتظار
        add_action('init', [$this, 'auto_start_if_needed']);
    }
    
    /**
     * شروع خودکار پردازش در صورت نیاز
     */
    public function auto_start_if_needed() {
        if (!$this->is_processing_active() && $this->has_pending_items()) {
            $this->start_background_processing();
        }
    }
    
    /**
     * بررسی وجود آیتم‌های در انتظار ارسال
     */
    private function has_pending_items() {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wn_queue 
             WHERE status = 'queued' AND scheduled_for <= %s",
            current_time('mysql')
        ));
        return $count > 0;
    }
    
    /**
     * بررسی وضعیت فعال بودن پردازش
     */
    public function is_processing_active() {
        $status = get_option($this->process_status_key, 'stopped');
        return $status === 'running';
    }
    
    /**
     * تنظیم وضعیت پردازش
     */
    private function set_process_status($status) {
        update_option($this->process_status_key, $status);
        update_option($this->process_status_key . '_time', current_time('mysql'));
    }
    
    /**
     * شروع پردازش پس‌زمینه
     */
    public function start_background_processing() {
        if ($this->is_processing_active()) {
            return false; // در حال پردازش است
        }
        
        $this->set_process_status('running');
        $this->schedule_next_process();
        return true;
    }
    
    /**
     * توقف پردازش
     */
    public function stop_processing() {
        $this->set_process_status('stopped');
        wp_clear_scheduled_hook('wnw_background_process_step');
        return true;
    }
    
    /**
     * برنامه‌ریزی مرحله بعدی پردازش
     */
    private function schedule_next_process() {
        if (!$this->is_processing_active()) {
            return;
        }
        
        // برنامه‌ریزی برای 60 ثانیه بعد (قابل تنظیم)
        if (!wp_next_scheduled('wnw_background_process_step')) {
            wp_schedule_single_event(time() + 60, 'wnw_background_process_step');
        }
        
        // ارسال درخواست AJAX برای پردازش فوری
        $this->trigger_ajax_process();
    }
    
    /**
     * ارسال درخواست AJAX برای پردازش
     */
    private function trigger_ajax_process() {
        $url = admin_url('admin-ajax.php');
        $data = [
            'action' => 'wnw_process_queue_step',
            'nonce' => wp_create_nonce('wnw_process_nonce'),
            'background' => 1
        ];
        
        wp_remote_post($url, [
            'timeout' => 0.01,
            'blocking' => false,
            'body' => $data,
            'sslverify' => false
        ]);
    }
    
    /**
     * پردازش یک دسته از صف
     */
    public function process_queue_batch() {
        global $wpdb;
        
        if (!$this->is_processing_active()) {
            return false;
        }
        
        // گرفتن تنظیمات
        $settings = get_option('wnw_settings', []);
        $batch_size = intval($settings['batch_size'] ?? 100);
        
        if ($batch_size <= 0) {
            $batch_size = 10; // حداقل تعداد
        }
        
        // گرفتن آیتم‌های آماده ارسال
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wn_queue 
             WHERE status = 'queued' AND scheduled_for <= %s 
             ORDER BY scheduled_for ASC, id ASC
             LIMIT %d",
            current_time('mysql'),
            $batch_size
        ));
        
        if (empty($items)) {
            // صف خالی است، متوقف کردن پردازش
            $this->stop_processing();
            return false;
        }
        
        // پردازش آیتم‌ها
        $processed = $this->process_items($items);
        
        // برنامه‌ریزی مرحله بعدی
        if ($this->has_pending_items()) {
            $this->schedule_next_process();
        } else {
            $this->stop_processing();
        }
        
        return $processed;
    }
    
    /**
     * پردازش لیست آیتم‌ها
     */
    private function process_items($items) {
        global $wpdb;
        
        $settings = get_option('wnw_settings', []);
        $auth = [
            'VAPID' => [
                'subject' => $settings['email'] ?? get_option('admin_email'),
                'publicKey' => $settings['public_key'] ?? '',
                'privateKey' => $settings['private_key'] ?? '',
            ],
        ];
        
        if (empty($auth['VAPID']['publicKey']) || empty($auth['VAPID']['privateKey'])) {
            // به‌روزرسانی وضعیت آیتم‌های ناموفق
            $item_ids = wp_list_pluck($items, 'id');
            $ids_placeholder = implode(',', array_fill(0, count($item_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}wn_queue SET status = 'failed', status_message = 'کلیدهای VAPID تنظیم نشده' WHERE id IN ({$ids_placeholder})",
                ...$item_ids
            ));
            return false;
        }
        
        $webPush = new WebPush($auth);
        $webPush->setReuseVAPIDHeaders(true);
        
        // آماده‌سازی نوتیفیکیشن‌ها
        $notification_cache = [];
        $subscription_cache = [];
        
        foreach ($items as $item) {
            // بروزرسانی وضعیت به در حال پردازش
            $wpdb->update(
                $wpdb->prefix . 'wn_queue',
                ['status' => 'processing', 'last_attempt_at' => current_time('mysql')],
                ['id' => $item->id]
            );
            
            // گرفتن اطلاعات نوتیفیکیشن
            if (!isset($notification_cache[$item->notification_id])) {
                $notification_cache[$item->notification_id] = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wn_notifications WHERE id = %d",
                    $item->notification_id
                ));
            }
            
            // گرفتن اطلاعات اشتراک
            if (!isset($subscription_cache[$item->subscription_id])) {
                $subscription_cache[$item->subscription_id] = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wn_subscriptions WHERE id = %d AND status = 'active'",
                    $item->subscription_id
                ));
            }
            
            $notification = $notification_cache[$item->notification_id];
            $subscription = $subscription_cache[$item->subscription_id];
            
            if (!$notification || !$subscription) {
                $wpdb->update(
                    $wpdb->prefix . 'wn_queue',
                    ['status' => 'failed', 'status_message' => 'نوتیفیکیشن یا اشتراک یافت نشد'],
                    ['id' => $item->id]
                );
                continue;
            }
            
            // آماده‌سازی محتوای نوتیفیکیشن
            $payload = json_encode([
                'title' => $notification->title,
                'body' => $notification->message,
                'icon' => $notification->icon ?? ($settings['icon'] ?? ''),
                'url' => $notification->url,
                'image' => $notification->image,
            ]);
            
            // ایجاد subscription object
            $webpush_subscription = Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
            ]);
            
            // افزودن به صف ارسال
            $webPush->queueNotification(
                $webpush_subscription,
                $payload,
                [
                    'TTL' => $settings['ttl'] ?? 2419200,
                    'urgency' => $settings['urgency'] ?? 'normal'
                ]
            );
        }
        
        // ارسال همه نوتیفیکیشن‌ها
        $reports = $webPush->flush();
        
        // پردازش نتایج
        $this->process_send_reports($reports, $items);
        
        return count($items);
    }
    
    /**
     * پردازش نتایج ارسال
     */
    private function process_send_reports($reports, $items) {
        global $wpdb;
        
        foreach ($reports as $report) {
            $endpoint = $report->getEndpoint();
            
            // یافتن subscription_id
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wn_subscriptions WHERE endpoint = %s",
                $endpoint
            ));
            
            if (!$subscription) continue;
            
            // یافتن queue item
            $queue_item = null;
            foreach ($items as $item) {
                if ($item->subscription_id == $subscription->id) {
                    $queue_item = $item;
                    break;
                }
            }
            
            if (!$queue_item) continue;
            
            // به‌روزرسانی وضعیت
            $update_data = [];
            if ($report->isSuccess()) {
                $update_data['status'] = 'sent';
                $update_data['sent_at'] = current_time('mysql');
                $update_data['status_message'] = 'ارسال موفق';
                
                // افزایش شمارنده ارسال موفق
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}wn_notifications SET total_sent = total_sent + 1 WHERE id = %d",
                    $queue_item->notification_id
                ));
            } else {
                $update_data['status'] = 'failed';
                $update_data['status_message'] = $report->getReason();
                
                // افزایش شمارنده ارسال ناموفق
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}wn_notifications SET total_failed = total_failed + 1 WHERE id = %d",
                    $queue_item->notification_id
                ));
            }
            
            // بررسی انقضای اشتراک
            if ($report->isSubscriptionExpired()) {
                $update_data['status'] = 'expired';
                $wpdb->update(
                    $wpdb->prefix . 'wn_subscriptions',
                    ['status' => 'expired'],
                    ['id' => $subscription->id]
                );
            }
            
            // به‌روزرسانی queue item
            $wpdb->update(
                $wpdb->prefix . 'wn_queue',
                $update_data,
                ['id' => $queue_item->id]
            );
        }
    }
    
    /**
     * AJAX Handler: شروع پردازش
     */
    public function ajax_start_processing() {
        check_ajax_referer('wnw_process_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        $result = $this->start_background_processing();
        
        if ($result) {
            wp_send_json_success('پردازش شروع شد');
        } else {
            wp_send_json_error('پردازش در حال اجرا است');
        }
    }
    
    /**
     * AJAX Handler: توقف پردازش
     */
    public function ajax_stop_processing() {
        check_ajax_referer('wnw_process_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        $this->stop_processing();
        wp_send_json_success('پردازش متوقف شد');
    }
    
    /**
     * AJAX Handler: پردازش یک مرحله
     */
    public function ajax_process_queue_step() {
        // بررسی nonce فقط اگر از داخل ادمین باشد
        if (!isset($_POST['background'])) {
            check_ajax_referer('wnw_process_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('دسترسی غیرمجاز');
            }
        }
        
        $processed = $this->process_queue_batch();
        
        if ($processed !== false) {
            wp_send_json_success([
                'processed' => $processed,
                'status' => $this->is_processing_active() ? 'running' : 'stopped'
            ]);
        } else {
            wp_send_json_error('خطا در پردازش');
        }
    }
    
    /**
     * AJAX Handler: دریافت وضعیت پردازش
     */
    public function ajax_get_process_status() {
        check_ajax_referer('wnw_process_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        global $wpdb;
        
        $status = $this->is_processing_active() ? 'running' : 'stopped';
        $last_update = get_option($this->process_status_key . '_time');
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$wpdb->prefix}wn_queue"
        );
        
        wp_send_json_success([
            'status' => $status,
            'last_update' => $last_update,
            'stats' => $stats
        ]);
    }
}

// راه‌اندازی کلاس
add_action('init', function() {
    WNW_Background_Processor::getInstance();
});

// Hook برای wp-cron به عنوان backup
add_action('wnw_background_process_step', function() {
    $processor = WNW_Background_Processor::getInstance();
    $processor->process_queue_batch();
});