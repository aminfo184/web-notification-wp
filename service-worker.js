/**
 * Service Worker for Web Notification WP
 * Handles push events and notification clicks.
 * Enhanced with better debugging and error handling
 */

console.log('[WNW Service Worker] Loaded and running');

self.addEventListener('push', function(event) {
  console.log('[WNW Service Worker] Push event received');
  console.log('[WNW Service Worker] Event data:', event.data);

  let payload;
  let notificationData;

  try {
    if (event.data) {
      // Try to parse as JSON first
      const rawData = event.data.text();
      console.log('[WNW Service Worker] Raw data:', rawData);
      
      payload = JSON.parse(rawData);
      console.log('[WNW Service Worker] Parsed payload:', payload);
      
      notificationData = {
        title: payload.title || 'اطلاعیه جدید',
        body: payload.body || payload.message || 'شما یک اطلاعیه جدید دارید',
        icon: payload.icon || '/favicon.ico',
        image: payload.image || null,
        badge: payload.badge || payload.icon || '/favicon.ico',
        tag: payload.tag || 'default-notification',
        data: {
          url: payload.url || self.location.origin,
          timestamp: payload.timestamp || Date.now()
        },
        actions: payload.actions || [],
        requireInteraction: false,
        silent: false
      };
      
    } else {
      throw new Error('No data in push event');
    }
  } catch (e) {
    console.error('[WNW Service Worker] Error parsing push data:', e);
    
    // Fallback notification
    notificationData = {
      title: '🔔 اطلاعیه جدید',
      body: 'یک اطلاعیه جدید دریافت شد',
      icon: '/favicon.ico',
      tag: 'fallback-notification',
      data: {
        url: self.location.origin,
        timestamp: Date.now()
      }
    };
  }

  console.log('[WNW Service Worker] Final notification data:', notificationData);

  const showNotification = self.registration.showNotification(
    notificationData.title,
    {
      body: notificationData.body,
      icon: notificationData.icon,
      image: notificationData.image,
      badge: notificationData.badge,
      tag: notificationData.tag,
      data: notificationData.data,
      actions: notificationData.actions,
      requireInteraction: notificationData.requireInteraction,
      silent: notificationData.silent
    }
  );

  event.waitUntil(showNotification);
});

self.addEventListener('notificationclick', function(event) {
  console.log('[WNW Service Worker] Notification clicked:', event.notification.tag);
  
  const notification = event.notification;
  const action = event.action;
  const url = notification.data.url || self.location.origin;
  
  console.log('[WNW Service Worker] Click action:', action);
  console.log('[WNW Service Worker] Target URL:', url);
  
  notification.close();

  // Handle action buttons
  if (action === 'open') {
    // Open specific action
    event.waitUntil(clients.openWindow(url));
  } else if (action === 'close') {
    // Just close, do nothing
    return;
  } else {
    // Default click action
    event.waitUntil(
      clients.matchAll({
        type: 'window',
        includeUncontrolled: true
      }).then(function(clientList) {
        // Try to focus existing window
        for (let i = 0; i < clientList.length; i++) {
          const client = clientList[i];
          if (client.url.indexOf(url) >= 0 && 'focus' in client) {
            console.log('[WNW Service Worker] Focusing existing window');
            return client.focus();
          }
        }
        
        // Open new window
        if (clients.openWindow) {
          console.log('[WNW Service Worker] Opening new window:', url);
          return clients.openWindow(url);
        }
      })
    );
  }
});

self.addEventListener('notificationclose', function(event) {
  console.log('[WNW Service Worker] Notification closed:', event.notification.tag);
});

// Handle service worker installation
self.addEventListener('install', function(event) {
  console.log('[WNW Service Worker] Installing');
  self.skipWaiting();
});

// Handle service worker activation
self.addEventListener('activate', function(event) {
  console.log('[WNW Service Worker] Activating');
  event.waitUntil(self.clients.claim());
});

// Handle errors
self.addEventListener('error', function(event) {
  console.error('[WNW Service Worker] Error:', event.error);
});

// Handle unhandled promise rejections
self.addEventListener('unhandledrejection', function(event) {
  console.error('[WNW Service Worker] Unhandled promise rejection:', event.reason);
});

// Send a message to all clients when service worker is ready
self.addEventListener('message', function(event) {
  console.log('[WNW Service Worker] Message received:', event.data);
  
  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({
      type: 'VERSION',
      version: '1.0.0',
      ready: true
    });
  }
});

console.log('[WNW Service Worker] All event listeners registered');