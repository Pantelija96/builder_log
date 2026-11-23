import React, { useEffect, useRef, useState } from 'react';
import Modal from './ui/components/Modal.jsx';
import { showToast } from './ui/components/Toast.jsx';
import getSharedPreferences from './utils/SharedPreferences.js';
import { getNotificationPermission, requestNotificationPermission, setNotificationsEnabled } from './notifications/NotificationPermission.js';
import './IosNotificationPermissionPrompt.css';

const preferences = () => getSharedPreferences('notification_preferences');

export default function IosNotificationPermissionPrompt({ active = true }) {
  const [open, setOpen] = useState(() => getNotificationPermission() === 'default'
    && !preferences().getBoolean('permission_prompt_shown', false));
  const [busy, setBusy] = useState(false);
  const pending = useRef(false);

  useEffect(() => {
    if (!active || !open) return;
    try {
      preferences().edit().putBoolean('permission_prompt_shown', true).apply();
    } catch { /* Storage availability must not block the native permission request. */ }
  }, [active, open]);

  async function enable() {
    if (pending.current) return;
    pending.current = true;
    setBusy(true);
    try {
      // No asynchronous work before this call: preserve the iOS tap activation.
      const permission = await requestNotificationPermission();
      if (permission === 'granted') {
        try { await setNotificationsEnabled(true); }
        catch { showToast('Podešavanje obaveštenja nije uspelo. Proverite internet konekciju.'); return; }
        showToast('Dozvola za obaveštenja je odobrena.');
      }
      else if (permission === 'denied') showToast('Obaveštenja nisu dozvoljena. Možete ih uključiti u podešavanjima uređaja.');
    } catch {
      showToast('Zahtev za dozvolu za obaveštenja nije uspeo.');
    } finally {
      pending.current = false;
      setBusy(false);
      setOpen(false);
    }
  }

  if (!active || !open) return null;
  return <Modal className="notification-permission-dialog" title="Obaveštenja"
    onClose={() => setOpen(false)} busy={busy}
    actions={<>
      <button type="button" className="text-button" disabled={busy} onClick={() => setOpen(false)}>Ne sada</button>
      <button type="button" className="text-button confirm-button" disabled={busy} onClick={enable}>Uključi obaveštenja</button>
    </>}>
    <p>Želite li da dozvolite obaveštenja za ovu aplikaciju?</p>
  </Modal>;
}
