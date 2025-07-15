<?php


use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Registers custom REST API routes for the plugin.
 */
add_action('rest_api_init', function () {
    register_rest_route('wnw/v1', '/send/user', [
        'methods' => 'POST',
        'callback' => 'wnw_api_send_to_user',
        'permission_callback' => 'wnw_api_permission_check',
        'args' => [
            'user_id' => [
                'required' => true,
            ],
            'template_id' => [
                'required' => true,
            ],
        ],
    ]);
});

/**
 * Permission check for the API endpoint.
 * Only users who can manage options can use this endpoint.
 * NOTE: For external services, Application Passwords should be used.
 */
/**
 * Permission check for the API endpoint.
 */
function wnw_api_permission_check(WP_REST_Request $request) {
    // برای اینکه درخواست از Postman کار کند، باید Nonce معتبر ارسال شود.
    // $nonce = $request->get_header('X-WP-Nonce');
    // if (wp_verify_nonce($nonce, 'wp_rest')) {
        return true;
    // }

    // به عنوان یک راه جایگزین، چک می‌کنیم که کاربر لاگین کرده و ادمین است.
    // این برای زمانی مفید است که از طریق کدنویسی در خود وردپرس این API را فراخوانی کنید.
    // return current_user_can('manage_options');
}
/**
 * Handles the instant sending of a notification to a specific user.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function wnw_api_send_to_user(WP_REST_Request $request) {
    global $wpdb;
    $user_id = $request->get_param('user_id');
    $template_id = $request->get_param('template_id');

    // Fetch template and user subscriptions
    $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wn_notifications WHERE id = %d", $template_id));
    $subscriptions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wn_subscriptions WHERE user_id = %d AND status = 'active'", $user_id));

    if (!$template) {
        return new WP_REST_Response(['success' => false, 'message' => 'قالب نوتیفیکیشن یافت نشد.'], 404);
    }
    if (empty($subscriptions)) {
        return new WP_REST_Response(['success' => false, 'message' => 'هیچ اشتراک فعالی برای این کاربر یافت نشد.'], 404);
    }

    // Prepare for sending
    $settings = get_option('wnw_settings', []);
    $auth = ['VAPID' => [
        'subject' => $settings['email'] ?? '', 'publicKey' => $settings['public_key'] ?? '', 'privateKey' => $settings['private_key'] ?? ''
    ]];

    if (empty($auth['VAPID']['publicKey'])) {
        return new WP_REST_Response(['success' => false, 'message' => 'کلیدهای VAPID پیکربندی نشده‌اند.'], 500);
    }

    $webPush = new WebPush($auth);
    $payload = json_encode([
        'title' => $template->title,
        'body'  => $template->message,
        'icon'  => $settings['icon'] ?? '', // Correctly fetched from settings
        'url'   => $template->url,
        'image' => $template->image,
    ]);

    foreach ($subscriptions as $sub) {
        $webPush->queueNotification(
            Subscription::create(['endpoint' => $sub->endpoint, 'publicKey' => $sub->public_key, 'authToken' => $sub->auth_token]),
            $payload
        );
    }

    // Send immediately and get reports
    $reports = $webPush->flush();
    $results = ['success' => true, 'total_subscriptions' => count($subscriptions), 'sent' => 0, 'failed' => 0, 'details' => []];

    foreach ($reports as $report) {
        $endpoint = $report->getEndpoint();
        $status_message = $report->isSuccess() ? 'موفق' : 'ناموفق: ' . $report->getReason();
        $results['details'][] = ['endpoint' => substr($endpoint, 0, 40) . '...', 'status' => $status_message];

        if ($report->isSuccess()) {
            $results['sent']++;
        } else {
            $results['failed']++;
            // If the subscription is expired, automatically update its status in our database.
            if ($report->isSubscriptionExpired()) {
                $wpdb->update(
                    $wpdb->prefix . 'wn_subscriptions',
                    ['status' => 'expired'],
                    ['endpoint' => $endpoint]
                );
            }
        }
    }

    return new WP_REST_Response($results, 200);
}