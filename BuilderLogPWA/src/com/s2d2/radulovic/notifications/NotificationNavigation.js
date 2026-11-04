import { readNotificationPreferences, updateNotificationPreferences } from './NotificationStorage.js';

export const NOTIFICATION_TAP = 'radulovic:notification-tap';
const PREFERENCES_NAME = 'notification_navigation';

// Persist the tap before handing it to the page, including a cold/manual-login start.
export function createNotificationTap(data) {
  if (!data?.notification?.type) return null;
  return { id: crypto.randomUUID(), notification: data.notification,
    workerId: data.worker_id, role: data.user_role };
}
export function saveNotificationTap(tap) {
  return updateNotificationPreferences(PREFERENCES_NAME, () => ({ pending: tap }));
}
export async function readNotificationTap() {
  return (await readNotificationPreferences(PREFERENCES_NAME)).pending ?? null;
}
export function removeNotificationTap(id) {
  return updateNotificationPreferences(PREFERENCES_NAME,
    saved => saved.pending?.id === id ? {} : saved);
}
