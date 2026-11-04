import DesktopShell from '../../ui/components/DesktopShell.jsx';
import AdminConstructionSiteDetailsActivity from './AdminConstructionSiteDetailsActivity.jsx';
import AdminSubcontractorsActivity from './AdminSubcontractorsActivity.jsx';
import Toast from '../../ui/components/Toast.jsx';
import NotificationBellIcon from '../../NotificationBellIcon.jsx';
import NotificationsActivity from '../general/NotificationsActivity.jsx';
import React, {useEffect,useState} from 'react';
import NotificationDestination from '../../notifications/NotificationDestination.jsx';
import Session from '../../network/Session.js';
import {SettingsIcon} from '../../ui/components/SettingsAccessBar.jsx';
import useBackLayer from '../../ui/components/useBackLayer.js';
import {Icon,Notice} from './AdminShared.jsx';
import AdminWorkHistoryActivity from './AdminWorkHistoryActivity.jsx';
import AdminWorkersActivity from './AdminWorkersActivity.jsx';
import AdminDeliveryNotesActivity from './AdminDeliveryNotesActivity.jsx';
import AdminDocumentsActivity from './AdminDocumentsActivity.jsx';
import AdminNotesActivity from './AdminNotesActivity.jsx';
import AdminTasksActivity from './AdminTasksActivity.jsx';
import AdminExpensesActivity from './AdminExpensesActivity.jsx';
import AdminMachinesActivity from './AdminMachinesActivity.jsx';
const menu=[['ic_clock','EVIDENCIJA RADA'],['ic_site_manager_black','RADNICI'],['ic_document_lines_black','OTPREMNICE'],['ic_folder_simple_black','DOKUMENTI'],['ic_wallet_black','TROŠKOVI'],['ic_diary','GRAĐEVINSKI DNEVNIK'],['ic_tasks_black','ZADACI'],['ic_excavator_black','RADNE MAŠINE'],['ic_workers_black_no_bg_original_proportions','PODIZVOĐAČI'],['ic_construction_site_crane','GRADILIŠTA']];
export default function AdminHomeActivity({onSettings, onLogout, settingsContent, onCloseSettings, settingsBusy, notificationDestination, onOpenNotification}) {
  const [section,setSection]=useState(null);const [notice,setNotice]=useState('');
  useEffect(() => { if (notificationDestination) setSection('notification'); }, [notificationDestination]);
  useBackLayer(section!==null,()=>setSection(null));
  function renderContent() {
  if(section==='notification')return <NotificationDestination key={notificationDestination.key} destination={notificationDestination} onBack={()=>setSection(null)}/>;
  if(section==='notifications')return <NotificationsActivity onBack={()=>setSection(null)} onOpenAfterLeaving={onOpenNotification}/>;
  if(section===0)return <AdminWorkHistoryActivity onBack={()=>setSection(null)}/>;
  if(section===1)return <AdminWorkersActivity onBack={()=>setSection(null)}/>;
  if(section===2)return <AdminDeliveryNotesActivity onBack={()=>setSection(null)}/>;
  if(section===3)return <AdminDocumentsActivity onBack={()=>setSection(null)}/>;
  if(section===9)return <AdminConstructionSiteDetailsActivity/>;
  if(section===8)return <AdminSubcontractorsActivity/>;
  if(section===7)return <AdminMachinesActivity/>;
  if(section===4)return <AdminExpensesActivity/>;
  if(section===6)return <AdminTasksActivity/>;
  if(section===5)return <AdminNotesActivity/>;
  return <section className="admin-home"><header className="manager-header"><Icon name="radulovic_logo" className="manager-logo"/><h1>Radulović gradnja</h1><button type="button" className="manager-notifications" onClick={()=>setSection('notifications')}><NotificationBellIcon/></button><button className="settings-icon-button" aria-label="Podešavanja" onClick={onSettings}><SettingsIcon/></button></header><div className="admin-home-body"><div className="admin-role"><Icon name="ic_admin"/><strong>{Session.getCurrentUser().getFullName()}</strong></div><Toast message={notice} onDismiss={() => setNotice('')}/><div className="manager-menu">{menu.map(([icon,title],i)=><button className="manager-menu-tile" key={title} onClick={()=>(i<10)?setSection(i):setNotice('Ovaj admin ekran još nije implementiran.')}><Icon name={icon}/><span>{title}</span></button>)}</div></div></section>;
  }
  return <DesktopShell menu={menu} selected={typeof section === 'number' ? section : null}
    onSelect={index => { if (settingsContent) onCloseSettings(); setSection(index); }}
    onHome={() => { if (settingsContent) onCloseSettings(); setSection(null); }}
    onSettings={onSettings} onLogout={onLogout} settingsContent={settingsContent} navigationDisabled={settingsBusy}
    userName={Session.getCurrentUser().getFullName()} roleLabel="Administrator">{renderContent()}</DesktopShell>;
}
