import firebaseConfig, { VAPID_PUBLIC_KEY } from './FirebaseConfig.js';
import isInstalledIosPwa from '../utils/InstalledIosPwa.js';
import { initializeNotificationStorage } from './NotificationStorage.js';
import { clearSystemNotifications } from './SystemNotifications.js';

let initialization;

function waitUntilActive(registration) {
  if (registration.active) return Promise.resolve();
  const worker = registration.installing ?? registration.waiting;
  if (!worker) return Promise.reject(new Error('Notification worker did not install.'));
  return new Promise((resolve, reject) => {
    const timeout = setTimeout(() => finish(new Error('Notification worker activation timed out.')), 15000);
    function finish(error) {
      clearTimeout(timeout);
      worker.removeEventListener('statechange', changed);
      if (error) reject(error); else resolve();
    }
    function changed() {
      if (worker.state === 'activated') finish();
      else if (worker.state === 'redundant') finish(new Error('Notification worker installation failed.'));
    }
    worker.addEventListener('statechange', changed);
    changed();
  });
}

async function prepare() {
  const [{ initializeApp, getApps }, { getMessaging, isSupported }] = await Promise.all([
    import('firebase/app'), import('firebase/messaging'),
  ]);
  if (!await isSupported()) return null;
  await initializeNotificationStorage();
  const base = new URL(import.meta.env.BASE_URL, window.location.href);
  const registration = await window.navigator.serviceWorker.register(
    new URL('firebase-messaging-sw.js', base).href,
    { scope: base.href, updateViaCache: 'none' },
  );
  await waitUntilActive(registration);
  const app = getApps().find(app => app.name === '[DEFAULT]') ?? initializeApp(firebaseConfig);
  return { app, messaging: getMessaging(app), registration };
}

// Infrastructure only: no permission prompt, push subscription, token, API
// registration, notification display or foreground handler is started here.
export function initializeIosNotificationInfrastructure() {
  if (!isInstalledIosPwa() || !window.isSecureContext || !window.navigator.serviceWorker) {
    return Promise.resolve(null);
  }
  initialization ??= prepare().catch(error => {
    initialization = undefined;
    throw error;
  });
  return initialization;
}

// Permission is requested only by the UI's tap handler, never by startup.
export async function getIosFcmToken() {
  if (!isInstalledIosPwa() || window.Notification?.permission !== 'granted') return null;
  const infrastructure = await initializeIosNotificationInfrastructure();
  if (!infrastructure) throw new Error('Notifications are not supported on this device.');
  const { getToken } = await import('firebase/messaging');
  if (window.Notification?.permission !== 'granted') return null;
  return getToken(infrastructure.messaging, {
    vapidKey: VAPID_PUBLIC_KEY,
    serviceWorkerRegistration: infrastructure.registration,
  });
}

export async function unsubscribeIosPush() {
  if (!isInstalledIosPwa() || !window.navigator.serviceWorker) return;
  const scope = new URL(import.meta.env.BASE_URL, window.location.href).href;
  const registration = await window.navigator.serviceWorker.getRegistration(scope);
  if (!registration) return;
  // Direct browser unsubscribe also works offline. Firebase deleteToken first
  // contacts FCM and may fail before removing the device's subscription.
  const subscription = await registration.pushManager.getSubscription();
  if (subscription && !await subscription.unsubscribe()) throw new Error('Notification unsubscribe failed.');
  for (const notification of await registration.getNotifications()) notification.close();
}

// Resume cleanup works with an existing worker even while Firebase/network
// initialization is unavailable. It never creates or removes a subscription.
export async function clearIosSystemNotifications() {
  if (!isInstalledIosPwa() || !window.isSecureContext || !window.navigator.serviceWorker) return;
  const scope = new URL(import.meta.env?.BASE_URL ?? '/app/', window.location.href).href;
  const registration = await window.navigator.serviceWorker.getRegistration(scope);
  await clearSystemNotifications(registration);
}
