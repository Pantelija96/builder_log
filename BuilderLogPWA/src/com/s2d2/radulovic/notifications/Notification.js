export default class Notification {
  static NOTIFICATION_TYPE = Object.freeze({
    DOCUMENT_CREATED: 'DOCUMENT_CREATED',
    TASK_ASSIGNED: 'TASK_ASSIGNED',
    TASK_COMPLETED: 'TASK_COMPLETED',
    WORKER_DAY_STARTED: 'WORKER_DAY_STARTED',
    WORKER_DAY_FINISHED: 'WORKER_DAY_FINISHED',
    NOTE_CREATED: 'NOTE_CREATED',
    TRUCK_ASSIGNED: 'TRUCK_ASSIGNED',
    EXCAVATOR_ASSIGNED: 'EXCAVATOR_ASSIGNED',
  });

  #id = 0;
  #title = null;
  #description = null;
  #type = null;

  constructor(id, title, description, type) {
    this.#id = id;
    this.#title = title;
    this.#description = description;
    this.#type = type;
  }

  getTitle() {
    return this.#title;
  }

  getDescription() {
    return this.#description;
  }

  getType() {
    return this.#type;
  }

  getId() {
    return this.#id;
  }
}

// Integer.parseInt(String) semantics for the notification subclasses.
// Unlike JS parseInt, Java rejects whitespace, decimals, suffixes and overflow.
export function parseNotificationInt(value) {
  if (typeof value !== 'string') throw new TypeError('Invalid integer: ' + value);
  // Java also accepts BMP decimal digits, such as Arabic and full-width digits.
  const text = value.replace(/[^\x00-\x7f]/g, character => {
    if (!/\p{Decimal_Number}/u.test(character)) return character;
    const code = character.charCodeAt(0);
    let zero = code;
    while (/\p{Decimal_Number}/u.test(String.fromCharCode(zero - 1))) zero--;
    return String(code - zero);
  });
  if (!/^[+-]?[0-9]+$/.test(text)) throw new TypeError('Invalid integer: ' + value);
  const number = Number(text);
  if (number < -2147483648 || number > 2147483647) throw new TypeError('Invalid integer: ' + value);
  return number || 0;
}
