/**
 * Service Worker for Web Notification WP
 * This code will be injected into the PWA's service worker.
 */

self.addEventListener('push', function(event) {
    // فقط پیام‌هایی را پردازش می‌کنیم که از سرور ما آمده و فرمت JSON صحیح دارند
    try {
        const payload = event.data.json();
        
        // بررسی می‌کنیم که آیا پیام شامل فیلدهای مورد انتظار ما هست یا خیر
        if ('title' in payload && 'body' in payload) {
            console.log('WNW: [Service Worker] Push Received from our system.');

            const title = payload.title;
            const options = {
                body: payload.body,
                icon: payload.icon,
                image: payload.image,
                data: {
                    url: payload.url,
                },
            };

            event.waitUntil(self.registration.showNotification(title, options));
        }
    } catch (e) {
        // این پیام‌ها برای ما نیستند، آنها را نادیده می‌گیریم
        console.log('WNW: [Service Worker] Push received, but not in our format. Ignoring.');
    }
});

self.addEventListener('notificationclick', function(event) {
    // فقط روی نوتیفیکیشن‌هایی کلیک می‌کنیم که داده‌های ما را دارند
    if (event.notification.data && 'url' in event.notification.data) {
        console.log('WNW: [Service Worker] Notification clicked.');
        event.notification.close();
        const urlToOpen = event.notification.data.url;
        if (urlToOpen) {
            event.waitUntil(clients.openWindow(urlToOpen));
        }
    }
});