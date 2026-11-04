import Toast, { ToastHost, showToast } from './ui/components/Toast.jsx';
import AdminHomeActivity from './screens/admin/AdminHomeActivity.jsx';
import React, { useEffect, useRef, useState, useSyncExternalStore } from 'react';
import ManagerHomeActivity from './screens/manager/ManagerHomeActivity.jsx';
import SiteSelectionActivity from './screens/manager/SiteSelectionActivity.jsx';
import SettingsActivity, { logoutFromSettings } from './screens/general/SettingsActivity.jsx';
import LoginActivity from './screens/general/LoginActivity.jsx';
import ExcavatorSelectionActivity from './screens/excavator_driver/ExcavatorSelectionActivity.jsx';
import TruckSelectionActivity from './screens/truck_driver/TruckSelectionActivity.jsx';
import TruckDriverActivity from './screens/truck_driver/TruckDriverActivity.jsx';
import ExcavatorDriverActivity from './screens/excavator_driver/ExcavatorDriverActivity.jsx';
import SettingsAccessBar from './ui/components/SettingsAccessBar.jsx';
import DesktopShell from './ui/components/DesktopShell.jsx';
import Session from './network/Session.js';
import WebUtils, { onAuthenticationFailure, onAuthenticatedRequestSuccess } from './network/WebUtils.js';
import useForegroundRefresh from './ui/components/useForegroundRefresh.js';
import SessionGuard from './SessionGuard.jsx';
import getSharedPreferences from './utils/SharedPreferences.js';
import isInstalledIosPwa from './utils/InstalledIosPwa.js';
import Role from './employee/Role.js';
import NetworkLoadingState from './NetworkLoadingState.js';
import StartupSplash from './ui/components/StartupSplash.jsx';
import NetworkLoadingOverlay from './ui/components/NetworkLoadingOverlay.jsx';
import useNotificationRouting from './notifications/useNotificationRouting.js';
import { resetBackLayers } from './ui/components/useBackLayer.js';
import IosNotificationPermissionPrompt from './IosNotificationPermissionPrompt.jsx';
import './ui/theme/DriverSelection.css';
import './ui/theme/DriverWork.css';

const installedIosPwa = isInstalledIosPwa();

export default function RadulovicApplication() {
    const [screen, setScreen] = useState({ name: installedIosPwa ? 'login' : 'loading' });
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [settingsBusy, setSettingsBusy] = useState(false);
    const [notice, setNotice] = useState('');
    const [notificationDestination, setNotificationDestination] = useState(null);
    const [homeVersion, setHomeVersion] = useState(0);
    useNotificationRouting({
        ready: screen.name === 'admin-home' || screen.name === 'manager-home',
        async onTap() {
            setSettingsOpen(false);
            setNotificationDestination(null);
            setHomeVersion(value => value + 1);
            await resetBackLayers();
        },
        onOpen: setNotificationDestination,
    });

    const requestVersion = useRef(0);
    const automaticRecoveryAttempted = useRef(false);
    const sessionCheckPending = useRef(false);

    const requests = useSyncExternalStore(
        NetworkLoadingState.activeRequests.subscribe,
        () => NetworkLoadingState.activeRequests.value,
    );

    useEffect(() => {
        if (!notice) return;

        const timeout = setTimeout(() => setNotice(''), 3000);

        return () => clearTimeout(timeout);
    }, [notice]);

    function closeSettings(message = '') {
        setSettingsOpen(false);
        setNotice(message);
    }

    function logout() {
        setNotificationDestination(null);
        setSettingsOpen(false);
        setNotice('');
        requestVersion.current++;

        Session.clearSession();

        setScreen({ name: 'login' });
    }

    function openSession(form) {
        const version = ++requestVersion.current;
        const currentUser = Session.getCurrentUser();

        if (!currentUser) {
            setScreen({
                name: 'login',
                form,
                allowAutomaticLogin: false,
            });
            return;
        }

        if (installedIosPwa) {
            import('./notifications/IosPushNotifications.js')
                .then(module => module.refreshIosPushNotifications())
                .catch(error => console.error('Notification initialization failed', error));
        }

        const role = currentUser.getRole();

        setScreen({ name: 'loading', role });

        const callback = {
            onSuccess(log) {
                if (version !== requestVersion.current) return;

                if (log == null) {
                    setScreen({
                        name: 'selection',
                        role,
                    });
                } else if (role === Role.SITE_MANAGER) {
                    Session.setDefaultConstructionSite(log);

                    setScreen({
                        name: 'manager-home',
                        role,
                        site: log,
                    });
                } else {
                    const truck = role === Role.TRUCK_DRIVER;

                    setScreen({
                        name: 'assigned',
                        role,
                        assignment: {
                            machine: truck ? log.getTruck() : log.getExcavator(),
                            siteName: truck ? '' : log.getConstructionSiteName(),
                            logId: truck ? log.getTruckLogId() : log.getExcavatorLogId(),
                        },
                    });
                }
            },

            onFailure(exception) {
                if (version !== requestVersion.current) return;

                showToast(
                    role === Role.TRUCK_DRIVER
                        ? 'Neuspešno učitavanje dodeljenog kamiona'
                        : role === Role.SITE_MANAGER
                            ? 'Neuspešno učitavanje odabranog gradilišta'
                            : 'Neuspešno učitavanje dodeljenog bagera',
                );

                setScreen({
                    name: 'login',
                    form,
                    allowAutomaticLogin: false,
                });
            },
        };

        if (role === Role.OPERATOR) {
            Session.getAssignedExcavator(callback);
        } else if (role === Role.TRUCK_DRIVER) {
            Session.getAssignedTruck(callback);
        } else if (role === Role.SITE_MANAGER) {
            Session.getSelectedSelectionSiteFromSever(callback);
        } else if (role === Role.ADMIN) {
            setScreen({
                name: 'admin-home',
                role,
            });
        } else {
            showToast('Nepoznata korisnička uloga');

            setScreen({
                name: 'unsupported',
                role,
            });
        }
    }

    function requireLogin(allowAutomaticLogin = true, error = '') {
        setNotificationDestination(null);
        setSettingsOpen(false);

        requestVersion.current++;

        Session.clearSession();

        getSharedPreferences('com.s2d2.radulovic.LOGIN_PREFERENCES')
            .edit()
            .remove('user_role')
            .apply();

        setScreen({
            name: 'login',
            allowAutomaticLogin,
            error,
        });
    }

    /*
     * Restore saved authentication session after browser refresh.
     * Installed iOS apps instead start at login with Remember me, as in the old PWA.
     *
     * Session.loadToken() restores the stored Bearer token.
     * Session.restoreCurrentUser() validates it through GET /me
     * and recreates Session.currentUser.
     *
     * No backend changes are required.
     */
    useEffect(() => {
        if (installedIosPwa) return;

        const hasSavedToken = Session.loadToken();

        if (!hasSavedToken) {
            setScreen({
                name: 'login',
            });

            return;
        }

        Session.restoreCurrentUser({
            onSuccess() {
                openSession();
            },

            onFailure() {
                Session.clearSession();

                setScreen({
                    name: 'login',
                    allowAutomaticLogin: true,
                });
            },
        });
    }, []);

    useEffect(() => {
        const stopFailure = onAuthenticationFailure(authorization => {
            // A delayed response from an old session must not log out a newer login.
            if (authorization !== Session.getSessionToken()) return;

            const remember = getSharedPreferences('com.s2d2.radulovic.LOGIN_PREFERENCES')
                .getBoolean('remember_me', false);

            const automatic =
                remember &&
                !automaticRecoveryAttempted.current;

            if (automatic) {
                automaticRecoveryAttempted.current = true;
            }

            requireLogin(
                automatic,
                automatic
                    ? ''
                    : 'Sesija je istekla. Prijavite se ponovo.',
            );
        });

        const stopSuccess = onAuthenticatedRequestSuccess(authorization => {
            // Allow later recovery once the server has accepted the new session.
            if (authorization === Session.getSessionToken()) {
                automaticRecoveryAttempted.current = false;
            }
        });

        return () => {
            stopFailure();
            stopSuccess();
        };
    }, []);

    useForegroundRefresh(() => {
        const token = Session.getSessionToken();

        if (
            sessionCheckPending.current ||
            !token ||
            !Session.getCurrentUser()
        ) {
            return;
        }

        sessionCheckPending.current = true;

        // Validate the live session only.
        // Authenticated 401 responses are handled by the shared listener above.
        WebUtils.sendGETRequest(
            'me',
            {},
            {
                Authorization: token,
            },
            {
                onSuccess() {
                    sessionCheckPending.current = false;
                },

                onFailure() {
                    sessionCheckPending.current = false;
                },
            },
        );
    }, screen.name !== 'login' && screen.name !== 'loading');

    useEffect(() => () => {
        requestVersion.current++;
    }, []);

    function selected(assignment) {
        setScreen({
            name: 'assigned',
            role: screen.role,
            assignment: {
                ...assignment,
                siteName: assignment.site.getName(),
            },
        });
    }

    const settingsInShell =
        screen.name === 'admin-home' ||
        screen.name === 'manager-home';

    const settingsPage =
        settingsOpen &&
        (
            <SettingsActivity
                onBack={closeSettings}
                onLogout={logout}
                embedded={settingsInShell}
                onBusyChange={setSettingsBusy}
            />
        );

    return (
        <>
            {screen.name === 'loading'
                ? (
                    <StartupSplash />
                )
                : screen.name === 'login'
                    ? (
                        <LoginActivity
                            onAuthenticated={openSession}
                            initialError={screen.error}
                            initialValues={screen.form}
                            allowAutomaticLogin={screen.allowAutomaticLogin ?? true}
                        />
                    )
                    : (
                        <SessionGuard onSessionMissing={requireLogin}>
                            <main
                                className="role-screen"
                                hidden={settingsOpen && !settingsInShell}
                            >
                                {screen.name !== 'assigned' &&
                                    screen.name !== 'manager-home' &&
                                    screen.name !== 'admin-home' &&
                                    Session.getCurrentUser() && (
                                        <SettingsAccessBar
                                            onOpen={() => setSettingsOpen(true)}
                                        />
                                    )}

                                {screen.name === 'selection' && (
                                    screen.role === Role.SITE_MANAGER
                                        ? (
                                            <SiteSelectionActivity
                                                onSelected={site =>
                                                    setScreen({
                                                        name: 'manager-home',
                                                        role: screen.role,
                                                        site,
                                                    })
                                                }
                                            />
                                        )
                                        : screen.role === Role.TRUCK_DRIVER
                                            ? (
                                                <TruckSelectionActivity
                                                    onSelected={selected}
                                                />
                                            )
                                            : (
                                                <ExcavatorSelectionActivity
                                                    onSelected={selected}
                                                />
                                            )
                                )}

                                {screen.name === 'assigned' && (
                                    screen.role === Role.TRUCK_DRIVER
                                        ? (
                                            <TruckDriverActivity
                                                assignment={screen.assignment}
                                                onSettings={() => setSettingsOpen(true)}
                                            />
                                        )
                                        : (
                                            <ExcavatorDriverActivity
                                                assignment={screen.assignment}
                                                onSettings={() => setSettingsOpen(true)}
                                            />
                                        )
                                )}

                                {screen.name === 'admin-home' && (
                                    <AdminHomeActivity
                                        key={homeVersion}
                                        notificationDestination={notificationDestination}
                                        onOpenNotification={setNotificationDestination}
                                        onSettings={() => setSettingsOpen(true)}
                                        onLogout={() => logoutFromSettings(logout)}
                                        settingsContent={settingsPage}
                                        onCloseSettings={closeSettings}
                                        settingsBusy={settingsBusy}
                                    />
                                )}

                                {screen.name === 'manager-home' && (
                                    <ManagerHomeActivity
                                        key={homeVersion}
                                        notificationDestination={notificationDestination}
                                        onOpenNotification={setNotificationDestination}
                                        active={!settingsOpen}
                                        onSettings={() => setSettingsOpen(true)}
                                        onLogout={() => logoutFromSettings(logout)}
                                        settingsContent={settingsPage}
                                        onCloseSettings={closeSettings}
                                        settingsBusy={settingsBusy}
                                    />
                                )}

                                {screen.name === 'unsupported' && (
                                    <section className="selection-body">
                                        <div className="selection-form">
                                            <p>
                                                Početni ekran za ovu korisničku ulogu još nije dostupan.
                                            </p>

                                            <button
                                                type="button"
                                                className="primary-button"
                                                onClick={() => logoutFromSettings(logout)}
                                            >
                                                Nazad na prijavu
                                            </button>
                                        </div>
                                    </section>
                                )}
                            </main>

                            {!settingsInShell &&
                                settingsPage && (
                                    <DesktopShell
                                        menu={[]}
                                        selected={null}
                                        onHome={() => closeSettings()}
                                        onSettings={() => setSettingsOpen(true)}
                                        onLogout={() => logoutFromSettings(logout)}
                                        userName={
                                            Session.getCurrentUser()?.getFullName() ?? ''
                                        }
                                        roleLabel={
                                            screen.role === Role.TRUCK_DRIVER
                                                ? 'Vozač kamiona'
                                                : screen.role === Role.OPERATOR
                                                    ? 'Bagerista'
                                                    : 'Šef gradilišta'
                                        }
                                        settingsContent={settingsPage}
                                        navigationDisabled={settingsBusy}
                                    />
                                )}
                        </SessionGuard>
                    )}

            <Toast
                message={notice}
                onDismiss={() => setNotice('')}
            />

            <ToastHost />

            <IosNotificationPermissionPrompt active={screen.name !== 'loading' && requests === 0} />

            {requests > 0 &&
                screen.name !== 'loading' && (
                    <NetworkLoadingOverlay />
                )}
        </>
    );
}
