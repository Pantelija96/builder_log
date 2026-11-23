export default class Expense {
  #id = 0;
  #title = null;
  #description = null;
  #amount = 0;
  #date = null;
  #constructionSiteId = 0;
  #constructionSiteName = null;

  constructor(id, title, description, amount, date, constructionSiteId, constructionSiteName) {
    this.#id = id;
    this.#title = title;
    this.#description = description == null || description === 'null' ? '-' : description;
    this.#amount = Math.trunc(Number(amount));
    this.#date = date != null && date.length >= 10
      ? date.substring(8, 10) + '.' + date.substring(5, 7) + '.' + date.substring(0, 4) + '.'
      : '';
    this.#constructionSiteId = constructionSiteId;
    this.#constructionSiteName = constructionSiteName;
  }

  getId() {
    return this.#id;
  }

  getTitle() {
    return this.#title;
  }

  getDescription() {
    return this.#description;
  }

  getAmount() {
    return this.#amount;
  }

  getDate() {
    return this.#date;
  }

  getConstructionSiteName() {
    return this.#constructionSiteName;
  }
}
