import React, { useEffect } from 'react';
import { AdminSupplierForm } from './AdminAddSupplierActivity.jsx';

export default function AdminEditSupplierActivity({ supplier, onBack }) {
  const id = supplier.getId();
  useEffect(() => {
    if (id <= 0) onBack();
  }, [id, onBack]);
  return id > 0 ? <AdminSupplierForm key={id} supplier={supplier} onBack={onBack}/> : null;
}
