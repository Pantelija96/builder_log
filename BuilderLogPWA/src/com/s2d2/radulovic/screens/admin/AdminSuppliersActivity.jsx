import React, { useState } from 'react';
import AdminAddSupplierActivity from './AdminAddSupplierActivity.jsx';
import AdminEditSupplierActivity from './AdminEditSupplierActivity.jsx';
import LoadFailureToast from '../../LoadFailureToast.jsx';
import Session from '../../network/Session.js';
import { ASSET_BASE } from '../../utils/AssetPaths.js';
import { showToast } from '../../ui/components/Toast.jsx';
import Modal from '../../ui/components/Modal.jsx';
import { Title, MaterialIcon, Search, Notice, useLoad } from './AdminShared.jsx';
import './AdminSuppliersActivity.css';

export default function AdminSuppliersActivity() {
  const [search, setSearch] = useState('');
  const [adding, setAdding] = useState(false);
  const [editing, setEditing] = useState(null);
  const suppliers = useLoad(callback => Session.getAllSuppliersFromServer(callback), [], []);
  const query = search.trim().toLocaleLowerCase();
  const visibleSuppliers = suppliers.data.filter(supplier => supplier.getName().toLocaleLowerCase().includes(query));

  if (editing) return <AdminEditSupplierActivity supplier={editing} onBack={() => setEditing(null)}/>;
  if (adding) return <AdminAddSupplierActivity onBack={() => setAdding(false)}/>;

  return <section className="admin-screen admin-suppliers">
    <Title title="DOBAVLJAČI" icon="ic_supplier"/>
    <Search value={search} onChange={setSearch} placeholder="Pretraži dobavljače..."/>
    <button type="button" className="admin-supplier-add" onClick={() => setAdding(true)}><MaterialIcon name="add" size={22}/>Dodaj dobavljača</button>
    {suppliers.loading ? <Notice>Učitavanje dobavljača...</Notice>
      : suppliers.error ? <LoadFailureToast message="Učitavanje dobavljača nije uspelo."/>
      : !visibleSuppliers.length ? <Notice>Nema dobavljača za izabrani filter.</Notice>
      : <div className="admin-supplier-list">{visibleSuppliers.map(supplier => <SupplierRow key={supplier.getId()} supplier={supplier} onEdit={() => setEditing(supplier)}/>)}</div>}
  </section>;
}

function SupplierRow({ supplier, onEdit }) {
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
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
    {showDeleteDialog && <DeleteSupplierDialog supplier={supplier}
      onDismiss={() => setShowDeleteDialog(false)}
      onConfirm={() => {
        // Android keeps the supplier until the delete endpoint is connected.
        setShowDeleteDialog(false);
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

function DeleteSupplierDialog({ supplier, onDismiss, onConfirm }) {
  return <Modal className="admin-supplier-delete" title="Obriši dobavljača?" onClose={onDismiss}
    actions={<>
      <button type="button" onClick={onDismiss}>Otkaži</button>
      <button type="button" className="supplier-delete-confirm" onClick={onConfirm}>Obriši</button>
    </>}>
    <p>Da li ste sigurni da želite da obrišete dobavljača {supplier.getName()}?</p>
  </Modal>;
}
