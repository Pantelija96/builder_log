// Serialize token changes so logout/OFF cannot be undone by a pending login.
export default class PushTokenLifecycle {
  #adapter;
  #queue = Promise.resolve();
  #pendingSync;
  #loggingOut;

  constructor(adapter) {
    this.#adapter = adapter;
  }

  #enqueue(operation) {
    const result = this.#queue.then(operation);
    this.#queue = result.catch(() => {});
    return result;
  }

  #current(state) {
    return !this.#loggingOut
      && JSON.stringify(this.#adapter.readState()) === JSON.stringify(state);
  }

  synchronize() {
    const state = this.#adapter.readState();
    if (!state.supported || this.#loggingOut) return Promise.resolve();
    const key = JSON.stringify(state);
    if (this.#pendingSync?.key === key) return this.#pendingSync.result;
    const result = this.#enqueue(() => this.#synchronize(state));
    const pending = { key, result };
    this.#pendingSync = pending;
    const finished = () => { if (this.#pendingSync === pending) this.#pendingSync = undefined; };
    result.then(finished, finished);
    return result;
  }

  async #synchronize(state) {
    const adapter = this.#adapter;
    if (!this.#current(state)) return;
    await adapter.saveEnabled(state.enabled);
    if (!this.#current(state)) return;
    let saved = adapter.readToken();
    if (!state.enabled) {
      // Local unsubscribe still runs when the server is offline. Keep a failed
      // unregister pending in storage, to retry with this account's next session.
      const results = await Promise.allSettled([
        adapter.unsubscribe(),
        saved?.token && state.authorization && saved.workerId === state.workerId
          ? adapter.unregister(saved.token, state.authorization).then(() => adapter.saveToken(null))
          : Promise.resolve(),
      ]);
      const failure = results.find(result => result.status === 'rejected');
      if (failure) throw failure.reason;
      return;
    }
    if (!state.authorization || state.workerId == null) return;

    // A different account must get a fresh subscription. Never reuse a device
    // token still associated with the previous account after an offline exit.
    if (saved?.token && saved.workerId !== state.workerId) {
      await adapter.unsubscribe();
      await adapter.saveToken(null);
      saved = null;
      if (!this.#current(state)) return;
    }

    const token = await adapter.getToken();
    if (!this.#current(state)) {
      const current = adapter.readState();
      if (!current.enabled || current.workerId !== state.workerId || this.#loggingOut) {
        await adapter.unsubscribe();
      }
      return;
    }
    if (!token) throw new Error('Firebase did not return a notification token.');

    if (saved?.token && saved.token !== token) {
      await adapter.unregister(saved.token, state.authorization);
      if (!this.#current(state)) return;
    }
    // Save before sending: a lost response may still mean the server registered
    // the token, so logout must also unregister an unconfirmed registration.
    const binding = { token, workerId: state.workerId, role: state.role, registered: false };
    await adapter.saveToken(binding);
    if (!this.#current(state)) return;
    await adapter.register(token, state.authorization);
    if (this.#current(state)) await adapter.saveToken({ ...binding, registered: true });
  }

  logout() {
    const state = this.#adapter.readState();
    if (!state.supported) return Promise.resolve();
    if (this.#loggingOut) return this.#loggingOut;
    const result = this.#enqueue(async () => {
      const adapter = this.#adapter;
      const saved = adapter.readToken();
      // As on Android, failed unregister leaves the user signed in for retry.
      if (saved?.token && saved.workerId === state.workerId) {
        await adapter.unregister(saved.token, state.authorization);
      }
      await adapter.unsubscribe();
      // No token means no push state to clear. Storage unavailable on a device
      // that never registered for push must not prevent ordinary logout.
      if (saved?.token) await adapter.saveToken(null);
    });
    this.#loggingOut = result;
    const finished = () => { this.#loggingOut = undefined; };
    result.then(finished, finished);
    return result;
  }
}
