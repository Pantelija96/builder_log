import React from 'react';
import AdminNoteDetailsActivity from '../screens/admin/AdminNoteDetailsActivity.jsx';
import AdminNotesActivity from '../screens/admin/AdminNotesActivity.jsx';
import AdminTasksActivity from '../screens/admin/AdminTasksActivity.jsx';
import AdminWorkerDetailsActivity from '../screens/admin/AdminWorkerDetailsActivity.jsx';
import DocumentsActivity from '../screens/manager/DocumentsActivity.jsx';
import TasksActivity from '../screens/manager/TasksActivity.jsx';

// Reuse the existing screens; notification routing adds no new visible controls.
export default function NotificationDestination({ destination, onBack }) {
  switch (destination.name) {
    case 'admin-note': return <AdminNoteDetailsActivity note={destination.note} siteName={destination.siteName}/>;
    case 'admin-notes': return <AdminNotesActivity/>;
    case 'admin-tasks': return <AdminTasksActivity initialTaskType={destination.initialTaskType} initialWorkerId={destination.initialWorkerId} initialSiteId={destination.initialSiteId}/>;
    case 'admin-worker': return <AdminWorkerDetailsActivity workerId={destination.workerId} onBack={onBack}/>;
    case 'manager-documents': return <DocumentsActivity initialSiteId={destination.initialSiteId} onBack={onBack}/>;
    case 'manager-tasks': return <TasksActivity onBack={onBack}/>;
    default: return null;
  }
}
