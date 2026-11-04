import React, { useEffect, useRef, useState } from 'react';
import { NotificationsIcon } from '../NotificationBellIcon.jsx';
import { showToast } from '../ui/components/Toast.jsx';
import { areNotificationsEnabled, getNotificationPermission, requestNotificationPermission, setNotificationsEnabled } from './NotificationPermission.js';
import './IosNotificationSettings.css';

const readState = () => ({ permission: getNotificationPermission(), enabled: areNotificationsEnabled() });

export default function IosNotificationSettings({ disabled = false }) {
  const [state, setState] = useState(readState);
  const [busy, setBusy] = useState(false);
  const pending = useRef(false);

  useEffect(() => {
    // The user may change permission in iOS Settings and return to this screen.
    const refresh = () => { if (document.visibilityState === 'visible') setState(readState()); };
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('focus', refresh);
    window.addEventListener('pageshow', refresh);
    return () => {
      document.removeEventListener('visibilitychange', refresh);
      window.removeEventListener('focus', refresh);
      window.removeEventListener('pageshow', refresh);
    };
  }, []);

  async function toggle() {
    if (pending.current) return;
    pending.current = true;
    setBusy(true);
    try {
      const enabled = !areNotificationsEnabled();
      if (enabled) {
        // Keep this call in the tap handler, before any asynchronous work.
        const permission = await requestNotificationPermission();
        if (permission === 'denied') {
          showToast('U podešavanjima uređaja otvorite Obaveštenja, izaberite ovu aplikaciju i uključite „Dozvoli obaveštenja”.', 6000);
          return;
        }
        if (permission !== 'granted') return;
      }
      try { await setNotificationsEnabled(enabled); }
      catch { showToast('Podešavanje obaveštenja nije uspelo. Proverite internet konekciju.'); }
    } catch {
      showToast('Zahtev za dozvolu za obaveštenja nije uspeo.');
    } finally {
      pending.current = false;
      setBusy(false);
      setState(readState());
    }
  }

  const status = state.permission === 'unsupported' ? 'Obaveštenja nisu podržana na ovom uređaju.'
    : state.permission === 'denied' ? 'Dozvolite u podešavanjima uređaja.'
      : state.enabled ? 'Uključena' : 'Isključena';
  return <button type="button" role="switch" className="ios-notification-settings"
    aria-checked={state.enabled} aria-busy={busy}
    aria-labelledby="settings-notifications-label" aria-describedby="settings-notifications-status"
    disabled={disabled || busy || state.permission === 'unsupported'} onClick={toggle}>
    <NotificationsIcon size={24} />
    <span className="ios-notification-settings-text">
      <span id="settings-notifications-label">Obaveštenja</span>
      <span id="settings-notifications-status" aria-live="polite">{status}</span>
    </span>
    <span className="ios-notification-switch" aria-hidden="true"><span /></span>
  </button>;
}
