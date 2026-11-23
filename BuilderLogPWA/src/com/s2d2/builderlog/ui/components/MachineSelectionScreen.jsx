import LoadFailureToast from '../../LoadFailureToast.jsx';
import { showToast } from './Toast.jsx';
import React, { useEffect, useRef, useState } from 'react';
import Session, { JSONObject } from '../../network/Session.js';
import WebUtils from '../../network/WebUtils.js';
import SelectionDropdown from './SelectionDropdown.jsx';
import Modal from './Modal.jsx';

export default function MachineSelectionScreen({ kind, onSelected }) {
  const truck = kind === 'truck';
  const [sites, setSites] = useState([]);
  const [machines, setMachines] = useState([]);
  const [site, setSite] = useState(null);
  const [machine, setMachine] = useState(null);
  const [loading, setLoading] = useState(2);
  const [loadFailed, setLoadFailed] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [saving, setSaving] = useState(false);
  const lock = useRef(false);
  const mounted = useRef(false);

  useEffect(() => { mounted.current = true; return () => { mounted.current = false; }; }, []);
  useEffect(() => {
    let active = true;
    setLoading(2);
    setLoadFailed(false);
    setSite(null);
    setMachine(null);
    function loaded(setItems) {
      return {
        onSuccess(items) { if (active) { setItems(items); setLoading(value => value - 1); } },
        onFailure() { if (active) { setLoadFailed(true); setLoading(value => value - 1); } },
      };
    }
    Session.getAllSitesFromServer(loaded(setSites));
    if (truck) Session.getAvailableTrucksFromServer(loaded(setMachines));
    else Session.getAvailableExcavatorsFromServer(loaded(setMachines));
    return () => { active = false; };
  }, [truck]);

  function submit() {
    if (lock.current) return;
    lock.current = true;
    setSaving(true);
    const body = JSON.stringify({ machine_id: machine.getMachineId(), construction_site_id: site.getId() });
    WebUtils.sendPOSTRequest(truck ? 'truck-logs/driver' : 'excavator-logs/operator', body,
      { Authorization: Session.getSessionToken() }, {
        onSuccess(responseBody) {
          if (!mounted.current) return;
          try {
            const logId = truck ? new JSONObject(responseBody).getJSONObject('data').getInt('id') : 0;
            onSelected({ machine, site, logId });
          } catch {
            showToast('Neispravan odgovor servera');
            setSaving(false);
            lock.current = false;
          }
        },
        onFailure() {
          if (!mounted.current) return;
          showToast(truck ? 'Neuspešan odabir kamiona' : 'Neuspešan odabir bagera');
          setSaving(false);
          lock.current = false;
        },
      });
  }

  if (truck && loading > 0) return null;
  const complete = site != null && machine != null && loading === 0 && !saving;
  return (
    <section className="selection-body" aria-label={truck ? 'Izbor kamiona i gradilišta' : 'Izbor mašine i gradilišta'} aria-busy={loading > 0 || saving}>
      {truck && loadFailed && <LoadFailureToast message="Učitavanje izbora kamiona nije uspelo." />}
      <div className="selection-form">
        <SelectionDropdown label="Izaberite gradilište" placeholder="Izaberite gradilište"
          variant="operator" icon="ic_construction_site_crane" options={sites} selected={site} onSelected={setSite}
          optionEnabled={option => option.canSelect()} disabled={saving} />
        <SelectionDropdown label={truck ? 'Izaberite kamion koji danas vozite' : 'Izaberite mašinu'}
          placeholder={truck ? 'Izaberite kamion' : 'Izaberite mašinu'}
          variant={truck ? 'default' : 'operator'} icon={truck ? 'ic_truck_black' : 'ic_excavator_black'} options={machines} selected={machine} onSelected={setMachine}
          disabled={saving || (truck && machines.length === 0)}
          emptyText={truck ? 'Nema dostupnih kamiona.' : undefined} />
        <button type="button" className="primary-button" disabled={!complete} onClick={() => setConfirming(true)}>Potvrdi</button>
        
      </div>
      {confirming && <Modal title="Potvrda izbora" busy={saving} onClose={() => setConfirming(false)}
        actions={<>
          <button className="text-button" type="button" disabled={saving} onClick={() => setConfirming(false)}>Otkaži</button>
          <button className="text-button confirm-button" type="button" disabled={saving} onClick={submit}>{saving ? 'Čuvanje…' : 'Potvrdi'}</button>
        </>}>
        <p><strong>Gradilište: {site.getName()}<br />{truck ? 'Kamion' : 'Mašina'}: {machine.getName()}</strong></p>
        <p>Da li ste sigurni da želite da nastavite?</p>
        <p><strong>{truck
          ? 'Ukoliko nastavite, drugi vozači neće moći da biraju ovaj kamion kroz aplikaciju.'
          : 'Ukoliko nastavite, drugi radnici neće moći da biraju ovu mašinu kroz aplikaciju.'}</strong></p>
        <p>Da li ste sigurni da je odabir dobar?</p>
      </Modal>}
    </section>
  );
}
