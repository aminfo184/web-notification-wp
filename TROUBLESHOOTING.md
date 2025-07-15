# 🛠️ راهنمای رفع مشکلات Web Push Notifications

## 🔍 تشخیص سریع مشکل

### گام 1: استفاده از ابزار Debug
1. فایل `debug-notification.php` را در root وردپرس قرار دهید
2. به آدرس `yoursite.com/debug-notification.php` بروید
3. تمام بخش‌ها را بررسی کنید

### گام 2: استفاده از ابزار تست API
1. فایل `test-api.html` را در root وردپرس قرار دهید  
2. به آدرس `yoursite.com/test-api.html` بروید
3. API Status Check کنید

## 🚨 مشکلات رایج و راه حل

### مشکل 1: نوتیفیکیشن ارسال نمی‌شود
**علائم:**
- API می‌گوید موفق ولی نوتیف نمی‌آید
- طولانی شدن زمان ارسال (بیش از 30 ثانیه)

**راه حل:**
```bash
# 1. چک کردن لاگ‌ها
tail -f /var/log/php/error.log | grep WNW

# 2. تست مستقیم از دیباگ script
# در مرورگر برو به: yoursite.com/debug-notification.php

# 3. چک کردن subscriptions در دیتابیس
```

### مشکل 2: Subscription Expired
**علائم:**
- پیام "Subscription appears to be expired"
- نوتیف فقط برای برخی کاربران می‌آید

**راه حل:**
```php
// در wp-admin > Tools > WP-CLI یا phpMyAdmin:
DELETE FROM wp_wn_subscriptions WHERE status = 'expired';

// سپس کاربران دوباره باید subscribe کنند
```

### مشکل 3: Service Worker مشکل دارد
**علائم:**
- "No Service Worker registered"
- خطا در Console مرورگر

**راه حل:**
```javascript
// 1. Cache مرورگر را پاک کنید
// 2. Service Worker را دوباره register کنید:

navigator.serviceWorker.register('/service-worker.js')
.then(registration => console.log('SW registered:', registration))
.catch(error => console.log('SW registration failed:', error));

// 3. اگر مشکل ادامه داشت، فایل service-worker.js را بررسی کنید
```

### مشکل 4: VAPID Keys اشتباه
**علائم:**
- "Invalid VAPID key" errors
- Authentication failures

**راه حل:**
1. به Settings پلاگین بروید
2. Generate New VAPID Keys کنید
3. Save Changes
4. دوباره تست کنید

### مشکل 5: Permission Denied
**علائم:**
- Notification.permission === "denied"
- هیچ subscription ایجاد نمی‌شود

**راه حل:**
```javascript
// 1. Manual permission request:
Notification.requestPermission().then(permission => {
    console.log('Permission:', permission);
});

// 2. اگر denied است، باید از تنظیمات مرورگر اجازه بدهید:
// Chrome: Settings > Privacy and security > Site Settings > Notifications
// Firefox: Preferences > Privacy & Security > Permissions > Notifications
```

## 🧪 تست‌های تشخیصی

### تست 1: بررسی Browser Support
```javascript
console.log('Service Worker:', 'serviceWorker' in navigator);
console.log('Push Manager:', 'PushManager' in window);
console.log('Notifications:', 'Notification' in window);
console.log('Permission:', Notification.permission);
```

### تست 2: بررسی Service Worker
```javascript
navigator.serviceWorker.getRegistrations().then(registrations => {
    console.log('SW Registrations:', registrations.length);
    registrations.forEach(reg => console.log('Scope:', reg.scope));
});
```

### تست 3: تست API مستقیم
```bash
# GET test
curl "https://yoursite.com/wp-json/wnw/v1/test"

# POST send test  
curl -X POST "https://yoursite.com/wp-json/wnw/v1/send/custom" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","message":"Test message","user_ids":"all"}'
```

### تست 4: بررسی Database
```sql
-- چک کردن subscriptions
SELECT COUNT(*) as active_subs FROM wp_wn_subscriptions WHERE status = 'active';

-- چک کردن اطلاعات subscriptions
SELECT id, user_id, browser, status, created_at 
FROM wp_wn_subscriptions 
ORDER BY created_at DESC 
LIMIT 5;

-- پاک کردن subscriptions منقضی
DELETE FROM wp_wn_subscriptions WHERE status = 'expired';
```

## 🔧 حل مشکلات خاص

### مشکل: "Class 'Minishlink\WebPush\WebPush' not found"
```bash
# نصب پکیج composer
composer require minishlink/web-push

# یا manual vendor file check
ls -la vendor/minishlink/web-push/
```

### مشکل: "cURL timeout" 
**راه حل در `api-routes.php`:**
```php
// خط 150 را تغییر دهید:
$webPush = new WebPush($auth, [], 5); // 5 second timeout

// TTL را کم کنید:
'TTL' => 30, // 30 seconds instead of 300
```

### مشکل: payload خیلی بزرگ
```php
// پیلود کوچک‌تر بسازید:
$payload = [
    'title' => substr($notification_data['title'], 0, 50),
    'body' => substr($notification_data['message'], 0, 100),
    'icon' => '', // حذف icon اگر بزرگ است
    'url' => $notification_data['url']
];
```

## 📊 مانیتورینگ و لاگ‌ها

### فعال کردن Debug Mode
```php
// در wp-config.php اضافه کنید:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// لاگ‌های WNW را ببینید:
tail -f /path/to/wp-content/debug.log | grep WNW
```

### لاگ‌های مهم برای بررسی:
```
[WNW Service Worker] Push event received
[WNW Service Worker] Parsed payload: {...}
WNW Payload: {"title":"...","body":"..."}
WNW Subscription prepared: https://fcm.googleapis.com...
WNW Send completed in 1250ms
WNW Success: https://fcm.googleapis.com...
WNW Failed: https://fcm.googleapis.com... - reason
```

## 🎯 بهینه‌سازی عملکرد

### کاهش زمان ارسال:
1. TTL کوتاه‌تر (30-60 ثانیه)
2. Timeout کم‌تر (5-10 ثانیه)  
3. Batch size کوچک‌تر (10-20 کاربر)
4. پاک کردن subscriptions منقضی

### بهبود تحویل نوتیف:
1. Payload کوچک‌تر
2. urgency = 'high'
3. requireInteraction = false
4. Service Worker بهینه

## 📞 راه‌های تماس برای کمک

اگر مشکل حل نشد:

1. **لاگ‌های خطا** را جمع‌آوری کنید
2. **Browser Console** را چک کنید  
3. **Network tab** را در Developer Tools بررسی کنید
4. تست‌های بالا را انجام دهید و نتایج را ذخیره کنید

**مثال گزارش مشکل خوب:**
```
مرورگر: Chrome 120
OS: Windows 11  
مشکل: نوتیف ارسال نمی‌شود
API Response: {"success":true,"sent":0,"failed":3}
Console Error: [WNW Service Worker] Error parsing push data
لاگ سرور: WNW Failed: ... - InvalidRegistration
```

## ✅ چک‌لیست نهایی

- [ ] VAPID keys تولید شده
- [ ] Service Worker registered شده  
- [ ] Permission granted شده
- [ ] Active subscriptions وجود دارد
- [ ] API test موفق است
- [ ] Browser support کامل است
- [ ] لاگ‌ها چک شده
- [ ] تست debug انجام شده

---

💡 **نکته:** اگر همه چیز درست به نظر می‌رسد ولی نوتیف نمی‌آید، احتمالاً مشکل از:
1. Browser notification settings
2. Endpoint های منقضی شده
3. Network firewall
4. Push service downtime

برای کمک بیشتر، تمام لاگ‌ها و نتایج تست‌ها را آماده کنید.