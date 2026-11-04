import React, { useEffect, useMemo, useRef, useState } from 'react';
import { NotificationsIcon } from '../../NotificationBellIcon.jsx';
import Session from '../../network/Session.js';
import Role from '../../employee/Role.js';
import Notification from '../../notifications/Notification.js';
import NotificationsUtils from '../../notifications/NotificationsUtils.js';
import { notificationInboxName } from '../../notifications/NotificationInbox.js';
import { subscribeToNotificationPreferences } from '../../notifications/NotificationStorageEvents.js';
import getSharedPreferences from '../../utils/SharedPreferences.js';
import LoadFailureToast from '../../LoadFailureToast.jsx';
import Modal from '../../ui/components/Modal.jsx';
import { openNotification } from '../../NotificationRouter.js';
import NotificationDestination from '../../notifications/NotificationDestination.jsx';
import useBackLayer from '../../ui/components/useBackLayer.js';
import { showToast } from '../../ui/components/Toast.jsx';
import './NotificationsActivity.css';

export default function NotificationsActivity({ onOpenAfterLeaving } = {}) {
  const [notifications, setNotifications] = useState(null);
  const [loadFailed, setLoadFailed] = useState(false);
  const role = Session.getCurrentUser()?.getRole();
  const [destination, setDestination] = useState(null);
  const [showClearConfirmation, setShowClearConfirmation] = useState(false);
  const [clearing, setClearing] = useState(false);
  const clearPending = useRef(false);
  const reload = useRef(() => {});
  const mounted = useRef(false);
  const opening = useRef(false);
  useBackLayer(destination !== null, () => setDestination(null));

  useEffect(() => {
    mounted.current = true;
    return () => { mounted.current = false; };
  }, []);

  useEffect(() => {
    if (destination) return;
    let active = true;
    let reading = 0;
    function reloadNotifications() {
      const request = ++reading;
      NotificationsUtils.readAllNotifications().then(saved => {
        if (active && request === reading) { setNotifications(saved.reverse()); setLoadFailed(false); }
      }).catch(error => {
        console.error('Notifications', 'Could not read saved notifications', error);
        if (active && request === reading) { setNotifications([]); setLoadFailed(true); }
      });
    }
    reload.current = reloadNotifications;
    const savedRole = getSharedPreferences('com.s2d2.radulovic.LOGIN_PREFERENCES').getString('user_role', null);
    const unsubscribe = subscribeToNotificationPreferences(notificationInboxName(savedRole), reloadNotifications);
    const resume = () => { if (document.visibilityState === 'visible') reloadNotifications(); };
    document.addEventListener('visibilitychange', resume);
    window.addEventListener('pageshow', resume);
    window.addEventListener('focus', resume);
    reloadNotifications();
    return () => {
      active = false;
      reload.current = () => {};
      unsubscribe();
      document.removeEventListener('visibilitychange', resume);
      window.removeEventListener('pageshow', resume);
      window.removeEventListener('focus', resume);
    };
  }, [destination]);

  async function removeNotifications(selected) {
    try {
      await NotificationsUtils.removeNotifications(selected);
      reload.current();
      return true;
    } catch (error) {
      console.error('Notifications', 'Could not remove notifications', error);
      if (mounted.current) showToast('Brisanje obaveštenja nije uspelo.');
      return false;
    }
  }

  async function clearVisibleNotifications() {
    if (clearPending.current) return;
    clearPending.current = true;
    setClearing(true);
    const removed = await removeNotifications(groups.flatMap(group => group.entries));
    clearPending.current = false;
    if (mounted.current) {
      setClearing(false);
      if (removed) setShowClearConfirmation(false);
    }
  }

  async function open(notification) {
    if (opening.current) return;
    opening.current = true;
    const user = Session.getCurrentUser();
    const authorization = Session.getSessionToken();
    const isCurrent = () => (mounted.current || !!onOpenAfterLeaving) && user === Session.getCurrentUser()
      && authorization === Session.getSessionToken();
    try {
      const opened = await openNotification({
        session: Session, notifications: NotificationsUtils, isCurrent, showToast,
        openDestination: destination => {
          if (mounted.current) setDestination(destination);
          // Like Android's application context, the app can open a result after Back.
          else onOpenAfterLeaving({ ...destination, key: 'inbox-' + notification.getId() });
        },
      }, notification);
      if (opened && mounted.current && isCurrent()) {
        setNotifications(saved => saved?.filter(item => item.getId() !== notification.getId()));
      }
    } finally {
      opening.current = false;
    }
  }

  const groups = useMemo(() => {
    const types = Notification.NOTIFICATION_TYPE;
    const labels = role === Role.SITE_MANAGER ? [
      [types.DOCUMENT_CREATED, 'Dokumenta'],
      [types.TASK_ASSIGNED, 'Zadaci'],
    ] : role === Role.ADMIN ? [
      [types.TASK_COMPLETED, 'Zadaci'],
      [types.NOTE_CREATED, 'Građevinski dnevnik'],
      [types.WORKER_DAY_STARTED, 'Početak rada'],
      [types.WORKER_DAY_FINISHED, 'Završetak rada'],
    ] : [];

    return labels.map(([type, label]) => {
      const seen = new Set();
      const entries = (notifications ?? []).filter(notification => {
        if (notification.getType() !== type || seen.has(notification.getId())) return false;
        seen.add(notification.getId());
        return true;
      });
      return { type, label, entries };
    }).filter(group => group.entries.length > 0);
  }, [notifications, role]);

  if (destination) return <NotificationDestination destination={destination} onBack={() => setDestination(null)} />;

  return <section className="notifications-screen" aria-label="Obaveštenja" aria-busy={notifications === null}>
    {loadFailed && <LoadFailureToast message="Učitavanje obaveštenja nije uspelo." />}
    <div className="notifications-content">
      <div className="notifications-title">
        <span><NotificationsIcon size={32} /></span>
        <h2>OBAVEŠTENJA</h2>
      </div>
      {!loadFailed && notifications !== null && (groups.length === 0
        ? <p className="notifications-empty" role="status">Nema nepročitanih obaveštenja.</p>
        : <><div className="notifications-clear-bar">
          <button type="button" onClick={() => setShowClearConfirmation(true)}>
            <DeleteOutlineIcon size={18} />Obriši sve
          </button>
        </div>{groups.map(({ type, label, entries }) => <React.Fragment key={type}>
          <div className="notifications-group-heading">
            <h3>{label}</h3><span aria-hidden="true" />
          </div>
          {entries.map(notification => <SwipeNotificationRow key={type + ':' + notification.getId()}
            notification={notification} onOpen={() => open(notification)}
            onRemove={() => removeNotifications([notification])} />)}
        </React.Fragment>)}</>)}

    </div>
    {showClearConfirmation && <Modal className="notifications-clear-dialog"
      title={<><DeleteOutlineIcon />Obriši obaveštenja</>}
      onClose={() => setShowClearConfirmation(false)} busy={clearing}
      actions={<>
        <button type="button" disabled={clearing} onClick={() => setShowClearConfirmation(false)}>Otkaži</button>
        <button type="button" className="notifications-clear-confirm" disabled={clearing} onClick={clearVisibleNotifications}>Obriši sve</button>
      </>}>
      <p>Da li želite da obrišete sva prikazana obaveštenja?</p>
    </Modal>}
  </section>;
}

/** AndroidX Icons.Outlined.DeleteOutline (Apache-2.0). */
function DeleteOutlineIcon({ size = 24 }) {
  return <svg viewBox="0 0 24 24" width={size} height={size} aria-hidden="true">
    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12ZM8 9h8v10H8V9Zm7.5-5-1-1h-5l-1 1H5v2h14V4h-3.5Z" />
  </svg>;
}

function SwipeNotificationRow({ notification, onOpen, onRemove }) {
  const [offset, setOffset] = useState(0);
  const [dragging, setDragging] = useState(false);
  const row = useRef(null);
  const gesture = useRef(null);
  const suppressClick = useRef(false);
  const removing = useRef(false);
  const timer = useRef(null);
  const alive = useRef(false);
  const currentOnRemove = useRef(onRemove);
  currentOnRemove.current = onRemove;

  useEffect(() => {
    alive.current = true;
    return () => { alive.current = false; clearTimeout(timer.current); };
  }, []);

  function dismiss(direction) {
    if (removing.current) return;
    removing.current = true;
    suppressClick.current = true;
    setDragging(false);
    setOffset(direction * row.current.getBoundingClientRect().width);
    timer.current = setTimeout(async () => {
      const removed = await currentOnRemove.current();
      if (!removed && alive.current) { removing.current = false; setOffset(0); }
    }, 180);
  }

  function start(event) {
    if (!event.isPrimary || event.button !== 0 || removing.current) return;
    suppressClick.current = false;
    // Leave the iPhone's left-edge Back gesture to the existing navigation handler.
    if (event.pointerType === 'touch' && event.clientX <= 24) return;
    gesture.current = { id: event.pointerId, x: event.clientX, y: event.clientY,
      horizontal: false, samples: [{ x: event.clientX, time: event.timeStamp }] };
  }

  function move(event) {
    const drag = gesture.current;
    if (!drag || drag.id !== event.pointerId) return;
    const dx = event.clientX - drag.x;
    const dy = event.clientY - drag.y;
    if (!drag.horizontal) {
      if (Math.max(Math.abs(dx), Math.abs(dy)) < 8) return;
      suppressClick.current = true;
      if (Math.abs(dy) >= Math.abs(dx)) { gesture.current = null; return; }
      drag.horizontal = true;
      event.currentTarget.setPointerCapture(event.pointerId);
      setDragging(true);
    }
    drag.samples.push({ x: event.clientX, time: event.timeStamp });
    while (drag.samples.length > 2 && event.timeStamp - drag.samples[0].time > 100) drag.samples.shift();
    const width = row.current.getBoundingClientRect().width;
    setOffset(Math.max(-width, Math.min(width, dx)));
  }

  function finish(event, cancelled = false) {
    const drag = gesture.current;
    if (!drag || drag.id !== event.pointerId) return;
    gesture.current = null;
    setDragging(false);
    if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
    if (cancelled || !drag.horizontal) { setOffset(0); return; }
    const dx = event.clientX - drag.x;
    const sample = drag.samples.find(sample => event.timeStamp - sample.time <= 100);
    const velocity = sample && event.timeStamp > sample.time
      ? (event.clientX - sample.x) * 1000 / (event.timeStamp - sample.time) : 0;
    // Android SwipeToDismissBox defaults: 56dp distance or a 125dp/s fling.
    const shouldDismiss = Math.abs(velocity) >= 125
      ? Math.sign(velocity) === Math.sign(dx) : Math.abs(dx) > 56;
    if (shouldDismiss && dx !== 0) dismiss(Math.sign(dx));
    else setOffset(0);
  }

  return <div ref={row} className="notification-swipe"
    onPointerDown={start} onPointerMove={move} onPointerUp={finish}
    onPointerCancel={event => finish(event, true)}
    onLostPointerCapture={event => { if (event.target === event.currentTarget) finish(event, true); }}>
    <div className="notification-swipe-background" aria-hidden="true"
      style={{ justifyContent: offset > 0 ? 'flex-start' : 'flex-end' }}>
      <DeleteOutlineIcon />
    </div>
    <article className="notification-row" role="button" tabIndex={0} aria-keyshortcuts="Delete"
      style={{ transform: `translateX(${offset}px)`, transition: dragging ? 'none' : undefined }}
      onClick={() => { if (!suppressClick.current && !removing.current) onOpen(); }}
      onKeyDown={event => {
        if (event.key === 'Delete') { event.preventDefault(); dismiss(-1); }
        else if ((event.key === 'Enter' || event.key === ' ') && !removing.current) {
          event.preventDefault(); onOpen();
        }
      }}>
      <h4>{notification.getTitle() ?? ''}</h4>
      {notification.getDescription()?.trim() && <p>{notification.getDescription()}</p>}
    </article>
  </div>;
}
