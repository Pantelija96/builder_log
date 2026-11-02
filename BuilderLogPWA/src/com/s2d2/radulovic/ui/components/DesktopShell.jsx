import { ASSET_BASE } from '../../utils/AssetPaths.js';
import React from 'react';
import { SettingsIcon } from './SettingsAccessBar.jsx';
import DesktopUserMenu from './DesktopUserMenu.jsx';

// Presentation only: destinations and callbacks belong to the existing role screen.
export default function DesktopShell({ menu, selected, onSelect, onHome, onSettings, onLogout, userName, roleLabel, children, settingsContent, navigationDisabled = false }) {
  const settingsActive = Boolean(settingsContent);
  return <div className="desktop-shell">
    <aside className="desktop-sidebar" aria-label="Glavna navigacija">
      <div className="desktop-brand"><img src={ASSET_BASE + "drawable/radulovic_logo.svg"} alt="" />
        <div><strong>BuilderLog</strong><span>Radulović gradnja</span></div></div>
      <nav aria-label="Radni prostor">
        <button type="button" disabled={navigationDisabled} aria-current={!settingsActive && selected == null ? 'page' : undefined} onClick={onHome}>
          <img src={ASSET_BASE + "drawable/ic_construction_site.svg"} alt="" /><span>Početna</span></button>
        {menu.map(([icon, title], index) => <button type="button" key={title}
          disabled={navigationDisabled} aria-current={!settingsActive && selected === index ? 'page' : undefined} onClick={() => onSelect(index)}>
          <img src={ASSET_BASE + 'drawable/' + icon + '.svg'} alt="" /><span>{title.toLocaleLowerCase('sr-Latn')}</span>
        </button>)}
      </nav>
      <div className="desktop-account"><span>{roleLabel}</span><strong>{userName}</strong>
        <button type="button" disabled={navigationDisabled} aria-current={settingsActive ? 'page' : undefined} onClick={onSettings}><SettingsIcon /><span>Podešavanja</span></button></div>
    </aside>
    <div className="desktop-workspace">
      <header className="desktop-topbar"><div><span>{roleLabel} / Radni prostor</span>
        <strong>{settingsActive ? 'Podešavanja' : selected == null ? 'Pregled radnog prostora' : menu[selected][1]}</strong></div>
        <DesktopUserMenu userName={userName} onSettings={onSettings} onLogout={onLogout} disabled={navigationDisabled}/></header>
      <div className="desktop-content" hidden={settingsActive}>{children}</div>
      {settingsActive && <div className="desktop-content">{settingsContent}</div>}
    </div>
  </div>;
}
