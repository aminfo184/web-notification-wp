<?php
if (!defined('ABSPATH')) {
    exit;
}

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Main hook for the cron job (acts as a fallback).
 * This function now simply triggers the main runner.
 */
add_action('wnw_process_queue_hook', 'wnw_spawn_runner');

/**
 * Triggers the background sending process using a non-blocking request.
 * This ensures the admin or user doesn't have to wait.
 */
function wnw_spawn_runner()
{
    // Check if a process is already locked to avoid multiple simultaneous runs.
    if (get_transient('wnw_sender_lock')) {
        return;
    }

    $url = add_query_arg([
        'action' => 'wnw_run_sender',
        '_wnw_nonce' => wp_create_nonce('wnw_sender_nonce'),
    ], admin_url('admin-ajax.php'));

    wp_remote_post($url, [
        'blocking'  => false,
        'sslverify' => apply_filters('https_local_ssl_verify', false),
        'timeout'   => 1,
    ]);
}

/**
 * The AJAX action that actually runs the sender.
 * This can be triggered by the cron or immediately after a campaign is created.
 */
add_action('wp_ajax_nopriv_wnw_run_sender', 'wnw_run_sender_callback');
add_action('wp_ajax_wnw_run_sender', 'wnw_run_sender_callback');

function wnw_run_sender_callback()
{
    check_ajax_referer('wnw_sender_nonce', '_wnw_nonce');

    // Lock the process to prevent multiple instances from running.
    if (get_transient('wnw_sender_lock')) {
        wp_die('Process is already running.');
    }
    set_transient('wnw_sender_lock', true, MINUTE_IN_SECONDS * 5); // Lock for max 5 mins

    // Run the main processing function.
    wnw_process_notification_queue();

    // Unlock the process once done.
    delete_transient('wnw_sender_lock');

    wp_die('Background process finished.');
}


/**
 * Processes the notification queue continuously until it's empty.
 * This is the new, optimized core of the sending engine.
 */
function wnw_process_notification_queue()
{
    global $wpdb;
    $queue_table = $wpdb->prefix . 'wn_queue';
    $subs_table = $wpdb->prefix . 'wn_subscriptions';
    $notif_table = $wpdb->prefix . 'wn_notifications';

    $settings = get_option('wnw_settings', []);
    $batch_size = !empty($settings['batch_size']) ? absint($settings['batch_size']) : 100;

    // Prepare WebPush object once
    $auth = ['VAPID' => ['subject' => $settings['email'] ?? '', 'publicKey' => $settings['public_key'] ?? '', 'privateKey' => $settings['private_key'] ?? '']];
    if (empty($auth['VAPID']['publicKey']) || empty($auth['VAPID']['privateKey'])) {
        // Log error and stop if keys are not set.
        return;
    }

    $webPush = new WebPush($auth);
    $webPush->setReuseVAPIDHeaders(true); // Performance optimization

    // Loop until the queue is empty
    while (true) {
        $items_to_process = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$queue_table} WHERE status = 'queued' AND scheduled_for <= %s ORDER BY id ASC LIMIT %d",
            current_time('mysql', 1),
            $batch_size
        ));

        if (empty($items_to_process)) {
            break; // Exit the loop if queue is empty
        }

        // Lock these specific items to prevent duplicate processing in other potential threads
        $item_ids = wp_list_pluck($items_to_process, 'id');
        $ids_placeholder = implode(',', array_fill(0, count($item_ids), '%d'));
        $wpdb->query($wpdb->prepare("UPDATE {$queue_table} SET status = 'processing', last_attempt_at = %s WHERE id IN ({$ids_placeholder})", current_time('mysql', 1), ...$item_ids));

        // --- OPTIMIZATION: Fetch all required data in bulk ---
        $notification_ids = array_unique(wp_list_pluck($items_to_process, 'notification_id'));
        $subscription_ids = array_unique(wp_list_pluck($items_to_process, 'subscription_id'));

        $notifications = $wpdb->get_results("SELECT * FROM {$notif_table} WHERE id IN (" . implode(',', $notification_ids) . ")", OBJECT_K);
        $subscriptions = $wpdb->get_results("SELECT * FROM {$subs_table} WHERE id IN (" . implode(',', $subscription_ids) . ")", OBJECT_K);
        // --- END OPTIMIZATION ---

        foreach ($items_to_process as $item) {
            // Use data from our pre-fetched cache instead of querying the DB in a loop
            $notification_data = $notifications[$item->notification_id] ?? null;
            $subscription_data = $subscriptions[$item->subscription_id] ?? null;

            if (!$notification_data || !$subscription_data || $subscription_data->status !== 'active') {
                $wpdb->update($queue_table, ['status' => 'failed', 'status_message' => 'Template or active subscription not found.'], ['id' => $item->id]);
                continue;
            }

            $payload = json_encode([
                'title' => $notification_data->title,
                'body'  => $notification_data->message,
                'icon'  => $settings['icon'] ?? '',
                'url'   => $notification_data->url,
                'image' => $notification_data->image,
            ]);

            $subscription = Subscription::create([
                'endpoint' => $subscription_data->endpoint,
                'publicKey' => $subscription_data->public_key,
                'authToken' => $subscription_data->auth_token,
            ]);

            $webPush->queueNotification($subscription, $payload, ['TTL' => $settings['ttl'] ?? 2419200, 'urgency' => $settings['urgency'] ?? 'normal']);
        }

        // Flush all queued notifications and get reports
        $reports = $webPush->flush();

        foreach ($reports as $report) {
            $endpoint = $report->getEndpoint();
            $queue_item = null;

            // Find the queue item that corresponds to this report
            foreach ($items_to_process as $item) {
                if (isset($subscriptions[$item->subscription_id]) && $subscriptions[$item->subscription_id]->endpoint === $endpoint) {
                    $queue_item = $item;
                    break;
                }
            }
            if (!$queue_item) continue;

            $update_data = [];
            if ($report->isSuccess()) {
                $update_data['status'] = 'sent';
                $update_data['sent_at'] = current_time('mysql', 1);
                $update_data['status_message'] = 'Success';
                $wpdb->query($wpdb->prepare("UPDATE {$notif_table} SET total_sent = total_sent + 1 WHERE id = %d", $queue_item->notification_id));
            } else {
                $update_data['status'] = 'failed';
                $update_data['status_message'] = $report->getReason();
                $wpdb->query($wpdb->prepare("UPDATE {$notif_table} SET total_failed = total_failed + 1 WHERE id = %d", $queue_item->notification_id));
            }

            if ($report->isSubscriptionExpired()) {
                $update_data['status'] = 'expired';
                $wpdb->update($subs_table, ['status' => 'expired'], ['endpoint' => $endpoint]);
            }
            $wpdb->update($queue_table, $update_data, ['id' => $queue_item->id]);
        }
    }
}
