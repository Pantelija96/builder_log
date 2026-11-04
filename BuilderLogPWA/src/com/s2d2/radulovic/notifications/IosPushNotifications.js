import Session from '../network/Session.js';
import getSharedPreferences from '../utils/SharedPreferences.js';
import isInstalledIosPwa from '../utils/InstalledIosPwa.js';
import { areNotificationsEnabled } from './NotificationPermission.js';
import NotificationsUtils from './NotificationsUtils.js';
import PushTokenLifecycle from './PushTokenLifecycle.js';
import { getIosFcmToken, unsubscribeIosPush, initializeIosNotificationInfrastructure, clearIosSystemNotifications } from './FirebaseMessaging.js';
import { updateNotificationPreferences } from './NotificationStorage.js';

const PREFERENCES_NAME = 'push_notification_preferences';
const preferences = () => getSharedPreferences(PREFERENCES_NAME);
const lifecycle = new PushTokenLifecycle({
  readState() {
    const user = Session.getCurrentUser();
    return {
      supported: isInstalledIosPwa() && window.isSecureContext && !!window.navigator.serviceWorker,
      enabled: areNotificationsEnabled(),
      workerId: user?.getId() ?? null,
      role: user?.getRole() ?? null,
      authorization: Session.getSessionToken(),
    };
  },
  readToken() {
    const saved = preferences();
    const token = saved.getString(NotificationsUtils.TOKEN_KEY, null);
    return token ? {
      token,
      workerId: saved.getString('worker_id', null),
      role: saved.getString('user_role', null),
      registered: saved.getBoolean('registered', false),
    } : null;
  },
  async saveToken(binding) {
    const values = binding ? {
      fcm_token: binding.token, worker_id: binding.workerId,
      user_role: binding.role, registered: binding.registered,
    } : {};
    // Worker-visible binding contains no password or API authorization token.
    await updateNotificationPreferences(PREFERENCES_NAME, () => values);
    const editor = preferences().edit();
    for (const key of ['fcm_token', 'worker_id', 'user_role', 'registered']) editor.remove(key);
    for (const [key, value] of Object.entries(values)) editor.putString(key, value);
    editor.apply();
  },
  saveEnabled(enabled) {
    return updateNotificationPreferences('notification_preferences',
      saved => ({ ...saved, notifications_enabled: enabled }));
  },
  getToken: getIosFcmToken,
  unsubscribe: unsubscribeIosPush,
  register(token, authorization) {
    return new Promise((resolve, reject) => NotificationsUtils.registerFCMToken({
      onSuccess: resolve, onFailure: reject,
    }, { token, authorization }));
  },
  unregister(token, authorization) {
    return new Promise((resolve, reject) => NotificationsUtils.unregisterFCMToken({
      onSuccess: resolve, onFailure: reject,
    }, { token, authorization }));
  },
});

export function synchronizeIosPushNotifications() {
  return lifecycle.synchronize();
}

export function logoutIosPushNotifications() {
  return lifecycle.logout();
}

export function refreshIosPushNotifications() {
  return synchronizeIosPushNotifications().catch(error => {
    console.error('Notification token synchronization failed', error);
  });
}

let started = false;
export function startIosPushNotifications() {
  if (!isInstalledIosPwa()) return Promise.resolve(null);
  if (started) return initializeIosNotificationInfrastructure();
  started = true;
  // Web FCM has no Android-style onNewToken callback. Re-read the SDK token
  // after login, foreground return, and reconnection; it may have rotated.
  const clearVisibleAlerts = () => {
    if (document.visibilityState !== 'visible') return;
    clearIosSystemNotifications().catch(error => {
      console.error('Notification dismissal failed', error);
    });
  };
  const resume = () => {
    if (document.visibilityState !== 'visible') return;
    clearVisibleAlerts();
    refreshIosPushNotifications();
  };
  window.addEventListener('online', refreshIosPushNotifications);
  window.addEventListener('pageshow', resume);
  document.addEventListener('visibilitychange', resume);
  clearVisibleAlerts();
  refreshIosPushNotifications();
  return initializeIosNotificationInfrastructure();
}
