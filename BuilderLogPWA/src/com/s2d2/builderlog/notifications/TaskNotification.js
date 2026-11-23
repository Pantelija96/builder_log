import Notification, { parseNotificationInt } from './Notification.js';

export default class TaskNotification extends Notification {
  #workerId = 0;
  #constructionSiteId = 0;
  #taskNotificationType = null;

  static TASK_NOTIFICATION_TYPE = Object.freeze({
    GENERAL: 'GENERAL',
    MANAGER: 'MANAGER',
    CONSTRUCTION_SITE: 'CONSTRUCTION_SITE',
    EXCAVATOR_DRIVER: 'EXCAVATOR_DRIVER',
    TRUCK_DRIVER: 'TRUCK_DRIVER',
  });

  constructor(id, title, description, type, taskNotificationType, workerOrSiteId) {
    super(id, title, description, type);
    this.#taskNotificationType = taskNotificationType;
    // Java's five-argument constructor leaves both IDs at their default zero.
    if (arguments.length === 6) {
      if (this.#taskNotificationType === TaskNotification.TASK_NOTIFICATION_TYPE.MANAGER
        || this.#taskNotificationType === TaskNotification.TASK_NOTIFICATION_TYPE.EXCAVATOR_DRIVER
        || this.#taskNotificationType === TaskNotification.TASK_NOTIFICATION_TYPE.TRUCK_DRIVER) {
        this.#workerId = parseNotificationInt(workerOrSiteId);
      } else if (this.#taskNotificationType === TaskNotification.TASK_NOTIFICATION_TYPE.CONSTRUCTION_SITE) {
        this.#constructionSiteId = parseNotificationInt(workerOrSiteId);
      }
    }
  }

  getWorkerId() {
    return this.#workerId;
  }

  getTaskNotificationType() {
    return this.#taskNotificationType;
  }

  getConstructionSiteId() {
    return this.#constructionSiteId;
  }

  static mapTaskNotificationType(type) {
    switch (type.toLowerCase()) {
      case 'general':
        return TaskNotification.TASK_NOTIFICATION_TYPE.GENERAL;
      case 'manager':
        return TaskNotification.TASK_NOTIFICATION_TYPE.MANAGER;
      case 'construction_site':
        return TaskNotification.TASK_NOTIFICATION_TYPE.CONSTRUCTION_SITE;
      case 'excavator_driver':
        return TaskNotification.TASK_NOTIFICATION_TYPE.EXCAVATOR_DRIVER;
      case 'truck_driver':
        return TaskNotification.TASK_NOTIFICATION_TYPE.TRUCK_DRIVER;
    }

    return null;
  }
}
