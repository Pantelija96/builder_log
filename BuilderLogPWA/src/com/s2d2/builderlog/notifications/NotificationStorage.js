import { publishNotificationPreferencesChange } from './NotificationStorageEvents.js';

const DATABASE_NAME = 'radulovic.notifications';
const STORE_NAME = 'preferences';

// Shared by the page and service worker. No window/localStorage dependencies.
function openDatabase() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DATABASE_NAME, 1);
    let blocked = false;
    request.onupgradeneeded = () => request.result.createObjectStore(STORE_NAME);
    request.onerror = () => reject(request.error);
    request.onblocked = () => {
      blocked = true;
      reject(new Error('Notification storage upgrade is blocked by another connection.'));
    };
    request.onsuccess = () => {
      const database = request.result;
      if (blocked) { database.close(); return; }
      database.onversionchange = () => database.close();
      resolve(database);
    };
  });
}

export async function initializeNotificationStorage() {
  const database = await openDatabase();
  database.close();
}

async function preferencesTransaction(name, update, relatedNames = []) {
  const database = await openDatabase();
  return new Promise((resolve, reject) => {
    let transaction;
    let result;
    let failure;
    try {
      transaction = database.transaction(STORE_NAME, update ? 'readwrite' : 'readonly');
      const store = transaction.objectStore(STORE_NAME);
      const names = [name, ...relatedNames];
      const values = new Map();
      let remaining = names.length;
      for (const key of names) {
        const request = store.get(key);
        request.onsuccess = () => {
          values.set(key, request.result ?? {});
          if (--remaining > 0) return;
          try {
            const current = values.get(name);
            result = update ? update(current, ...relatedNames.map(key => values.get(key))) : current;
            if (update && result !== undefined) store.put(result, name);
          } catch (error) {
            failure = error;
            transaction.abort();
          }
        };
      }
      transaction.oncomplete = () => {
        database.close();
        if (update && result !== undefined) {
          try { publishNotificationPreferencesChange(name); }
          catch (error) { console.error('Notifications', 'Could not announce saved notification changes', error); }
        }
        resolve(result);
      };
      transaction.onabort = () => {
        database.close();
        reject(failure ?? transaction.error ?? new Error('Notification storage transaction aborted.'));
      };
    } catch (error) {
      database.close();
      reject(error);
    }
  });
}

export function readNotificationPreferences(name) {
  return preferencesTransaction(name);
}

// The updater must be synchronous. Read and write share one transaction so
// simultaneous page/worker updates cannot overwrite each other's changes.
export function updateNotificationPreferences(name, update, relatedNames = []) {
  return preferencesTransaction(name, update, relatedNames);
}

// Inbox counterpart of the existing SharedPreferences handle. Reads and apply()
// return promises; edits merge with the latest record instead of a stale copy.
export function getStoredNotificationPreferences(name) {
  return {
    async getString(key, fallback = null) { return (await readNotificationPreferences(name))[key] ?? fallback; },
    async getBoolean(key, fallback = false) { return (await readNotificationPreferences(name))[key] ?? fallback; },
    edit() {
      const changes = new Map();
      return {
        putString(key, value) { changes.set(key, value); return this; },
        putBoolean(key, value) { changes.set(key, value); return this; },
        remove(key) { changes.set(key, undefined); return this; },
        apply() {
          return updateNotificationPreferences(name, current => {
            const next = { ...current };
            for (const [key, value] of changes) {
              if (value === undefined) delete next[key];
              else next[key] = value;
            }
            return next;
          });
        },
      };
    },
  };
}
