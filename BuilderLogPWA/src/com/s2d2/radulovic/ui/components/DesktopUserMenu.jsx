import { ASSET_BASE } from '../../utils/AssetPaths.js';
import React, { useEffect, useId, useRef, useState } from 'react';

export default function DesktopUserMenu({ userName, onSettings, onLogout, disabled = false }) {
  const [open, setOpen] = useState(false);
  const container = useRef(null);
  const trigger = useRef(null);
  const menuId = useId();

  useEffect(() => {
    if (!open) return;
    function outside(event) {
      if (!container.current?.contains(event.target)) setOpen(false);
    }
    document.addEventListener('pointerdown', outside);
    return () => document.removeEventListener('pointerdown', outside);
  }, [open]);

  useEffect(() => { if (disabled) setOpen(false); }, [disabled]);

  function closeAndFocus() {
    setOpen(false);
    trigger.current?.focus();
  }
  function keyboard(event) {
    if (event.key === 'Escape') {
      event.preventDefault(); closeAndFocus();
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Home' || event.key === 'End') {
      event.preventDefault();
      setOpen(true);
      requestAnimationFrame(() => {
        const items = [...container.current.querySelectorAll('[role="menuitem"]')];
        const current = items.indexOf(document.activeElement);
        const last = items.length - 1;
        const index = event.key === 'Home' ? 0 : event.key === 'End' ? last
          : event.key === 'ArrowDown' ? (current + 1) % items.length : current < 0 ? last : (current + last) % items.length;
        items[index]?.focus();
      });
    }
  }

  return <div className="desktop-user-menu" ref={container} onKeyDown={keyboard}
    onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false); }}>
    <button type="button" className="desktop-user-trigger" ref={trigger} disabled={disabled}
      aria-haspopup="menu" aria-expanded={open} aria-controls={menuId} onClick={() => setOpen(value => !value)}>
      <span>{userName}</span><img src={ASSET_BASE + "drawable/ic_chevron_down.svg"} alt="" />
    </button>
    {open && <div className="desktop-user-dropdown" id={menuId} role="menu" aria-label="Korisnički meni">
      <button type="button" role="menuitem" onClick={() => { setOpen(false); onSettings(); }}>Podešavanja</button>
      <button type="button" role="menuitem" className="desktop-user-logout" onClick={() => { setOpen(false); onLogout(); }}>Odjavi se</button>
    </div>}
  </div>;
}
