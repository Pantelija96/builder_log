import { initializeApp } from 'firebase/app';
import { getMessaging } from 'firebase/messaging/sw';
import firebaseConfig from './FirebaseConfig.js';
import MyFirebaseMessagingService from './MyFirebaseMessagingService.js';
import { initializeNotificationStorage } from './NotificationStorage.js';
import { handleNotificationClick } from './SystemNotifications.js';

const receiver = new MyFirebaseMessagingService(self.registration);

// Register before getMessaging installs Firebase's listener. Its default handler
// skips system notifications for visible windows; iOS requires visible delivery.
// Handle the standard FCM push envelope here, once, and keep the worker alive
// until showNotification finishes. This also prevents duplicate auto-displays
// for payloads containing Firebase's optional "notification" section.
self.addEventListener('push', event => {
  event.stopImmediatePropagation();
  event.waitUntil((async () => {
    try {
      await receiver.onMessageReceived(event.data?.json());
    } catch (error) {
      console.error('Notification delivery failed', error);
    }
  })());
});

self.addEventListener('notificationclick', event => {
  handleNotificationClick(event, self.registration, self.clients);
});

initializeApp(firebaseConfig);
// Keep Firebase's subscription-change handling; token registration is unchanged.
getMessaging();

self.addEventListener('install', event => {
  event.waitUntil(initializeNotificationStorage().then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim());
});
