import Notification, { parseNotificationInt } from './Notification.js';
import { createNotification, notificationToJSON } from './NotificationPayload.js';
import { readNotificationPreferences } from './NotificationStorage.js';
import { appendNotification } from './NotificationInbox.js';

// The web counterpart of Android's message receiver. Token changes are handled
// by PushTokenLifecycle. Inbox persistence is shared with the page.
export default class MyFirebaseMessagingService {
  static PREFERENCES_NAME = 'push_notification_preferences';
  static TOKEN_KEY = 'fcm_token';
  static EXTRA_NOTIFICATION_TAP = 'notification_tap';

  #registration;
  #readPreferences;
  #saveNotification;

  constructor(registration, readPreferences = readNotificationPreferences, saveNotification = saveReceivedNotification) {
    this.#registration = registration;
    this.#readPreferences = readPreferences;
    this.#saveNotification = saveNotification;
  }

  async onMessageReceived(message) {
    const data = message?.data ?? {};
    const display = message?.notification;
    if ((!data || typeof data !== 'object' || Array.isArray(data)
      || Object.keys(data).length === 0) && !display) return false;

    const binding = await this.#displayBinding();
    if (!binding) return false;
    let notification;
    let metadata;
    try {
      notification = this.#createNotification(data);
      metadata = notificationToJSON(notification);
    } catch {
      // The backend's test push has no notification_id. Also keep readable
      // title/body visible if an event has incomplete navigation metadata.
      notification = new Notification(null, data.title ?? display?.title ?? null,
        data.body ?? display?.body ?? null, null);
      metadata = notificationToJSON(notification);
    }
    if (notification.getId() != null) {
      try {
        await this.#saveNotification(notification, binding);
      } catch (error) {
        // Keep the existing iOS system alert even if inbox storage is unavailable.
        console.error('Notification inbox saving failed', error);
      }
    }
    return this.#showNotification(notification, metadata, message, binding);
  }

  #createNotification(data) {
    return createNotification(parseNotificationInt(data.notification_id), data);
  }

  async #displayBinding() {
    const [preferences, binding] = await Promise.all([
      this.#readPreferences('notification_preferences'),
      this.#readPreferences(MyFirebaseMessagingService.PREFERENCES_NAME),
    ]);
    // Only installed iOS apps create this binding. The worker runs without a
    // window, including after the app has been closed, so use persisted state.
    if (preferences.notifications_enabled === false
      || !binding.fcm_token || binding.worker_id == null) return null;
    return binding;
  }

  async #showNotification(notification, metadata, message, binding) {
    const current = await this.#displayBinding();
    if (!current || current.worker_id !== binding.worker_id
      || current.fcm_token !== binding.fcm_token) return false;

    const id = notification.getId();
    const messageId = message.messageId ?? message.fcmMessageId;
    const tag = id != null ? 'push_event:' + id
      : messageId ? 'push_message:' + messageId : undefined;
    const options = {
      body: notification.getDescription() ?? '',
      icon: new URL('assets/icons/launcher-192.png', this.#registration.scope).href,
      lang: 'sr-Latn',
      ...(tag ? { tag, renotify: true } : {}),
      data: {
        notification: metadata,
        worker_id: binding.worker_id,
        user_role: binding.user_role,
      },
    };
    try {
      // iOS requires a visible notification for every push, even if a window is
      // visible. Native grouping, presentation and sounds are controlled by iOS.
      await this.#registration.showNotification(notification.getTitle() || 'Radulović gradnja', options);
      return true;
    } catch (error) {
      // As on Android, permission can be revoked between delivery and display.
      if (error.name === 'NotAllowedError' || error.name === 'SecurityError') return false;
      throw error;
    }
  }
}

function saveReceivedNotification(notification, binding) {
  return appendNotification(binding.user_role ?? null, notificationToJSON(notification), binding);
}
