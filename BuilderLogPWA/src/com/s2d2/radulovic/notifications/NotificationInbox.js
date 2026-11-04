import { JSONArray, parseAndroidJson, jsonText } from '../network/Session.js';
import { updateNotificationPreferences } from './NotificationStorage.js';

export const notificationInboxName = role => 'foreground_push_notifications_preferences_' + role;

// Preserve Android org.json parsing for stored arrays, including numeric types.
export function readSavedNotificationArray(preferences) {
  const value = parseAndroidJson(preferences.notifications ?? '[]');
  if (!Array.isArray(value)) throw new TypeError('Expected JSONArray');
  return new JSONArray(value);
}

export function appendNotification(role, notificationJSON, binding = null) {
  return updateNotificationPreferences(notificationInboxName(role), (saved, settings, currentBinding) => {
    // A worker may finish parsing after OFF/logout/account switching. Check the
    // receiving binding and append under the same IndexedDB write transaction.
    if (binding && (settings.notifications_enabled === false
      || currentBinding.fcm_token !== binding.fcm_token
      || currentBinding.worker_id !== binding.worker_id
      || currentBinding.user_role !== binding.user_role)) return;
    const alreadySaved = readSavedNotificationArray(saved);
    alreadySaved.value.push(notificationJSON);
    return { ...saved, notifications: jsonText(alreadySaved.value) };
  }, binding ? ['notification_preferences', 'push_notification_preferences'] : []);
}
