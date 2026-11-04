import React, { useRef, useState } from 'react';
import { Title, Icon } from './AdminShared.jsx';
import useBackLayer from '../../ui/components/useBackLayer.js';
import './AdminAddSupplierActivity.css';

export default function AdminAddSupplierActivity({ onBack }) {
  return <AdminSupplierForm onBack={onBack}/>;
}

export function AdminSupplierForm({ supplier = null, onBack }) {
  const editing = supplier != null;
  const [supplierName, setSupplierName] = useState(supplier?.getName() ?? '');
  const [supplierPhone, setSupplierPhone] = useState(() => {
    const phone = supplier?.getPhoneNumber()?.trim();
    return phone === '-' || phone?.toLowerCase() === 'null' ? '' : phone ?? '';
  });
  const [supplierActive, setSupplierActive] = useState(supplier?.isActive() ?? true);
  const [blocked, setBlocked] = useState(false);
  const [touched, setTouched] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const nameInput = useRef(null);
  const focusedOnce = useRef(false);
  useBackLayer(true, onBack);

  const invalidName = !editing && (!supplierName.trim() || supplierName === '-');
  const showNameError = (touched || submitted) && invalidName;

  function save(event) {
    event.preventDefault();
    setSubmitted(true);
    if (invalidName) {
      setTouched(true);
      nameInput.current.focus({ preventScroll: true });
      nameInput.current.scrollIntoView({ block: 'nearest' });
      return;
    }
    if (!supplierName.trim()) {
      setBlocked(true);
      return;
    }
    setBlocked(false);
    setSubmitted(false);
    setTouched(false);
    focusedOnce.current = false;
    // Android also leaves submission unconnected until the endpoint is available.
  }

  return <form className={"admin-screen admin-add-supplier" + (editing ? " admin-edit-supplier" : "")} onSubmit={save} noValidate autoComplete="off">
    <Title title={editing ? "IZMENA DOBAVLJAČA" : "NOVI DOBAVLJAČ"}
      subtitle={editing ? "Izmena podataka za dobavljača" : "Dodavanje novog dobavljača"} icon="ic_supplier"/>
    <div className="add-supplier-scroll">
      <div className="add-supplier-fields">
        {editing ? <div className="add-supplier-field">
          <span>Naziv</span><div className="add-supplier-readonly">{supplierName}</div>
        </div> : <div>
          <label className="add-supplier-field">
            <span>Naziv *</span>
            <input ref={nameInput} name="name" aria-label="Naziv *" aria-required="true"
              aria-invalid={showNameError} aria-describedby={showNameError ? 'supplier-name-error' : undefined}
              placeholder="Unesite naziv" autoCapitalize="words" value={supplierName}
              onChange={event => setSupplierName(event.target.value)}
              onFocus={() => { focusedOnce.current = true; }}
              onBlur={() => { if (focusedOnce.current) setTouched(true); }}/>
          </label>
          {showNameError && <small id="supplier-name-error" className="add-supplier-error">Obavezno polje.</small>}
        </div>}
        <div className="add-supplier-field">
          <span>Status</span>
          <div className="add-supplier-status" role="group" aria-label="Status">
            {[true, false].map(active => <button key={String(active)} type="button"
              aria-pressed={supplierActive === active} onClick={() => setSupplierActive(active)}>
              {active ? 'Aktivan' : 'Neaktivan'}
            </button>)}
          </div>
        </div>
        <div className="add-supplier-field">
          <span>Uloga</span><div className="add-supplier-readonly">Dobavljač</div>
        </div>
        <label className="add-supplier-field">
          <span>Telefon (opciono)</span>
          <input name="phone" aria-label="Telefon (opciono)" type="tel" autoCapitalize="off"
            placeholder="Unesite telefon" value={supplierPhone} onChange={event => setSupplierPhone(event.target.value)}/>
        </label>
      </div>
    </div>
    <div className="add-supplier-footer">
      {blocked && <small className="add-supplier-error">Proverite obavezna polja i izbor gradilišta.</small>}
      <button type="submit" className="add-supplier-save"><Icon name="ic_save"/>{editing ? 'Sačuvaj izmene' : 'Sačuvaj dobavljača'}</button>
    </div>
  </form>;
}
