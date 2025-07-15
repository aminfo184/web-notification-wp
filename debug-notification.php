<?php
/**
 * Debug script for Web Push Notifications
 * استفاده: قرار دادن در root وردپرس و اجرا از مرورگر
 */

// Load WordPress
require_once 'wp-config.php';
require_once ABSPATH . 'wp-load.php';

// بررسی دسترسی
if (!current_user_can('manage_options')) {
    die('Access denied. Please login as admin.');
}

echo '<h1>🔍 Web Push Notification Debug</h1>';
echo '<style>body{font-family:monospace;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style>';

// 1. بررسی پکیج‌ها
echo '<h2>📦 Package Check</h2>';
echo '<strong>Vendor autoload:</strong> ';
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    echo '<span class="ok">✓ Found</span><br>';
} else {
    echo '<span class="error">✗ Not found</span><br>';
}

echo '<strong>WebPush class:</strong> ';
if (class_exists('Minishlink\\WebPush\\WebPush')) {
    echo '<span class="ok">✓ Available</span><br>';
} else {
    echo '<span class="error">✗ Not available</span><br>';
}

// 2. بررسی تنظیمات
echo '<h2>⚙️ Settings Check</h2>';
$settings = get_option('wnw_settings', []);

echo '<strong>VAPID Public Key:</strong> ';
if (!empty($settings['public_key'])) {
    echo '<span class="ok">✓ Set (' . strlen($settings['public_key']) . ' chars)</span><br>';
    echo '<code>' . substr($settings['public_key'], 0, 50) . '...</code><br>';
} else {
    echo '<span class="error">✗ Not set</span><br>';
}

echo '<strong>VAPID Private Key:</strong> ';
if (!empty($settings['private_key'])) {
    echo '<span class="ok">✓ Set (' . strlen($settings['private_key']) . ' chars)</span><br>';
    echo '<code>' . substr($settings['private_key'], 0, 20) . '...</code><br>';
} else {
    echo '<span class="error">✗ Not set</span><br>';
}

echo '<strong>Email:</strong> ';
$email = $settings['email'] ?? get_option('admin_email');
echo $email ? '<span class="ok">✓ ' . $email . '</span><br>' : '<span class="warning">Using admin email</span><br>';

echo '<strong>Icon:</strong> ';
$icon = $settings['icon'] ?? get_site_icon_url(192);
echo $icon ? '<span class="ok">✓ ' . $icon . '</span><br>' : '<span class="warning">No icon set</span><br>';

// 3. بررسی دیتابیس
echo '<h2>🗄️ Database Check</h2>';
global $wpdb;

$subscriptions_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wn_subscriptions WHERE status = 'active'");
echo '<strong>Active Subscriptions:</strong> ';
if ($subscriptions_count > 0) {
    echo '<span class="ok">✓ ' . $subscriptions_count . ' found</span><br>';
} else {
    echo '<span class="error">✗ No active subscriptions</span><br>';
}

// نمایش 3 subscription آخر
$recent_subs = $wpdb->get_results("SELECT id, user_id, endpoint, browser, created_at FROM {$wpdb->prefix}wn_subscriptions ORDER BY created_at DESC LIMIT 3");
if ($recent_subs) {
    echo '<strong>Recent Subscriptions:</strong><br>';
    foreach ($recent_subs as $sub) {
        echo "- ID: {$sub->id}, User: {$sub->user_id}, Browser: {$sub->browser}, Endpoint: " . substr($sub->endpoint, 0, 60) . "...<br>";
    }
}

// 4. تست Service Worker
echo '<h2>🛠️ Service Worker Check</h2>';
$sw_path = ABSPATH . 'service-worker.js';
echo '<strong>Service Worker File:</strong> ';
if (file_exists($sw_path)) {
    echo '<span class="ok">✓ Found at ' . $sw_path . '</span><br>';
    $sw_content = file_get_contents($sw_path);
    if (strpos($sw_content, 'push') !== false) {
        echo '<span class="ok">✓ Contains push event listener</span><br>';
    } else {
        echo '<span class="warning">⚠ May not contain push event listener</span><br>';
    }
} else {
    echo '<span class="error">✗ Not found</span><br>';
}

// 5. تست ارسال واقعی
echo '<h2>🚀 Live Send Test</h2>';

if ($subscriptions_count > 0 && !empty($settings['public_key']) && !empty($settings['private_key'])) {
    echo '<strong>Testing notification send...</strong><br>';
    
    try {
        // استفاده از WebPush اصلی
        use Minishlink\WebPush\WebPush;
        use Minishlink\WebPush\Subscription;
        
        $auth = [
            'VAPID' => [
                'subject' => $email,
                'publicKey' => $settings['public_key'],
                'privateKey' => $settings['private_key'],
            ],
        ];
        
        $webPush = new WebPush($auth);
        
        // گرفتن یک subscription برای تست
        $test_sub = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wn_subscriptions WHERE status = 'active' LIMIT 1");
        
        if ($test_sub) {
            echo "Testing with subscription ID: {$test_sub->id}<br>";
            echo "User ID: {$test_sub->user_id}<br>";
            echo "Endpoint: " . substr($test_sub->endpoint, 0, 80) . "...<br>";
            
            $subscription = Subscription::create([
                'endpoint' => $test_sub->endpoint,
                'publicKey' => $test_sub->public_key,
                'authToken' => $test_sub->auth_token,
            ]);
            
            $payload = json_encode([
                'title' => '🧪 Debug Test',
                'body' => 'If you see this, notifications are working! Time: ' . date('H:i:s'),
                'icon' => $icon,
                'url' => home_url(),
                'tag' => 'debug-test-' . time()
            ]);
            
            echo "Payload: <code>" . htmlspecialchars($payload) . "</code><br>";
            
            $start_time = microtime(true);
            echo "Sending notification...<br>";
            
            $report = $webPush->sendOneNotification($subscription, $payload, ['TTL' => 300]);
            
            $end_time = microtime(true);
            $duration = round(($end_time - $start_time) * 1000);
            
            echo "Send completed in {$duration}ms<br>";
            
            if ($report->isSuccess()) {
                echo '<span class="ok">✓ Notification sent successfully!</span><br>';
                echo '<strong>⚠️ Check your browser for the notification now!</strong><br>';
            } else {
                echo '<span class="error">✗ Send failed: ' . $report->getReason() . '</span><br>';
                
                if ($report->isSubscriptionExpired()) {
                    echo '<span class="warning">⚠️ Subscription appears to be expired</span><br>';
                    // به‌روزرسانی status
                    $wpdb->update(
                        $wpdb->prefix . 'wn_subscriptions',
                        ['status' => 'expired'],
                        ['id' => $test_sub->id]
                    );
                    echo 'Subscription marked as expired in database.<br>';
                }
            }
        } else {
            echo '<span class="error">✗ No test subscription found</span><br>';
        }
        
    } catch (Exception $e) {
        echo '<span class="error">✗ Exception: ' . $e->getMessage() . '</span><br>';
        echo 'File: ' . $e->getFile() . '<br>';
        echo 'Line: ' . $e->getLine() . '<br>';
    }
} else {
    echo '<span class="warning">⚠️ Cannot test: Missing subscriptions or VAPID keys</span><br>';
}

// 6. بررسی Browser Push Support
echo '<h2>🌐 Browser Support Test</h2>';
echo '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const resultDiv = document.getElementById("browser-support");
    
    if ("serviceWorker" in navigator) {
        resultDiv.innerHTML += "✓ Service Worker: Supported<br>";
    } else {
        resultDiv.innerHTML += "✗ Service Worker: Not supported<br>";
    }
    
    if ("PushManager" in window) {
        resultDiv.innerHTML += "✓ Push Manager: Supported<br>";
    } else {
        resultDiv.innerHTML += "✗ Push Manager: Not supported<br>";
    }
    
    if ("Notification" in window) {
        resultDiv.innerHTML += "✓ Notifications: Supported<br>";
        resultDiv.innerHTML += "Permission: " + Notification.permission + "<br>";
        
        if (Notification.permission === "default") {
            resultDiv.innerHTML += "⚠️ <button onclick=\"requestPermission()\">Request Permission</button><br>";
        }
    } else {
        resultDiv.innerHTML += "✗ Notifications: Not supported<br>";
    }
    
    // بررسی Service Worker registration
    navigator.serviceWorker.getRegistrations().then(function(registrations) {
        if (registrations.length > 0) {
            resultDiv.innerHTML += "✓ Service Worker Registered: " + registrations.length + " found<br>";
            registrations.forEach(function(reg, index) {
                resultDiv.innerHTML += "  - SW " + (index+1) + ": " + reg.scope + "<br>";
            });
        } else {
            resultDiv.innerHTML += "⚠️ No Service Worker registered<br>";
        }
    });
});

function requestPermission() {
    Notification.requestPermission().then(function(permission) {
        document.getElementById("permission-result").innerHTML = "Permission result: " + permission;
        if (permission === "granted") {
            new Notification("✓ Test Notification", {
                body: "Permission granted successfully!",
                icon: "' . ($icon ?: '/favicon.ico') . '"
            });
        }
    });
}
</script>
<div id="browser-support">Loading browser support check...</div>
<div id="permission-result"></div>
';

// 7. اقدامات پیشنهادی
echo '<h2>💡 Recommended Actions</h2>';
echo '
<ol>
<li><strong>اگر subscription ندارید:</strong> به سایت بروید و دکمه subscribe را کلیک کنید</li>
<li><strong>اگر Permission denied است:</strong> در تنظیمات مرورگر، اجازه notification را به سایت بدهید</li>
<li><strong>اگر Service Worker مشکل دارد:</strong> فایل service-worker.js را در root domain قرار دهید</li>
<li><strong>اگر VAPID keys ندارید:</strong> در تنظیمات پلاگین کلیدها را تولید کنید</li>
<li><strong>اگر notification نمی‌آید:</strong> Developer Tools > Console را برای خطاها چک کنید</li>
</ol>
';

echo '<br><a href="' . admin_url('admin.php?page=web-notification-wp-dashboard') . '">🔙 Back to Plugin Dashboard</a>';
?>