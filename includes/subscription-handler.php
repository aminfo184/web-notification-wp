<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * A robust function to parse the User-Agent string.
 * This function detects a wide range of browsers and operating systems, including their versions.
 *
 * @param string $user_agent The User-Agent string.
 * @return array An array containing 'browser', 'version', 'os', and 'os_version'.
 */
function wnw_parse_user_agent($user_agent) {
    $browser = 'Unknown';
    $os = 'Unknown';
    $version = '';
    $os_version = '';

    // Operating System detection
    if (preg_match('/windows nt 10/i', $user_agent)) $os = 'Windows 10/11';
    elseif (preg_match('/windows nt 6.3/i', $user_agent)) $os = 'Windows 8.1';
    elseif (preg_match('/windows nt 6.2/i', $user_agent)) $os = 'Windows 8';
    elseif (preg_match('/windows nt 6.1/i', $user_agent)) $os = 'Windows 7';
    elseif (preg_match('/windows/i', $user_agent)) $os = 'Windows';
    elseif (preg_match('/android ([0-9\.]+)/i', $user_agent, $matches)) {
        $os = 'Android';
        $os_version = $matches[1];
    } elseif (preg_match('/android/i', $user_agent)) {
        $os = 'Android';
    }
    elseif (preg_match('/linux/i', $user_agent)) $os = 'Linux';
    elseif (preg_match('/iphone os ([0-9_]+)/i', $user_agent, $matches)) {
        $os = 'iOS';
        $os_version = str_replace('_', '.', $matches[1]);
    } elseif (preg_match('/ipad.*os ([0-9_]+)/i', $user_agent, $matches)) {
        $os = 'iOS'; // iPadOS is still iOS under the hood
        $os_version = str_replace('_', '.', $matches[1]);
    }
    elseif (preg_match('/mac os x ([0-9_]+)/i', $user_agent, $matches)) {
        $os = 'macOS';
        $os_version = str_replace('_', '.', $matches[1]);
    }

    // Browser detection (order is important)
    if (preg_match('/edg\/([0-9\.]+)/i', $user_agent, $matches)) {
        $browser = 'Edge';
        $version = $matches[1];
    } elseif (preg_match('/opr\/([0-9\.]+)/i', $user_agent, $matches) || preg_match('/opera.([0-9\.]+)/i', $user_agent, $matches)) {
        $browser = 'Opera';
        $version = $matches[1];
    } elseif (preg_match('/chrome\/([0-9\.]+)/i', $user_agent, $matches)) {
        $browser = 'Chrome';
        $version = $matches[1];
    } elseif (preg_match('/firefox\/([0-9\.]+)/i', $user_agent, $matches)) {
        $browser = 'Firefox';
        $version = $matches[1];
    } elseif (preg_match('/version\/([0-9\.]+).*safari/i', $user_agent, $matches)) {
        $browser = 'Safari';
        $version = $matches[1];
    } elseif (preg_match('/msie ([0-9\.]+)/i', $user_agent, $matches)) {
        $browser = 'Internet Explorer';
        $version = $matches[1];
    } elseif (preg_match('/trident\/7.0.*rv:([0-9\.]+)/i', $user_agent, $matches)) {
        $browser = 'Internet Explorer';
        $version = $matches[1];
    }

    return [
        'browser' => $browser, 
        'version' => $version, 
        'os' => $os,
        'os_version' => $os_version
    ];
}


add_action('wp_ajax_wnw_save_subscription', 'wnw_ajax_save_subscription');
add_action('wp_ajax_nopriv_wnw_save_subscription', 'wnw_ajax_save_subscription');

function wnw_ajax_save_subscription() {
    check_ajax_referer('wnw_subscribe_nonce', 'nonce');

    $subscription_data = json_decode(stripslashes($_POST['subscription']), true);
    $guest_token = isset($_POST['guest_token']) ? sanitize_text_field($_POST['guest_token']) : null;

    if (!$subscription_data || empty($subscription_data['endpoint'])) {
        wp_send_json_error(['message' => 'اطلاعات اشتراک نامعتبر است.']);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wn_subscriptions';

    $endpoint = esc_sql($subscription_data['endpoint']);
    $public_key = esc_sql($subscription_data['keys']['p256dh'] ?? null);
    $auth_token = esc_sql($subscription_data['keys']['auth'] ?? null);

    // Use the new robust parser function
    $device_info = wnw_parse_user_agent($_SERVER['HTTP_USER_AGENT'] ?? '');

    // Combine name and version for storage
    $browser_with_version = trim($device_info['browser'] . ' ' . $device_info['version']);
    $os_with_version = trim($device_info['os'] . ' ' . $device_info['os_version']);

    $data = [
        'user_id' => get_current_user_id(),
        'guest_token' => $guest_token,
        'endpoint' => $endpoint,
        'public_key' => $public_key,
        'auth_token' => $auth_token,
        'browser' => $browser_with_version, // Store browser with version
        'os' => $os_with_version, // Store OS with version
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'status' => 'active',
        'updated_at' => current_time('mysql', 1),
    ];

    $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE endpoint = %s", $endpoint));

    if ($existing_id) {
        $result = $wpdb->update($table_name, $data, ['id' => $existing_id]);
    } else {
        $data['created_at'] = current_time('mysql', 1);
        $result = $wpdb->insert($table_name, $data);
    }

    if ($result !== false) {
        wp_send_json_success(['message' => 'اشتراک با موفقیت ذخیره شد.']);
    } else {
        wp_send_json_error(['message' => 'خطا در ذخیره اشتراک.']);
    }
}

add_action('wp_login', 'wnw_associate_guest_token_on_login', 10, 2);
function wnw_associate_guest_token_on_login($user_login, $user) {
    if (isset($_COOKIE['wnw_guest_token'])) {
        $guest_token = sanitize_text_field($_COOKIE['wnw_guest_token']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wn_subscriptions';
        
        $wpdb->update(
            $table_name,
            ['user_id' => $user->ID],
            ['guest_token' => $guest_token, 'user_id' => 0]
        );
    }
}
