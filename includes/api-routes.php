<?php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * ثبت API routes برای پلاگین
 */
add_action('rest_api_init', function () {
    // API ارسال به کاربر مشخص
    register_rest_route('wnw/v1', '/send/user', [
        'methods' => 'POST',
        'callback' => 'wnw_api_send_to_user',
        'permission_callback' => 'wnw_api_permission_check',
        'args' => [
            'user_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                }
            ],
            'template_id' => [
                'required' => false,
            ],
            'title' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'message' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_textarea_field'
            ],
            'url' => [
                'required' => false,
                'sanitize_callback' => 'esc_url_raw'
            ],
            'image' => [
                'required' => false,
                'sanitize_callback' => 'esc_url_raw'
            ]
        ],
    ]);

    // API ارسال با محتوای سفارشی
    register_rest_route('wnw/v1', '/send/custom', [
        'methods' => 'POST',
        'callback' => 'wnw_api_send_custom',
        'permission_callback' => 'wnw_api_permission_check',
        'args' => [
            'title' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'message' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_textarea_field'
            ],
            'user_ids' => [
                'required' => false,
                'default' => 'all'
            ],
            'url' => [
                'required' => false,
                'sanitize_callback' => 'esc_url_raw'
            ],
            'image' => [
                'required' => false,
                'sanitize_callback' => 'esc_url_raw'
            ]
        ],
    ]);

    // API تست برای دیباگ
    register_rest_route('wnw/v1', '/test', [
        'methods' => 'GET',
        'callback' => 'wnw_api_test',
        'permission_callback' => '__return_true',
    ]);

    // API debug برای بررسی subscriptions
    register_rest_route('wnw/v1', '/debug', [
        'methods' => 'GET',
        'callback' => 'wnw_api_debug',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * بررسی مجوز API
 */
function wnw_api_permission_check(WP_REST_Request $request) {
    return true;
}

/**
 * API تست
 */
function wnw_api_test(WP_REST_Request $request) {
    global $wpdb;
    
    $settings = get_option('wnw_settings', []);
    $subscribers_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wn_subscriptions WHERE status = 'active'"
    );
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'API working correctly',
        'settings_configured' => !empty($settings['public_key']) && !empty($settings['private_key']),
        'subscribers_count' => $subscribers_count,
        'timestamp' => current_time('mysql'),
        'web_push_class_exists' => class_exists('Minishlink\\WebPush\\WebPush'),
        'settings' => [
            'has_public_key' => !empty($settings['public_key']),
            'has_private_key' => !empty($settings['private_key']),
            'email' => $settings['email'] ?? 'not set',
            'icon' => $settings['icon'] ?? 'not set'
        ]
    ], 200);
}

/**
 * API debug - بررسی subscriptions
 */
function wnw_api_debug(WP_REST_Request $request) {
    global $wpdb;
    
    $subscriptions = $wpdb->get_results(
        "SELECT id, user_id, endpoint, status, browser, created_at FROM {$wpdb->prefix}wn_subscriptions ORDER BY created_at DESC LIMIT 10"
    );
    
    $result = [];
    foreach ($subscriptions as $sub) {
        $result[] = [
            'id' => $sub->id,
            'user_id' => $sub->user_id,
            'endpoint_preview' => substr($sub->endpoint, 0, 50) . '...',
            'status' => $sub->status,
            'browser' => $sub->browser,
            'created_at' => $sub->created_at
        ];
    }
    
    return new WP_REST_Response([
        'success' => true,
        'subscriptions' => $result,
        'total_active' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wn_subscriptions WHERE status = 'active'")
    ], 200);
}

/**
 * ارسال نوتیفیکیشن به کاربر مشخص
 */
function wnw_api_send_to_user(WP_REST_Request $request) {
    $user_id = $request->get_param('user_id');
    $template_id = $request->get_param('template_id');
    
    // تعیین محتوای نوتیفیکیشن
    if ($template_id) {
        $notification_data = wnw_get_notification_data_by_template($template_id);
        if (!$notification_data) {
            return new WP_REST_Response([
                'success' => false, 
                'message' => 'قالب نوتیفیکیشن یافت نشد.'
            ], 404);
        }
    } else {
        $notification_data = [
            'title' => $request->get_param('title') ?: 'عنوان پیش‌فرض',
            'message' => $request->get_param('message') ?: 'پیام پیش‌فرض',
            'url' => $request->get_param('url') ?: home_url(),
            'image' => $request->get_param('image') ?: ''
        ];
    }
    
    // ارسال نوتیفیکیشن
    $result = wnw_send_instant_notification_improved([$user_id], $notification_data);
    
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

/**
 * ارسال نوتیفیکیشن با محتوای سفارشی
 */
function wnw_api_send_custom(WP_REST_Request $request) {
    $user_ids = $request->get_param('user_ids');
    
    // تعیین کاربران هدف
    if ($user_ids === 'all') {
        $user_ids = wnw_get_all_subscribed_users();
    } elseif (is_array($user_ids) && count($user_ids) > 100) {
        return new WP_REST_Response([
            'success' => false, 
            'message' => 'حداکثر 100 کاربر در هر درخواست مجاز است.'
        ], 400);
    }
    
    $notification_data = [
        'title' => $request->get_param('title'),
        'message' => $request->get_param('message'),
        'url' => $request->get_param('url') ?: home_url(),
        'image' => $request->get_param('image') ?: ''
    ];
    
    // ارسال نوتیفیکیشن
    $result = wnw_send_instant_notification_improved($user_ids, $notification_data);
    
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

/**
 * دریافت اطلاعات نوتیفیکیشن از قالب
 */
function wnw_get_notification_data_by_template($template_id) {
    global $wpdb;
    
    $template = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}wn_notifications WHERE id = %d",
        $template_id
    ));
    
    if (!$template) {
        return false;
    }
    
    return [
        'title' => $template->title,
        'message' => $template->message,
        'url' => $template->url,
        'image' => $template->image
    ];
}

/**
 * دریافت تمام کاربران دارای اشتراک فعال
 */
function wnw_get_all_subscribed_users() {
    global $wpdb;
    
    $user_ids = $wpdb->get_col(
        "SELECT DISTINCT user_id FROM {$wpdb->prefix}wn_subscriptions 
         WHERE status = 'active' AND user_id > 0"
    );
    
    return array_map('intval', $user_ids);
}

/**
 * ارسال فوری نوتیفیکیشن - نسخه بهبود یافته
 */
function wnw_send_instant_notification_improved($user_ids, $notification_data) {
    global $wpdb;
    
    if (empty($user_ids)) {
        return [
            'success' => false,
            'message' => 'هیچ کاربری مشخص نشده است.'
        ];
    }
    
    // تنظیمات VAPID
    $settings = get_option('wnw_settings', []);
    
    if (empty($settings['public_key']) || empty($settings['private_key'])) {
        return [
            'success' => false,
            'message' => 'کلیدهای VAPID تنظیم نشده‌اند. لطفاً به تنظیمات پلاگین بروید و کلیدها را تولید کنید.'
        ];
    }
    
    // دریافت اشتراک‌های فعال کاربران
    $user_ids_placeholder = implode(',', array_fill(0, count($user_ids), '%d'));
    $subscriptions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}wn_subscriptions 
         WHERE user_id IN ({$user_ids_placeholder}) AND status = 'active'",
        ...$user_ids
    ));
    
    if (empty($subscriptions)) {
        return [
            'success' => false,
            'message' => 'هیچ اشتراک فعالی برای کاربران مشخص شده یافت نشد.',
            'debug_info' => [
                'user_ids' => $user_ids,
                'subscriptions_query' => "SELECT * FROM {$wpdb->prefix}wn_subscriptions WHERE user_id IN ({$user_ids_placeholder}) AND status = 'active'"
            ]
        ];
    }
    
    // آماده‌سازی VAPID auth
    $auth = [
        'VAPID' => [
            'subject' => $settings['email'] ?? get_option('admin_email'),
            'publicKey' => $settings['public_key'],
            'privateKey' => $settings['private_key'],
        ],
    ];
    
    try {
        // ایجاد WebPush client با timeout کوتاه‌تر
        $webPush = new WebPush($auth, [], 10); // 10 second timeout
        $webPush->setReuseVAPIDHeaders(true);
        
        // تنظیمات اضافی برای عملکرد بهتر
        $webPush->setAutomaticPadding(false); // Disable padding for better performance
        
        // آماده‌سازی payload با debugging
        $payload = [
            'title' => $notification_data['title'],
            'body' => $notification_data['message'],
            'icon' => $settings['icon'] ?? get_site_icon_url(192),
            'url' => $notification_data['url'],
            'image' => $notification_data['image'] ?? '',
            'timestamp' => time(),
            'tag' => 'notification-' . time(),
            'requireInteraction' => false
        ];
        
        $payload_json = json_encode($payload);
        
        // Log payload for debugging
        error_log('WNW Payload: ' . $payload_json);
        
        // افزودن نوتیفیکیشن‌ها به صف ارسال
        $notifications_prepared = 0;
        $start_prep_time = microtime(true);
        
        foreach ($subscriptions as $subscription) {
            try {
                $webpush_subscription = Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                ]);
                
                $webPush->queueNotification(
                    $webpush_subscription,
                    $payload_json,
                    [
                        'TTL' => 60, // 1 minute TTL for faster processing
                        'urgency' => 'high'
                    ]
                );
                
                $notifications_prepared++;
                
                // Log each subscription for debugging
                error_log('WNW Subscription prepared: ' . substr($subscription->endpoint, 0, 50));
                
            } catch (Exception $e) {
                error_log('WNW Error preparing subscription: ' . $e->getMessage());
                
                // Mark subscription as potentially problematic
                $wpdb->update(
                    $wpdb->prefix . 'wn_subscriptions',
                    ['status' => 'expired'],
                    ['id' => $subscription->id]
                );
            }
        }
        
        $prep_time = round((microtime(true) - $start_prep_time) * 1000);
        error_log("WNW Preparation completed in {$prep_time}ms for {$notifications_prepared} notifications");
        
        if ($notifications_prepared === 0) {
            return [
                'success' => false,
                'message' => 'هیچ نوتیفیکیشنی آماده نشد. تمام subscriptions مشکل دارند.'
            ];
        }
        
        // ارسال نوتیفیکیشن‌ها
        $start_send_time = microtime(true);
        error_log('WNW Starting to send ' . $notifications_prepared . ' notifications');
        
        $reports = $webPush->flush();
        
        $send_time = round((microtime(true) - $start_send_time) * 1000);
        error_log("WNW Send completed in {$send_time}ms");
        
        // پردازش نتایج
        $total = $notifications_prepared;
        $sent = 0;
        $failed = 0;
        $expired = 0;
        $details = [];
        
        foreach ($reports as $report) {
            $endpoint = $report->getEndpoint();
            $subscription_info = null;
            
            foreach ($subscriptions as $sub) {
                if ($sub->endpoint === $endpoint) {
                    $subscription_info = $sub;
                    break;
                }
            }
            
            if ($report->isSuccess()) {
                $sent++;
                $status = 'موفق';
                error_log('WNW Success: ' . substr($endpoint, 0, 50));
            } else {
                $failed++;
                $reason = $report->getReason();
                $status = 'ناموفق: ' . $reason;
                error_log('WNW Failed: ' . substr($endpoint, 0, 50) . ' - ' . $reason);
                
                // به‌روزرسانی اشتراک‌های منقضی شده
                if ($report->isSubscriptionExpired() && $subscription_info) {
                    $wpdb->update(
                        $wpdb->prefix . 'wn_subscriptions',
                        ['status' => 'expired'],
                        ['id' => $subscription_info->id]
                    );
                    $expired++;
                    error_log('WNW Subscription expired and updated: ' . $subscription_info->id);
                }
            }
            
            $details[] = [
                'user_id' => $subscription_info ? $subscription_info->user_id : 'نامشخص',
                'endpoint' => substr($endpoint, 0, 40) . '...',
                'status' => $status
            ];
        }
        
        $total_time = $prep_time + $send_time;
        
        return [
            'success' => true,
            'total_subscriptions' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'expired' => $expired,
            'preparation_time_ms' => $prep_time,
            'send_time_ms' => $send_time,
            'total_time_ms' => $total_time,
            'payload' => $payload,
            'details' => $details
        ];
        
    } catch (Exception $e) {
        error_log('WNW Exception: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در ارسال: ' . $e->getMessage(),
            'debug_info' => [
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_class' => get_class($e)
            ]
        ];
    }
}