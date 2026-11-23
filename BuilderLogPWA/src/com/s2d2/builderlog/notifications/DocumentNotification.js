import Notification, { parseNotificationInt } from './Notification.js';

export default class DocumentNotification extends Notification {
  #constructionSiteId = 0;

  constructor(id, title, description, type, constructionSiteId) {
    super(id, title, description, type);
    this.#constructionSiteId = parseNotificationInt(constructionSiteId);
  }

  getConstructionSiteId() {
    return this.#constructionSiteId;
  }
}
