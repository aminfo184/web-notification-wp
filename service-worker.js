/**
 * Service Worker for Web Notification WP
 * Handles push events and notification clicks.
 */

self.addEventListener('push', function(event) {
  // اطلاعات پیش‌فرض در صورتی که پیامی از سرور نیاید
  let payload = {
    title: 'پیام جدید',
    body: 'شما یک پیام جدید دارید.',
    icon: '/favicon.ico', // یک آیکون پیش‌فرض
    url: self.location.origin,
  };

  // تلاش برای خواندن اطلاعات ارسال شده از سرور
  if (event.data) {
    try {
      payload = event.data.json();
    } catch (e) {
      console.error('WNW Error: Could not parse push data.', e);
    }
  }

  // تعریف گزینه‌های نوتیفیکیشن
  const options = {
    body: payload.body,
    icon: payload.icon, // آیکون داینامیک
    data: {
      url: payload.url, // آدرس داینامیک
    },
    // می‌توانیم گزینه‌های دیگری مثل تصویر بزرگ (image) یا دکمه‌های اکشن (actions) را هم اضافه کنیم
    // image: payload.image, 
  };

  // نمایش نوتیفیکیشن
  event.waitUntil(
    self.registration.showNotification(payload.title, options)
  );
});

self.addEventListener('notificationclick', function(event) {
  // بستن نوتیفیکیشن پس از کلیک
  event.notification.close();

  // دریافت آدرس URL از داده‌های نوتیفیکیشن
  const urlToOpen = event.notification.data.url;

  // باز کردن پنجره یا تب جدید با آدرس مورد نظر
  event.waitUntil(
    clients.matchAll({
      type: 'window',
      includeUncontrolled: true
    }).then(function(clientList) {
      // اگر تبی با همین آدرس باز بود، آن را focus کن
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      // در غیر این صورت، یک تب جدید باز کن
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});
