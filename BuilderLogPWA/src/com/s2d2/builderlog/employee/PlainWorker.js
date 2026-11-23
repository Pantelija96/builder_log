import Worker from './Worker.js';

export default class PlainWorker extends Worker {
  #availableToday = false;

  #attendances = null;

  #hourlyRate = null;

  constructor(id, firstName, lastName, role, availableToday, isActive, username, email, phoneNumber, hourlyRate) {
    super(id, firstName, lastName, role, isActive, username, email, phoneNumber);
    this.#availableToday = availableToday;
    this.#attendances = new Map();
    const rate = String(hourlyRate ?? '').trim();
    this.#hourlyRate = !rate || rate.toLowerCase() === 'null' ? null : Worker.parseHourlyRate(rate);
  }

  isAvailableToday() {
    return this.#availableToday;
  }

  setAvailableToday(availableToday) {
    this.#availableToday = availableToday;
  }

  setAttendance(dailyLogId, workerAttendance) {
    this.#attendances.set(dailyLogId, workerAttendance);
  }

  getAttendance(dailyLogId) {
    return this.#attendances.get(dailyLogId) ?? null;
  }

  removeAttendance(dailyLogId) {
    this.#attendances.delete(dailyLogId);
  }

  getHourlyRate() {
    return this.#hourlyRate ?? 0;
  }

  getHourlyRateOrNull() {
    return this.#hourlyRate;
  }
}
