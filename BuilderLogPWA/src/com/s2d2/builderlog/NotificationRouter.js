import Notification from './notifications/Notification.js';
import NoteNotification from './notifications/NoteNotification.js';
import DocumentNotification from './notifications/DocumentNotification.js';
import TaskNotification from './notifications/TaskNotification.js';
import WorkerNotification from './notifications/WorkerNotification.js';

// Same destinations, filters, removal rules and failure message as NotificationRouter.kt.
// Context supplies the page navigation, Session and NotificationsUtils counterparts.
export async function openNotification(context, notification) {
  const type = notification.getType();
  const types = Notification.NOTIFICATION_TYPE;
  async function openDestination(destination) {
    if (!context.isCurrent()) return false;
    context.openDestination(destination);
    await context.notifications.removeNotification(notification);
    return true;
  }
  try {
    if (notification instanceof NoteNotification && type === types.NOTE_CREATED) {
      const note = await new Promise((resolve, reject) => context.session.getNoteFromServer(
        notification.getNoteId(), { onSuccess: resolve, onFailure: reject },
      ));
      return await openDestination({ name: 'admin-note', note, siteName: note.getConstructionSiteName() ?? '' });
    }
    if (notification instanceof DocumentNotification && type === types.DOCUMENT_CREATED) {
      return await openDestination({ name: 'manager-documents', initialSiteId: notification.getConstructionSiteId() });
    }
    if (notification instanceof TaskNotification && type === types.TASK_COMPLETED) {
      return await openDestination({
        name: 'admin-tasks', initialTaskType: notification.getTaskNotificationType(),
        initialWorkerId: notification.getWorkerId(), initialSiteId: notification.getConstructionSiteId(),
      });
    }
    if (notification instanceof WorkerNotification
      && (type === types.WORKER_DAY_STARTED || type === types.WORKER_DAY_FINISHED)) {
      if (notification.getWorkerId() <= 0) throw new Error('Missing worker ID in notification');
      return await openDestination({ name: 'admin-worker', workerId: notification.getWorkerId() });
    }
    const name = {
      [types.TASK_ASSIGNED]: 'manager-tasks',
      [types.TASK_COMPLETED]: 'admin-tasks',
      [types.DOCUMENT_CREATED]: 'manager-documents',
      [types.NOTE_CREATED]: 'admin-notes',
    }[type];
    if (!name || !context.isCurrent()) return false;
    await context.notifications.removeNotification(notification);
    if (!context.isCurrent()) return false;
    context.openDestination({ name });
    return true;
  } catch (error) {
    if (context.isCurrent()) {
      console.error('NotificationRouter', 'Could not load notification destination', error);
      context.showToast('Učitavanje podataka nije uspelo.');
    }
    return false;
  }
}
