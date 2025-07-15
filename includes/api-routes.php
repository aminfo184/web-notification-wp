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

    // API ارسال به لیست کاربران
    register_rest_route('wnw/v1', '/send/users', [
        'methods' => 'POST',
        'callback' => 'wnw_api_send_to_users',
        'permission_callback' => 'wnw_api_permission_check',
        'args' => [
            'user_ids' => [
                'required' => true,
                'validate_callback' => function($param) {
                    if (!is_array($param)) return false;
                    foreach ($param as $id) {
                        if (!is_numeric($id) || $id <= 0) return false;
                    }
                    return true;
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
});

/**
 * بررسی مجوز API
 */
function wnw_api_permission_check(WP_REST_Request $request) {
    // برای تست و استفاده آسان، مجوز ساده
    return true;
    
    // در صورت نیاز به امنیت بیشتر، از کد زیر استفاده کنید:
    /*
    $nonce = $request->get_header('X-WP-Nonce');
    if (wp_verify_nonce($nonce, 'wp_rest')) {
        return true;
    }
    return current_user_can('manage_options');
    */
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
    $result = wnw_send_instant_notification([$user_id], $notification_data);
    
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

/**
 * ارسال نوتیفیکیشن به لیست کاربران
 */
function wnw_api_send_to_users(WP_REST_Request $request) {
    $user_ids = $request->get_param('user_ids');
    $template_id = $request->get_param('template_id');
    
    // محدود کردن تعداد کاربران
    if (count($user_ids) > 100) {
        return new WP_REST_Response([
            'success' => false, 
            'message' => 'حداکثر 100 کاربر در هر درخواست مجاز است.'
        ], 400);
    }
    
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
    $result = wnw_send_instant_notification($user_ids, $notification_data);
    
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
    $result = wnw_send_instant_notification($user_ids, $notification_data);
    
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
 * ارسال فوری نوتیفیکیشن
 */
function wnw_send_instant_notification($user_ids, $notification_data) {
    global $wpdb;
    
    if (empty($user_ids)) {
        return [
            'success' => false,
            'message' => 'هیچ کاربری مشخص نشده است.'
        ];
    }
    
    // تنظیمات VAPID
    $settings = get_option('wnw_settings', []);
    $auth = [
        'VAPID' => [
            'subject' => $settings['email'] ?? get_option('admin_email'),
            'publicKey' => $settings['public_key'] ?? '',
            'privateKey' => $settings['private_key'] ?? '',
        ],
    ];
    
    if (empty($auth['VAPID']['publicKey']) || empty($auth['VAPID']['privateKey'])) {
        return [
            'success' => false,
            'message' => 'کلیدهای VAPID تنظیم نشده‌اند.'
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
            'message' => 'هیچ اشتراک فعالی برای کاربران مشخص شده یافت نشد.'
        ];
    }
    
    // آماده‌سازی WebPush
    $webPush = new WebPush($auth);
    $webPush->setReuseVAPIDHeaders(true);
    
    // آماده‌سازی payload
    $payload = json_encode([
        'title' => $notification_data['title'],
        'body' => $notification_data['message'],
        'icon' => $settings['icon'] ?? '',
        'url' => $notification_data['url'],
        'image' => $notification_data['image'],
    ]);
    
    // افزودن نوتیفیکیشن‌ها به صف ارسال
    foreach ($subscriptions as $subscription) {
        $webpush_subscription = Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
        ]);
        
        $webPush->queueNotification(
            $webpush_subscription,
            $payload,
            [
                'TTL' => $settings['ttl'] ?? 2419200,
                'urgency' => 'high' // ارسال فوری
            ]
        );
    }
    
    // ارسال نوتیفیکیشن‌ها
    $reports = $webPush->flush();
    
    // پردازش نتایج
    $results = [
        'success' => true,
        'total_subscriptions' => count($subscriptions),
        'sent' => 0,
        'failed' => 0,
        'details' => []
    ];
    
    foreach ($reports as $report) {
        $endpoint = $report->getEndpoint();
        $subscription_info = null;
        
        foreach ($subscriptions as $sub) {
            if ($sub->endpoint === $endpoint) {
                $subscription_info = $sub;
                break;
            }
        }
        
        $status_message = $report->isSuccess() ? 'موفق' : 'ناموفق: ' . $report->getReason();
        $results['details'][] = [
            'user_id' => $subscription_info ? $subscription_info->user_id : 'نامشخص',
            'endpoint' => substr($endpoint, 0, 40) . '...',
            'status' => $status_message
        ];
        
        if ($report->isSuccess()) {
            $results['sent']++;
        } else {
            $results['failed']++;
            
            // به‌روزرسانی اشتراک‌های منقضی شده
            if ($report->isSubscriptionExpired() && $subscription_info) {
                $wpdb->update(
                    $wpdb->prefix . 'wn_subscriptions',
                    ['status' => 'expired'],
                    ['id' => $subscription_info->id]
                );
            }
        }
    }
    
    return $results;
}