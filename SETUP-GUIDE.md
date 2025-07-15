# راهنمای کامل راه‌اندازی Web Push Notifications

## مشکل حل شده
مشکل اصلی **عدم نصب پکیج web-push-php** بود که باعث عدم کارکرد نوتیفیکیشن‌ها می‌شد.

## راه‌حل پیاده‌سازی شده
یک پیاده‌سازی ساده از web-push بدون dependency ایجاد شده که شامل:
- کلاس `WNW_Simple_VAPID` برای تولید کلیدها
- کلاس `WNW_Simple_WebPush` برای ارسال نوتیفیکیشن‌ها

## مراحل راه‌اندازی

### 1. بررسی فایل‌های جدید
فایل‌های زیر اضافه شده‌اند:
- `includes/simple-vapid.php` - پیاده‌سازی ساده VAPID
- `composer.json` - برای پیاده‌سازی‌های آینده
- `SETUP-GUIDE.md` - این راهنما

### 2. تولید کلیدهای VAPID
1. به پنل ادمین وردپرس بروید
2. به `وب نوتیفیکیشن > تنظیمات` بروید
3. دکمه **"تولید کلیدهای جدید"** را کلیک کنید
4. مطمئن شوید که کلیدها تولید شده‌اند

### 3. بررسی عملکرد API

#### تست API:
```bash
# تست کلی
curl -X GET "http://yoursite.com/wp-json/wnw/v1/test"

# ارسال به کاربر مشخص
curl -X POST "http://yoursite.com/wp-json/wnw/v1/send/user" \
-H "Content-Type: application/json" \
-d '{
  "user_id": 1,
  "title": "تست نوتیف",
  "message": "این یک تست است"
}'

# ارسال به همه کاربران
curl -X POST "http://yoursite.com/wp-json/wnw/v1/send/custom" \
-H "Content-Type: application/json" \
-d '{
  "title": "پیام عمومی",
  "message": "برای همه کاربران", 
  "user_ids": "all"
}'
```

### 4. مراحل debugging

#### اگر نوتیفیکیشن ارسال نمی‌شود:

1. **بررسی کلیدهای VAPID:**
   - به تنظیمات پلاگین بروید
   - مطمئن شوید کلیدهای public و private تولید شده‌اند

2. **بررسی subscription:**
   - در دیتابیس جدول `wp_wn_subscriptions` را چک کنید
   - مطمئن شوید اشتراک‌هایی با status='active' وجود دارند

3. **بررسی مجوز مرورگر:**
   - در مرورگر F12 > Console را باز کنید
   - مطمئن شوید خطای مربوط به permission نداشته باشید

4. **تست ساده:**
   ```javascript
   // در کنسول مرورگر اجرا کنید
   Notification.requestPermission().then(function(permission) {
     console.log('Permission:', permission);
   });
   ```

### 5. بررسی Service Worker

Service Worker باید در `/service-worker.js` در دسترس باشد:
```javascript
// تست در کنسول مرورگر
navigator.serviceWorker.register('/service-worker.js')
.then(function(registration) {
  console.log('Service Worker registered:', registration);
})
.catch(function(error) {
  console.log('Service Worker registration failed:', error);
});
```

### 6. Dashboard جدید

Dashboard جدید امکانات زیر را ارائه می‌دهد:
- کنترل شروع/توقف پردازش صف
- آمار زنده از نوتیفیکیشن‌ها
- تنظیم سریع batch_size
- راهنمای API

### 7. نکات مهم

1. **HTTPS:** Web Push فقط روی HTTPS کار می‌کند (localhost مستثنی است)
2. **Browser Support:** Chrome, Firefox, Safari, Edge پشتیبانی می‌کنند
3. **Permission:** کاربر باید صراحتاً اجازه نوتیفیکیشن داده باشد
4. **Service Worker:** باید در root domain قرار گیرد

### 8. عیب‌یابی متداول

| مشکل | علت احتمالی | راه‌حل |
|------|-------------|--------|
| API error 404 | Permalink تنظیم نشده | Settings > Permalinks > Save |
| Keys not generating | OpenSSL support | بررسی تنظیمات PHP |
| Notifications not showing | Browser permission | Request permission again |
| Service Worker error | HTTPS required | Use HTTPS or localhost |

### 9. ساختار فایل‌ها

```
web-notification-wp/
├── includes/
│   ├── simple-vapid.php      # کلاس‌های ساده VAPID و WebPush
│   ├── api-routes.php        # API endpoints بهبود یافته
│   ├── background-processor.php # سیستم پردازش جدید
│   └── ...
├── admin/
├── assets/
├── templates/
├── service-worker.js
├── composer.json
└── SETUP-GUIDE.md
```

### 10. مراحل بعدی (اختیاری)

برای بهبود عملکرد:
1. نصب Composer و dependencies اصلی
2. پیاده‌سازی payload encryption کامل
3. افزودن قابلیت retry mechanism
4. اضافه کردن logging پیشرفته

---

## تماس و پشتیبانی

در صورت بروز مشکل:
1. لاگ‌های PHP را بررسی کنید
2. کنسول مرورگر را چک کنید  
3. Network tab در Developer Tools را بررسی کنید

**نکته:** این پیاده‌سازی برای محیط‌های production قابل استفاده است اما برای بهترین عملکرد توصیه می‌شود از پکیج اصلی web-push-php استفاده شود.