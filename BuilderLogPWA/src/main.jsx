import React from 'react';
import { createRoot } from 'react-dom/client';
import Session from './com/s2d2/builderlog/network/Session.js';
import getSharedPreferences from './com/s2d2/builderlog/utils/SharedPreferences.js';
import isInstalledIosPwa from './com/s2d2/builderlog/utils/InstalledIosPwa.js';
import BuilderLogApplication from './com/s2d2/builderlog/BuilderLogApplication.jsx';
import './com/s2d2/builderlog/ui/theme/Theme.css';
import './com/s2d2/builderlog/ui/theme/TypographyParity.css';
import './com/s2d2/builderlog/ui/theme/Desktop.css';

// A fresh installed iOS app starts at login, just like the old PWA.
// Returning to the running document does not execute this startup code again.
if (isInstalledIosPwa()) {
  Session.clearSession();
  getSharedPreferences('com.s2d2.builderlog.LOGIN_PREFERENCES').edit().remove('user_role').apply();
}

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BuilderLogApplication />
  </React.StrictMode>,
);


if (isInstalledIosPwa()) {
  import('./com/s2d2/builderlog/notifications/IosPushNotifications.js')
    .then(({ startIosPushNotifications }) => startIosPushNotifications())
    .catch(error => console.error('Notification initialization failed', error));
}
