import Notification, { parseNotificationInt } from './Notification.js';

export default class NoteNotification extends Notification {
  #noteId = 0;

  constructor(id, title, description, type, noteId) {
    super(id, title, description, type);
    this.#noteId = parseNotificationInt(noteId);
  }

  getNoteId() {
    return this.#noteId;
  }
}
