import { mapNotificationType, notificationToJSON } from './NotificationPayload.js';
import Session, { jsonText } from '../network/Session.js';
import { readNotificationPreferences, updateNotificationPreferences, getStoredNotificationPreferences } from './NotificationStorage.js';
import { notificationInboxName, readSavedNotificationArray, appendNotification } from './NotificationInbox.js';
import WebUtils from '../network/WebUtils.js';
import getSharedPreferences from '../utils/SharedPreferences.js';
import isInstalledIosPwa from '../utils/InstalledIosPwa.js';
import Notification from './Notification.js';
import TaskNotification from './TaskNotification.js';
import NoteNotification from './NoteNotification.js';
import DocumentNotification from './DocumentNotification.js';
import WorkerNotification from './WorkerNotification.js';

const PREFERENCES_NAME = 'push_notification_preferences';

// Same method order and data rules as Android NotificationsUtils.
// Inbox operations use the worker/page IndexedDB store and return promises.
// Login preferences and push registration retain their existing APIs.
export default class NotificationsUtils {
  static FOREGROUND_NOTIFICATIONS_PREFERENCES_NAME = null;
  static TOKEN_KEY = 'fcm_token';

  static areNotificationsEnabled() {
    return getSharedPreferences('notification_preferences').getBoolean('notifications_enabled', true);
  }

  static setNotificationsEnabled(enabled) {
    getSharedPreferences('notification_preferences').edit().putBoolean('notifications_enabled', enabled).apply();
    if (!enabled && isInstalledIosPwa() && window.navigator.serviceWorker) {
      // Web equivalent of cancelAll: dismiss this app's displayed notifications.
      // Preference saving is synchronous; dismissal returns a Promise.
      const scope = new URL(import.meta.env?.BASE_URL ?? '/app/', window.location.href).href;
      return window.navigator.serviceWorker.getRegistration(scope).then(async registration => {
        if (!registration) return;
        for (const notification of await registration.getNotifications()) notification.close();
      });
    }
  }

  static registerFCMToken(callback = {
    onSuccess(responseBody) {},
    onFailure(exception) { console.error('FCM TOKEN REGISTRATION', 'Token registration failed', exception); },
  }, registration = null) {
    if (!isInstalledIosPwa()) return;
    const preferences = getSharedPreferences(PREFERENCES_NAME);
    const token = registration?.token ?? preferences.getString(NotificationsUtils.TOKEN_KEY, null);
    const sessionToken = registration?.authorization ?? Session.getSessionToken();
    if (sessionToken == null || sessionToken.trim() === '' || token == null || token.trim() === '') return;

    const body = { platform: 'web', fcm_token: token };
    return WebUtils.sendPOSTRequest('push/register', JSON.stringify(body),
      { Authorization: sessionToken }, callback);
  }

  static unregisterFCMToken(callback, registration = null) {
    if (!isInstalledIosPwa()) { callback.onSuccess(''); return; }
    const preferences = getSharedPreferences(PREFERENCES_NAME);
    const token = registration?.token ?? preferences.getString(NotificationsUtils.TOKEN_KEY, null);
    if (token == null || token.trim() === '') { callback.onSuccess(''); return; }
    const authorization = registration?.authorization ?? Session.getSessionToken();
    if (authorization == null || authorization.trim() === '') {
      callback.onFailure(new Error('Missing session token'));
      return;
    }

    const body = { fcm_token: token };
    return WebUtils.sendPOSTRequest('push/unregister', JSON.stringify(body), { Authorization: authorization }, callback);
  }

  static async addNotification(notification) {
    if (!NotificationsUtils.areNotificationsEnabled()) return;
    const role = NotificationsUtils.#getRoleFromPreferences();
    NotificationsUtils.FOREGROUND_NOTIFICATIONS_PREFERENCES_NAME = notificationInboxName(role);
    await appendNotification(role, NotificationsUtils.#convertToJSON(notification));
  }

  static removeNotification(notification) {
    return NotificationsUtils.removeNotifications([notification]);
  }

  static async removeNotifications(notifications) {
    const role = NotificationsUtils.#getRoleFromPreferences();
    const name = notificationInboxName(role);
    NotificationsUtils.FOREGROUND_NOTIFICATIONS_PREFERENCES_NAME = name;
    const ids = new Set(notifications.map(notification => notification.getId()));

    await updateNotificationPreferences(name, saved => {
      const alreadySaved = readSavedNotificationArray(saved);
      removeSavedIds(alreadySaved, ids);
      return { ...saved, notifications: jsonText(alreadySaved.value) };
    });
  }

  static async removeNotificationsByGroup(type) {
    const role = NotificationsUtils.#getRoleFromPreferences();
    const name = notificationInboxName(role);
    NotificationsUtils.FOREGROUND_NOTIFICATIONS_PREFERENCES_NAME = name;

    await updateNotificationPreferences(name, saved => {
      const alreadySaved = readSavedNotificationArray(saved);
      const ids = new Set();
      for (let i = 0; i < alreadySaved.length(); i++) {
        const notificationJSON = alreadySaved.getJSONObject(i);
        if (NotificationsUtils.mapType(notificationJSON.getString('type')) === type) {
          ids.add(NotificationsUtils.#convertFromJSON(notificationJSON).getId());
        }
      }
      removeSavedIds(alreadySaved, ids);
      return { ...saved, notifications: jsonText(alreadySaved.value) };
    });
  }

  static async readAllNotifications() {
    const role = NotificationsUtils.#getRoleFromPreferences();
    const name = notificationInboxName(role);
    NotificationsUtils.FOREGROUND_NOTIFICATIONS_PREFERENCES_NAME = name;
    const alreadySaved = readSavedNotificationArray(await readNotificationPreferences(name));
    const allNotifications = [];

    for (let i = 0; i < alreadySaved.length(); i++) {
      try {
        allNotifications.push(NotificationsUtils.#convertFromJSON(alreadySaved.getJSONObject(i)));
      } catch (exception) {
        console.error('NotificationTrace', 'Could not read saved notification at index=' + i + ' role=' + role, exception);
      }
    }
    return allNotifications;
  }

  static #convertToJSON(notification) {
    return notificationToJSON(notification);
  }

  static #convertFromJSON(notificationJSON) {
    const type = NotificationsUtils.mapType(notificationJSON.getString('type'));

    if (type === Notification.NOTIFICATION_TYPE.NOTE_CREATED) {
      return new NoteNotification(notificationJSON.getInt('id'), notificationJSON.getString('title'),
        notificationJSON.getString('description'), type, notificationJSON.getString('note_id'));
    } else if (type === Notification.NOTIFICATION_TYPE.DOCUMENT_CREATED) {
      return new DocumentNotification(notificationJSON.getInt('id'), notificationJSON.getString('title'),
        notificationJSON.getString('description'), type, notificationJSON.getString('construction_site_id'));
    } else if (type === Notification.NOTIFICATION_TYPE.TASK_COMPLETED) {
      const taskNotificationType = TaskNotification.mapTaskNotificationType(notificationJSON.getString('task_notification_type'));

      if (taskNotificationType === TaskNotification.TASK_NOTIFICATION_TYPE.MANAGER
        || taskNotificationType === TaskNotification.TASK_NOTIFICATION_TYPE.EXCAVATOR_DRIVER
        || taskNotificationType === TaskNotification.TASK_NOTIFICATION_TYPE.TRUCK_DRIVER) {
        return new TaskNotification(notificationJSON.getInt('id'), notificationJSON.getString('title'),
          notificationJSON.getString('description'), type, taskNotificationType, notificationJSON.getString('worker_id'));
      } else if (taskNotificationType === TaskNotification.TASK_NOTIFICATION_TYPE.CONSTRUCTION_SITE) {
        return new TaskNotification(notificationJSON.getInt('id'), notificationJSON.getString('title'),
          notificationJSON.getString('description'), type, taskNotificationType, notificationJSON.getString('construction_site_id'));
      }
      return new TaskNotification(notificationJSON.getInt('id'), notificationJSON.getString('title'),
        notificationJSON.getString('description'), type, taskNotificationType);
    } else if (type === Notification.NOTIFICATION_TYPE.WORKER_DAY_STARTED
      || type === Notification.NOTIFICATION_TYPE.WORKER_DAY_FINISHED) {
      return new WorkerNotification(notificationJSON.getInt('id'), notificationJSON.getString('title'),
        notificationJSON.getString('description'), type, notificationJSON.getString('worker_id'));
    }

    return new Notification(notificationJSON.getInt('id'), notificationJSON.getString('title'),
      notificationJSON.getString('description'), type);
  }

  static mapType(type) {
    return mapNotificationType(type);
  }

  static getNotificationPreferences() {
    return getStoredNotificationPreferences(notificationInboxName(NotificationsUtils.#getRoleFromPreferences()));
  }

  static #getRoleFromPreferences() {
    return getSharedPreferences('com.s2d2.radulovic.LOGIN_PREFERENCES').getString('user_role', null);
  }
}

function removeSavedIds(alreadySaved, ids) {
  // Keep entries received after the caller selected the notifications to remove.
  for (let i = alreadySaved.length() - 1; i >= 0; i--) {
    if (ids.has(alreadySaved.getJSONObject(i).getInt('id'))) alreadySaved.value.splice(i, 1);
  }
}
