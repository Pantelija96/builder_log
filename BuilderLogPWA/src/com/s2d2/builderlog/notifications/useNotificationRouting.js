import { useEffect, useRef, useState } from 'react';
import Session from '../network/Session.js';
import Role from '../employee/Role.js';
import isInstalledIosPwa from '../utils/InstalledIosPwa.js';
import { showToast } from '../ui/components/Toast.jsx';
import NotificationsUtils from './NotificationsUtils.js';
import { notificationFromJSON } from './NotificationPayload.js';
import { openNotification } from '../NotificationRouter.js';
import { NOTIFICATION_TAP, readNotificationTap, removeNotificationTap } from './NotificationNavigation.js';

export default function useNotificationRouting({ ready, onTap, onOpen }) {
  const [tap, setTap] = useState(null);
  const handled = useRef(new Set());
  const generation = useRef(0);
  const callbacks = useRef({ onTap, onOpen });
  callbacks.current = { onTap, onOpen };
  const mounted = useRef(false);
  useEffect(() => {
    mounted.current = true;
    if (!isInstalledIosPwa() || !navigator.serviceWorker) return () => { mounted.current = false; };
    let reading = 0;
    const refresh = async () => {
      const request = ++reading;
      try {
        const pending = await readNotificationTap();
        if (mounted.current && request === reading && pending) setTap(pending);
      } catch (error) { console.error('Notification navigation could not be read', error); }
    };
    const message = event => {
      if (event.data?.type !== NOTIFICATION_TAP) return;
      reading++;
      if (event.data.tap) setTap(event.data.tap);
      else refresh();
    };
    const resume = () => { if (document.visibilityState === 'visible') refresh(); };
    navigator.serviceWorker.addEventListener('message', message);
    window.addEventListener('pageshow', resume);
    document.addEventListener('visibilitychange', resume);
    refresh();
    return () => {
      mounted.current = false;
      reading++;
      navigator.serviceWorker.removeEventListener('message', message);
      window.removeEventListener('pageshow', resume);
      document.removeEventListener('visibilitychange', resume);
    };
  }, []);

  useEffect(() => {
    if (!ready || !tap || handled.current.has(tap.id)) return;
    const user = Session.getCurrentUser();
    if (!user) return;
    handled.current.add(tap.id);
    const version = ++generation.current;
    const authorization = Session.getSessionToken();
    const isCurrent = () => mounted.current && version === generation.current
      && authorization === Session.getSessionToken() && user === Session.getCurrentUser();
    (async () => {
      try {
        // A saved tap belongs to the account that received it, never a later login.
        if (String(tap.workerId) !== String(user.getId()) || tap.role !== user.getRole()) return;
        if (user.getRole() !== Role.ADMIN && user.getRole() !== Role.SITE_MANAGER) return;
        await callbacks.current.onTap();
        if (!isCurrent()) return;
        await openNotification({
          session: Session, notifications: NotificationsUtils, isCurrent, showToast,
          openDestination: destination => callbacks.current.onOpen({ ...destination, key: tap.id }),
        }, notificationFromJSON(tap.notification));
      } catch (error) {
        if (isCurrent()) showToast('Učitavanje podataka nije uspelo.');
        console.error('Notification routing failed', error);
      } finally {
        try { await removeNotificationTap(tap.id); }
        catch (error) { console.error('Notification navigation could not be cleared', error); }
        if (mounted.current) setTap(current => current?.id === tap.id ? null : current);
      }
    })();
  }, [ready, tap]);
}
