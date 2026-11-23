import { createNotificationTap, saveNotificationTap, NOTIFICATION_TAP } from './NotificationNavigation.js';

// Only OS alerts are removed. Notification history and login/token state are
// independent and must not be changed by opening or returning to the app.
export async function clearSystemNotifications(registration) {
  if (!registration) return;
  for (const notification of await registration.getNotifications()) notification.close();
}

async function openApp(registration, clients) {
  const windows = await clients.matchAll({ type: 'window', includeUncontrolled: true });
  for (const client of windows) {
    if (!client.url.startsWith(registration.scope)) continue;
    try {
      // Preserve the current screen and in-memory session.
      return await client.focus();
    } catch {
      // The window may have closed between matchAll and focus.
    }
  }
  return clients.openWindow(registration.scope);
}

export function handleNotificationClick(event, registration, clients) {
  // Install this handler before Firebase's own click handler.
  event.stopImmediatePropagation();
  event.notification.close();
  // Opening the app must still work if dismissing another alert fails.
  const tap = createNotificationTap(event.notification.data);
  // Start opening immediately while the OS tap still grants user activation.
  const opening = openApp(registration, clients);
  const saving = tap ? saveNotificationTap(tap) : Promise.resolve();
  const routing = Promise.allSettled([opening, saving]).then(([opened, saved]) => {
    if (saved.status === 'rejected') console.error('Notification tap could not be saved', saved.reason);
    if (opened.status === 'rejected') throw opened.reason;
    if (tap) opened.value?.postMessage({ type: NOTIFICATION_TAP, tap });
  });
  event.waitUntil(Promise.allSettled([
    clearSystemNotifications(registration),
    routing,
  ]).then(results => {
    for (const result of results) {
      if (result.status === 'rejected') console.error('Notification click failed', result.reason);
    }
  }));
}
