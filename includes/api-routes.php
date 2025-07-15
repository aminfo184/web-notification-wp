<?php

// Load simple VAPID implementation
require_once WNW_PATH . 'includes/simple-vapid.php';

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

    // API تست برای دیباگ
    register_rest_route('wnw/v1', '/test', [
        'methods' => 'GET',
        'callback' => 'wnw_api_test',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * بررسی مجوز API
 */
function wnw_api_permission_check(WP_REST_Request $request) {
    // برای تست و استفاده آسان، مجوز ساده
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
        'timestamp' => current_time('mysql')
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
    $result = wnw_send_instant_notification_simple([$user_id], $notification_data);
    
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
    $result = wnw_send_instant_notification_simple($user_ids, $notification_data);
    
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
    $result = wnw_send_instant_notification_simple($user_ids, $notification_data);
    
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
 * ارسال فوری نوتیفیکیشن با کلاس ساده
 */
function wnw_send_instant_notification_simple($user_ids, $notification_data) {
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
            'message' => 'هیچ اشتراک فعالی برای کاربران مشخص شده یافت نشد.'
        ];
    }
    
    // آماده‌سازی payload
    $payload = json_encode([
        'title' => $notification_data['title'],
        'body' => $notification_data['message'],
        'icon' => $settings['icon'] ?? '',
        'url' => $notification_data['url'],
        'image' => $notification_data['image'],
    ]);
    
    // ایجاد WebPush client
    try {
        $webPush = new WNW_Simple_WebPush(
            $settings['public_key'],
            $settings['private_key'],
            $settings['email'] ?? get_option('admin_email')
        );
        
        // آماده‌سازی notifications
        $notifications = [];
        foreach ($subscriptions as $subscription) {
            $notifications[] = [
                'endpoint' => $subscription->endpoint,
                'payload' => $payload,
                'userPublicKey' => $subscription->public_key,
                'userAuthToken' => $subscription->auth_token
            ];
        }
        
        // ارسال نوتیفیکیشن‌ها
        $results = $webPush->sendNotifications($notifications);
        
        // پردازش نتایج
        $total = count($notifications);
        $sent = 0;
        $failed = 0;
        $details = [];
        
        foreach ($results as $result) {
            $subscription_info = null;
            foreach ($subscriptions as $sub) {
                if ($sub->endpoint === $result['endpoint']) {
                    $subscription_info = $sub;
                    break;
                }
            }
            
            if ($result['result']['success']) {
                $sent++;
                $status = 'موفق';
            } else {
                $failed++;
                $status = 'ناموفق: ' . ($result['result']['error'] ?: 'HTTP ' . $result['result']['httpCode']);
                
                // به‌روزرسانی اشتراک‌های منقضی شده
                if ($result['result']['httpCode'] == 410 && $subscription_info) {
                    $wpdb->update(
                        $wpdb->prefix . 'wn_subscriptions',
                        ['status' => 'expired'],
                        ['id' => $subscription_info->id]
                    );
                }
            }
            
            $details[] = [
                'user_id' => $subscription_info ? $subscription_info->user_id : 'نامشخص',
                'endpoint' => substr($result['endpoint'], 0, 40) . '...',
                'status' => $status
            ];
        }
        
        return [
            'success' => true,
            'total_subscriptions' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'details' => $details
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'خطا در ارسال: ' . $e->getMessage()
        ];
    }
}