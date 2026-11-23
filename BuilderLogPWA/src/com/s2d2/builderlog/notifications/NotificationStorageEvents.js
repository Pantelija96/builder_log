const CHANNEL_NAME = 'radulovic.notification-preferences-changed';
const listeners = new Map();

// IndexedDB has no change listener. Notify this page and other page/worker
// contexts only after a write commits; receivers read the actual saved count.
export function publishNotificationPreferencesChange(name) {
  for (const listener of listeners.get(name) ?? []) listener();
  if (typeof BroadcastChannel === 'undefined') return;
  const channel = new BroadcastChannel(CHANNEL_NAME);
  try { channel.postMessage(name); }
  finally { channel.close(); }
}

export function subscribeToNotificationPreferences(name, listener) {
  if (!listeners.has(name)) listeners.set(name, new Set());
  const local = listeners.get(name);
  local.add(listener);
  let channel;
  try {
    if (typeof BroadcastChannel !== 'undefined') {
      channel = new BroadcastChannel(CHANNEL_NAME);
      channel.onmessage = event => { if (event.data === name) listener(); };
    }
  } catch (error) {
    console.error('Notifications', 'Could not listen for notification changes', error);
  }
  return () => {
    local.delete(listener);
    if (local.size === 0) listeners.delete(name);
    channel?.close();
  };
}
