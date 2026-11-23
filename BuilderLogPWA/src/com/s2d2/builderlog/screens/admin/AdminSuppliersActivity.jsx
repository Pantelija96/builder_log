import React, { useEffect, useState } from 'react';
import AdminAddSupplierActivity from './AdminAddSupplierActivity.jsx';
import AdminEditSupplierActivity from './AdminEditSupplierActivity.jsx';
import LoadFailureToast from '../../LoadFailureToast.jsx';
import useForegroundRefresh from '../../ui/components/useForegroundRefresh.js';
import Session from '../../network/Session.js';
import { ASSET_BASE } from '../../utils/AssetPaths.js';
import { showToast } from '../../ui/components/Toast.jsx';
import Modal from '../../ui/components/Modal.jsx';
import { Title, MaterialIcon, Search, Notice, mutate } from './AdminShared.jsx';
import './AdminSuppliersActivity.css';

export default function AdminSuppliersActivity() {
  const [search, setSearch] = useState('');
  const [adding, setAdding] = useState(false);
  const [editing, setEditing] = useState(null);
  const [suppliers, setSuppliers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);
  const reload = () => setRefreshKey(value => value + 1);
  useEffect(() => {
    let disposed = false;
    setLoading(true); setLoadFailed(false);
    Session.getAllSuppliersFromServer({
      onSuccess(items) {
        if (!disposed) { setSuppliers([...items].sort((a, b) => Number(b.isActive()) - Number(a.isActive()))); setLoading(false); }
      },
      onFailure() {
        if (!disposed) { setLoading(false); setLoadFailed(true); }
      },
    });
    return () => { disposed = true; };
  }, [refreshKey]);
  useForegroundRefresh(reload, !adding && !editing);
  const query = search.trim().toLocaleLowerCase();
  const visibleSuppliers = suppliers.filter(supplier => supplier.getName().toLocaleLowerCase().includes(query));

  if (editing) return <AdminEditSupplierActivity supplier={editing} onBack={() => { setEditing(null); reload(); }}/>;
  if (adding) return <AdminAddSupplierActivity onBack={() => { setAdding(false); reload(); }}/>;

  return <section className="admin-screen admin-suppliers">
    <Title title="DOBAVLJAČI" icon="ic_supplier"/>
    <Search value={search} onChange={setSearch} placeholder="Pretraži dobavljače..."/>
    <button type="button" className="admin-supplier-add" onClick={() => setAdding(true)}><MaterialIcon name="add" size={22}/>Dodaj dobavljača</button>
    {loading ? <Notice>Učitavanje dobavljača...</Notice>
      : loadFailed ? <LoadFailureToast message="Učitavanje dobavljača nije uspelo."/>
      : !visibleSuppliers.length ? <Notice>Nema dobavljača za izabrani filter.</Notice>
      : <div className="admin-supplier-list">{visibleSuppliers.map(supplier => <SupplierRow key={supplier.getId()} supplier={supplier} onEdit={() => setEditing(supplier)} onDeleted={() => setSuppliers(items => items.filter(item => item.getId() !== supplier.getId()))}/>)}</div>}
  </section>;
}

function SupplierRow({ supplier, onEdit, onDeleted }) {
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const phoneNumber = supplier.getPhoneNumber()?.trim();
  const phone = phoneNumber && phoneNumber !== '-' && phoneNumber.toLowerCase() !== 'null' ? phoneNumber : null;
  function dial(event) {
    event.preventDefault();
    try {
      window.location.href = 'tel:' + phone.replaceAll(' ', '');
    } catch {
      showToast('Nema aplikacije za pozive.');
    }
  }
  const phoneContent = <><MaterialIcon name="phone" size={14}/><span>{phone ?? '-'}</span></>;
  return <article className="admin-supplier-row">
    {showDeleteDialog && <DeleteSupplierDialog supplier={supplier} deleting={deleting}
      onDismiss={() => { if (!deleting) setShowDeleteDialog(false); }}
      onConfirm={() => {
        setDeleting(true);
        mutate('DELETE', 'suppliers/' + supplier.getId(), { reason: 'admin obrisao' }, {
          onSuccess() {
            setDeleting(false); setShowDeleteDialog(false); onDeleted();
          },
          onFailure() {
            setDeleting(false); showToast('Neuspešno brisanje dobavljača.');
          },
        });
      }}/>}
    <div className="admin-supplier-copy">
      <strong>{supplier.getName()}</strong>
      {phone ? <a className="admin-supplier-phone" href={'tel:' + phone.replaceAll(' ', '')} onClick={dial}>{phoneContent}</a>
        : <div className="admin-supplier-phone">{phoneContent}</div>}
    </div>
    <button type="button" aria-label="Izmeni dobavljača" onClick={onEdit}><MaterialIcon name="edit" size={22}/></button>
    <button type="button" aria-label="Obriši dobavljača" onClick={() => setShowDeleteDialog(true)}><i aria-hidden="true" style={{ maskImage: `url(${ASSET_BASE}drawable/ic_costs_trash.svg)` }}/></button>
  </article>;
}

function DeleteSupplierDialog({ supplier, deleting, onDismiss, onConfirm }) {
  return <Modal className="admin-supplier-delete" title="Obriši dobavljača?" onClose={onDismiss} busy={deleting}
    actions={<>
      <button type="button" disabled={deleting} onClick={onDismiss}>Otkaži</button>
      <button type="button" className="supplier-delete-confirm" disabled={deleting} onClick={onConfirm}>{deleting ? 'Brisanje...' : 'Obriši'}</button>
    </>}>
    <p>Da li ste sigurni da želite da obrišete dobavljača {supplier.getName()}?</p>
  </Modal>;
}
