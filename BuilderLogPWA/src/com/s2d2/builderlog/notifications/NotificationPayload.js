import Notification from './Notification.js';
import TaskNotification from './TaskNotification.js';
import NoteNotification from './NoteNotification.js';
import DocumentNotification from './DocumentNotification.js';
import WorkerNotification from './WorkerNotification.js';

// Pure Android data rules shared by the page and service worker.
export function mapNotificationType(type) {
  switch (type.toLowerCase()) {
    case 'document_created': return Notification.NOTIFICATION_TYPE.DOCUMENT_CREATED;
    case 'task_assigned': return Notification.NOTIFICATION_TYPE.TASK_ASSIGNED;
    case 'task_completed': return Notification.NOTIFICATION_TYPE.TASK_COMPLETED;
    case 'worker_day_started': return Notification.NOTIFICATION_TYPE.WORKER_DAY_STARTED;
    case 'worker_day_finished': return Notification.NOTIFICATION_TYPE.WORKER_DAY_FINISHED;
    case 'note_created': return Notification.NOTIFICATION_TYPE.NOTE_CREATED;
    case 'truck_assigned': return Notification.NOTIFICATION_TYPE.TRUCK_ASSIGNED;
    case 'excavator_assigned': return Notification.NOTIFICATION_TYPE.EXCAVATOR_ASSIGNED;
  }
  return null;
}

export function notificationToJSON(notification) {
  const type = notification.getType();

  const baseNotification = {
    id: notification.getId(), title: notification.getTitle(),
    description: notification.getDescription(), type,
  };

  if (type === Notification.NOTIFICATION_TYPE.NOTE_CREATED) {
    baseNotification.note_id = notification.getNoteId();
  } else if (type === Notification.NOTIFICATION_TYPE.DOCUMENT_CREATED) {
    baseNotification.construction_site_id = notification.getConstructionSiteId();
  } else if (type === Notification.NOTIFICATION_TYPE.TASK_COMPLETED) {
    baseNotification.task_notification_type = notification.getTaskNotificationType();

    if (notification.getTaskNotificationType() === TaskNotification.TASK_NOTIFICATION_TYPE.MANAGER
      || notification.getTaskNotificationType() === TaskNotification.TASK_NOTIFICATION_TYPE.EXCAVATOR_DRIVER
      || notification.getTaskNotificationType() === TaskNotification.TASK_NOTIFICATION_TYPE.TRUCK_DRIVER) {
      baseNotification.worker_id = notification.getWorkerId();
    } else if (notification.getTaskNotificationType() === TaskNotification.TASK_NOTIFICATION_TYPE.CONSTRUCTION_SITE) {
      baseNotification.construction_site_id = notification.getConstructionSiteId();
    }
  } else if (type === Notification.NOTIFICATION_TYPE.WORKER_DAY_STARTED
    || type === Notification.NOTIFICATION_TYPE.WORKER_DAY_FINISHED) {
    baseNotification.worker_id = notification.getWorkerId();
  }
  // JSONObject.put(key, Java null) removes the key instead of writing JSON null.
  return Object.fromEntries(Object.entries(baseNotification).filter(([, value]) => value != null));
}

// Recreate the same notification subclasses after crossing worker/page storage.
export function notificationFromJSON(value) {
  if (!value.type) return new Notification(value.id, value.title ?? null, value.description ?? null, null);
  return createNotification(value.id, Object.fromEntries(
    Object.entries({ ...value, body: value.description })
      .map(([key, item]) => [key, item == null ? item : String(item)]),
  ));
}

export function createNotification(id, data) {
  const type = mapNotificationType(data.type);
  const title = data.title ?? null;
  const body = data.body ?? null;
  if (type === Notification.NOTIFICATION_TYPE.NOTE_CREATED) {
    return new NoteNotification(id, title, body, type, data.note_id);
  } else if (type === Notification.NOTIFICATION_TYPE.DOCUMENT_CREATED) {
    return new DocumentNotification(id, title, body, type, data.construction_site_id);
  } else if (type === Notification.NOTIFICATION_TYPE.TASK_COMPLETED) {
    const taskType = TaskNotification.mapTaskNotificationType(data.task_notification_type);
    if (taskType === TaskNotification.TASK_NOTIFICATION_TYPE.GENERAL) {
      return new TaskNotification(id, title, body, type, taskType);
    } else if (taskType === TaskNotification.TASK_NOTIFICATION_TYPE.MANAGER
      || taskType === TaskNotification.TASK_NOTIFICATION_TYPE.EXCAVATOR_DRIVER
      || taskType === TaskNotification.TASK_NOTIFICATION_TYPE.TRUCK_DRIVER) {
      return new TaskNotification(id, title, body, type, taskType, data.worker_id);
    } else if (taskType === TaskNotification.TASK_NOTIFICATION_TYPE.CONSTRUCTION_SITE) {
      return new TaskNotification(id, title, body, type, taskType, data.construction_site_id);
    }
  } else if (type === Notification.NOTIFICATION_TYPE.WORKER_DAY_STARTED
    || type === Notification.NOTIFICATION_TYPE.WORKER_DAY_FINISHED) {
    return new WorkerNotification(id, title, body, type, data.worker_id);
  }
  return new Notification(id, title, body, type);
}
