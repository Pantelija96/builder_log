export default class Supplier {
  #id = 0;
  #name = null;
  #phoneNumber = null;
  #isActive = false;

  constructor(id, name, phoneNumber, isActive) {
    this.#id = id;
    this.#name = name;
    this.#phoneNumber = phoneNumber == null ? "-" : phoneNumber;
    this.#isActive = isActive;
  }

  getId() {
    return this.#id;
  }

  getName() {
    return this.#name;
  }

  getPhoneNumber() {
    return this.#phoneNumber;
  }

  isActive() {
    return this.#isActive;
  }
}
