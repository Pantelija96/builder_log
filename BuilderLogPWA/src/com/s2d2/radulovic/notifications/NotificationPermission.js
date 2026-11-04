import isInstalledIosPwa from '../utils/InstalledIosPwa.js';
import getSharedPreferences from '../utils/SharedPreferences.js';

export function getNotificationPermission() {
  if (!isInstalledIosPwa() || !window.isSecureContext
    || typeof window.Notification?.requestPermission !== 'function') return 'unsupported';
  return window.Notification.permission;
}

// Call directly from the tap handler: iOS requires transient user activation.
export function requestNotificationPermission() {
  const permission = getNotificationPermission();
  return permission === 'default'
    ? window.Notification.requestPermission()
    : Promise.resolve(permission);
}

// This installation's preference, independent of account/profile settings.
// Token registration honors this preference; it is also mirrored for the worker.
export function areNotificationsEnabled() {
  return getNotificationPermission() === 'granted'
    && getSharedPreferences('notification_preferences').getBoolean('notifications_enabled', true);
}

export function setNotificationsEnabled(enabled) {
  getSharedPreferences('notification_preferences').edit()
    .putBoolean('notifications_enabled', enabled)
    .putBoolean('permission_prompt_shown', true).apply();
  if (isInstalledIosPwa() && window.isSecureContext && window.navigator.serviceWorker) {
    return import('./IosPushNotifications.js').then(module => module.synchronizeIosPushNotifications());
  }
}
