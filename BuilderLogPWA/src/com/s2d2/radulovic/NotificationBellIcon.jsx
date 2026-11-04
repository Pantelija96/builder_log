import React, { useEffect, useState } from 'react';
import NotificationsUtils from './notifications/NotificationsUtils.js';
import { notificationInboxName } from './notifications/NotificationInbox.js';
import { subscribeToNotificationPreferences } from './notifications/NotificationStorageEvents.js';
import getSharedPreferences from './utils/SharedPreferences.js';

// AndroidX Icons.Outlined.Notifications (Apache-2.0).
export function NotificationsIcon({ size = 22 }) {
  return <svg viewBox="0 0 24 24" width={size} height={size} aria-hidden="true">
    <path fill="currentColor" d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2ZM18 16v-5c0-3.07-1.63-5.64-4.5-6.32L13.5 4c0-.83-.67-1.5-1.5-1.5S10.5 3.17 10.5 4v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2ZM16 17H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6Z" />
  </svg>;
}

export default function NotificationBellIcon() {
  const [count, setCount] = useState(0);

  useEffect(() => {
    let active = true;
    let reading = 0;
    async function updateCount() {
      const request = ++reading;
      let next = 0;
      try { next = (await NotificationsUtils.readAllNotifications()).length; }
      catch (error) { console.error('Notifications', 'Could not read notification count', error); }
      if (active && request === reading) setCount(next);
    }
    const role = getSharedPreferences('com.s2d2.radulovic.LOGIN_PREFERENCES').getString('user_role', null);
    const unsubscribe = subscribeToNotificationPreferences(notificationInboxName(role), updateCount);
    const resume = () => { if (document.visibilityState === 'visible') updateCount(); };
    document.addEventListener('visibilitychange', resume);
    window.addEventListener('pageshow', resume);
    window.addEventListener('focus', resume);
    updateCount();
    return () => {
      active = false;
      unsubscribe();
      document.removeEventListener('visibilitychange', resume);
      window.removeEventListener('pageshow', resume);
      window.removeEventListener('focus', resume);
    };
  }, []);

  return <span className="notification-bell-icon" role="img" aria-label={'Obaveštenja: ' + count}
    style={{ width: 32, height: 32, position: 'relative', display: 'grid', placeItems: 'center', color: '#ffc400' }}>
    <NotificationsIcon />
    {count > 0 && <span className="notification-bell-count" aria-hidden="true"
      style={{ position: 'absolute', top: 0, right: 0, minWidth: 18, minHeight: 18,
        padding: '0 3px', borderRadius: 999, background: '#d32f2f', color: 'white',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        fontSize: 10, lineHeight: '12px', fontWeight: 700, whiteSpace: 'nowrap' }}>{count}</span>}
  </span>;
}
