import Notification, { parseNotificationInt } from './Notification.js';

export default class WorkerNotification extends Notification {
  #workerId = 0;

  constructor(id, title, description, type, workerId) {
    super(id, title, description, type);
    this.#workerId = parseNotificationInt(workerId);
  }

  getWorkerId() {
    return this.#workerId;
  }
}
