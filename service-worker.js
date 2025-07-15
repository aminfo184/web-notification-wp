/**
 * Service Worker for Web Notification WP
 * Handles push events and notification clicks.
 */

self.addEventListener('push', function(event) {
  console.log('[WNW Service Worker] Push Received.');
  console.log(`[WNW Service Worker] Raw Push data:`, event.data);

  let payload;

  // --- START DEBUGGING BLOCK ---
  try {
    // We try to parse the JSON from the server
    payload = event.data.json();
    console.log('[WNW Service Worker] Push data successfully parsed:', payload);
  } catch (e) {
    // If parsing fails, we log the error and create a default notification
    console.error('[WNW Service Worker] Error parsing push data:', e);
    console.log('[WNW Service Worker] Attempting to read data as text:', event.data.text());
    
    payload = {
      title: 'خطا در نوتیفیکیشن',
      body: 'داده‌های ارسال شده از سرور قابل پردازش نبودند.',
      icon: '/favicon.ico',
      url: self.location.origin,
    };
  }
  // --- END DEBUGGING BLOCK ---


  const options = {
    body: payload.body,
    icon: payload.icon,
    image: payload.image, // Added image support
    data: {
      url: payload.url,
    },
  };

  event.waitUntil(
    self.registration.showNotification(payload.title, options)
  );
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const urlToOpen = event.notification.data.url;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});