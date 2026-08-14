importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

// Initialize Firebase compat app
firebase.initializeApp({
  apiKey: "AIzaSyAZ15E4ngDSeJyYwdfCczYNck43fbOYyVc",
  authDomain: "okcampus-e15e0.firebaseapp.com",
  projectId: "okcampus-e15e0",
  storageBucket: "okcampus-e15e0.firebasestorage.app",
  messagingSenderId: "586874783991",
  appId: "1:586874783991:web:0bc794461e94401b40ebf2",
  measurementId: "G-ZW6NVJWYWL"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  console.log('🔔 [firebase-messaging-sw.js] Background Push Received:', payload);

  const title = payload.notification?.title || payload.data?.title || 'OkayERP Alert';
  const body = payload.notification?.body || payload.data?.body || 'You have a new notification.';
  const options = {
    body: body,
    icon: '/logo.png',
    badge: '/logo.png',
    tag: title + '|' + body,
    renotify: false,
    data: payload.data || {}
  };

  return self.registration.showNotification(title, options);
});

// Handle Notification Clicks
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data?.action_url || event.notification.data?.url || '/')
  );
});
