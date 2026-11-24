You are modifying the BuilderLog PWA client to support the completed multi-manager backend API migration.

Use the attached `api-client-changes.json` as the authoritative client API change specification.

Inspect the existing PWA code before making changes.

Do not guess store names, component names, API wrappers, or state architecture.


## Implementation tasks


1. Inspect the API client, types, stores, composables/hooks, router/page loading, active-site and DailyLog state, caches, localStorage/sessionStorage, machine and attendance state.
2. Wire site selection to POST /api/v1/construction-sites/{id}/select and persist data.id on 200 and 201.
3. Remove manager/date DailyLog lookup and singular fields; parse site_managers[] and joined_at. Preserve attendance, expense and machine attribution.
4. Support multiple sites per manager/day and key shared state and cached requests by DailyLog.id.
5. Render records from other members in shared attendance, expenses, notes, delivery notes, subcontractor logs, attachments and machine views; keep documented attribution-only history separate.
6. Invalidate/refetch the relevant shared lists after mutations using DailyLog ID and applicable company/date filters.
7. Update nullable machine attribution, operator/driver self-assignment and manager takeover. Use the returned log ID and wait for authoritative attendance.
8. Use company availability for machines and the interval-specific worker availability endpoint for operators/drivers; do not use visibility as availability.
9. Make locked state read-only for clients and handle conflicts from stale data after refreshing.
10. Handle endpoint-specific 403, 404, 409, 422 and known business 500 cases without changing unrelated UI.
11. Migrate stale cache/local storage keys if needed and update types/interfaces/serializers.
12. Add or update client parsing, state, cache and UI tests for the shared workflows; run the normal checks and build.


## Required API invariants

- Do not use `DailyLog.site_manager_id` or `DailyLog.site_manager` as output fields. Do not synthesize an owner from `site_managers[0]`. Use `DailyLog.site_managers[]` for participation display only, and `DailyLog.id` as the canonical shared log identifier.
- Manager A and Manager B on the same DailyLog see the same WorkerAttendance, Expenses, Notes, DeliveryNotes, SubcontractorLogs, Attachments, MachineAssignments, ExcavatorLogs and TruckLogs. Do not duplicate them or filter out records created by the other manager, except for routes with documented attribution semantics such as a manager’s expense history.
- Keep `site_manager_id` on children where the API exposes it. The `site_manager_id` query parameter on DailyLog filters membership. On MachineAssignment it filters attribution. The existing legacy DailyLog create body still validates its legacy fields. Prefer the select operation for current-day work.
- Pending machine self-assignment can have null stored `MachineAssignment.site_manager_id`. On wire the resource exposes nullable/conditional `site_manager`; it does not emit a scalar `site_manager_id`. Takeover attributes the actual manager; upsert by the returned log ID. Self-assignment alone creates no salary attendance. A manager-authoritative start drives salary synchronization.
- If `DailyLog.is_locked` is true, allow authorized viewing and disable create, update, delete, takeover and finish where applicable. Keep the backend authoritative. An administrator can still use the dedicated unlock operation if permitted. Selection can return an already locked log.
- Handle both 200 and 201 from select. Handle 403 permission failure, 404 for foreign/missing/invisible or wrong-parent records, 409 for known conflicts, and 422 for validation or required attribution. Attendance overlaps return 409. Existing lock and machine overlap cases can use 500; after a generic 500, refresh authoritative state before deciding whether a business condition caused it.
- Keep unrelated behavior, current architecture, and API fields as implemented. Do not change the backend, invent compatibility fields, or perform unrelated refactors. Do not invent financial attribution from participants. The client must compile and the existing tests must continue to pass.



## Current and planned worker selection

- Call GET /api/v1/workers/available?role=worker for available now. Use at for an explicit point, or started_at and optional finished_at for a planned interval. Present server candidates directly; never subtract everyone with attendance that day. Availability reads are advisory. POST and PATCH validate the entered interval across all company sites and reject overlapping work even when both periods are in the past.
- The daily attendance limit has been removed. Multiple non-overlapping periods on the same or different sites are valid for the same company and worker. An open attendance reserves time from its start until the local midnight following attendance.date. Salary uses exactly eight hours per open attendance. A closed attendance uses actual hours. An 08:00–12:00 attendance followed by an open attendance at 12:00 totals 12 salary hours.
- Distinguish the current instant, an explicit point, and an interval in availability cache keys; refresh current-mode results as time advances. Use the selected period as part of the interval cache key and use attendance IDs for editing/deleting. Refresh candidates after any relevant mutation. A 409 from create or PATCH means the interval must be revised or refreshed. PATCH can explicitly clear finished_at with null; omission preserves it.
- Machine manager periods use [start, finish); absent manager finish releases at local midnight at the end of the assignment date. Without manager start, the machine is reserved for its business date. Operator/driver finish does not release it.

## Worker display order

The default changed from 1 to 0. Show 0 as the normal group and use it to clear manual priority. A value of 1 is the highest positive priority.

- Worker.display_order is a non-null integer, default 0. On create and update it is optional; send a JSON integer from 0 to 2147483647. Omitting it on update keeps the current value. Sending 0 removes manual priority. Duplicate values are allowed.
- The backend lists all positive manual priorities first, in ascending order, and then the normal group (0). Within each equal value it sorts by first_name, last_name, and id ascending. Preserve the backend order unless the user requests another supported sort. Explicit sort/direction on GET /workers takes precedence.
- Read `display_order` wherever the API uses WorkerResource. The ordering applies to the worker directory, available worker pickers and construction-site manager lists. Preserve attendance, salary and report ordering.
- Parse Worker.display_order and preserve the server order in worker pickers. Send display_order only when the administrator sets it.

## Endpoint changes


The catalog includes the affected backend operations available to this client. Locate actual consumers first; update integrations the client uses, including any shared decoder or cache they use. Do not build unrelated screens for unused operations. JSON response examples show selected fields. HTTP codes and context come from the matching JSON entry. If evidence conflicts, report the specific route and observed field or status.

### POST /api/v1/construction-sites/{constructionSite}/select

PREVIOUS REQUEST:

ENDPOINT DID NOT EXIST

CURRENT REQUEST:

```json
{}
```

PREVIOUS RESPONSE:

ENDPOINT DID NOT EXIST

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": false,
    "site_managers": [
      {
        "id": 2,
        "first_name": "Ana",
        "last_name": "Ivić",
        "full_name": "Ana Ivić",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "first_name": "Milan",
        "last_name": "Petrović",
        "full_name": "Milan Petrović",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ],
    "company_id": 1,
    "locked_at": null,
    "locked_by_id": null,
    "created_at": "2026-11-22T07:00:00.000000Z",
    "updated_at": "2026-11-22T07:00:00.000000Z",
    "construction_site": {
      "id": 4,
      "company_id": 1,
      "name": "North site",
      "can_select": null
    }
  }
}
```

PWA CHANGE:

- Call from the site selector and invalidate queries by returned data.id.

Current HTTP outcomes — 200, 201, 401, 403, 404, 409.

### GET /api/v1/daily-logs

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 55,
      "construction_site_id": 4,
      "date": "2026-11-21T23:00:00.000000Z",
      "is_locked": false,
      "site_manager_id": 2,
      "site_manager": {
        "id": 2
      }
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 55,
      "construction_site_id": 4,
      "date": "2026-11-21T23:00:00.000000Z",
      "is_locked": false,
      "site_managers": [
        {
          "id": 2,
          "first_name": "Ana",
          "last_name": "Ivić",
          "full_name": "Ana Ivić",
          "username": null,
          "email": null,
          "phone": null,
          "role": "site_manager",
          "hourly_rate": null,
          "is_active": true,
          "created_at": null,
          "updated_at": null,
          "is_available": true,
          "joined_at": "2026-11-22T07:00:00.000000Z"
        },
        {
          "id": 7,
          "first_name": "Milan",
          "last_name": "Petrović",
          "full_name": "Milan Petrović",
          "username": null,
          "email": null,
          "phone": null,
          "role": "site_manager",
          "hourly_rate": null,
          "is_active": true,
          "created_at": null,
          "updated_at": null,
          "is_available": true,
          "joined_at": "2026-11-22T07:30:00.000000Z"
        }
      ]
    }
  ]
}
```

PWA CHANGE:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/daily-logs/{dailyLog}

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": false,
    "site_manager_id": 2,
    "site_manager": {
      "id": 2
    }
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": false,
    "site_managers": [
      {
        "id": 2,
        "first_name": "Ana",
        "last_name": "Ivić",
        "full_name": "Ana Ivić",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "first_name": "Milan",
        "last_name": "Petrović",
        "full_name": "Milan Petrović",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ]
  }
}
```

PWA CHANGE:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

Current HTTP outcomes — 200, 401, 404.

### POST /api/v1/daily-logs

PREVIOUS REQUEST:

```json
{
  "company_id": 1,
  "construction_site_id": 4,
  "site_manager_id": 2,
  "date": "2026-11-22"
}
```

CURRENT REQUEST:

```json
{
  "company_id": 1,
  "construction_site_id": 4,
  "site_manager_id": 2,
  "date": "2026-11-22"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Daily log created successfully.",
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": false,
    "site_manager_id": 2,
    "site_manager": {
      "id": 2
    }
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Daily log created successfully.",
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": false,
    "site_managers": [
      {
        "id": 2,
        "first_name": "Ana",
        "last_name": "Ivić",
        "full_name": "Ana Ivić",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "first_name": "Milan",
        "last_name": "Petrović",
        "full_name": "Milan Petrović",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ]
  }
}
```

PWA CHANGE:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

Current HTTP outcomes — 201, 401, 403, 404, 409, 422, 500.

### POST /api/v1/daily-logs/{dailyLog}/lock

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Daily log locked successfully.",
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": true,
    "site_manager_id": 2,
    "site_manager": {
      "id": 2
    }
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Daily log locked successfully.",
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": true,
    "site_managers": [
      {
        "id": 2,
        "first_name": "Ana",
        "last_name": "Ivić",
        "full_name": "Ana Ivić",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "first_name": "Milan",
        "last_name": "Petrović",
        "full_name": "Milan Petrović",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ]
  }
}
```

PWA CHANGE:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

Current HTTP outcomes — 200, 401, 404, 500.

### POST /api/v1/daily-logs/{dailyLog}/unlock

PREVIOUS REQUEST:

```json
{
  "reason": "Correction"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Correction"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Daily log unlocked successfully.",
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": false,
    "site_manager_id": 2,
    "site_manager": {
      "id": 2
    }
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Daily log unlocked successfully.",
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": false,
    "site_managers": [
      {
        "id": 2,
        "first_name": "Ana",
        "last_name": "Ivić",
        "full_name": "Ana Ivić",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "first_name": "Milan",
        "last_name": "Petrović",
        "full_name": "Milan Petrović",
        "username": null,
        "email": null,
        "phone": null,
        "role": "site_manager",
        "hourly_rate": null,
        "is_active": true,
        "created_at": null,
        "updated_at": null,
        "is_available": true,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ]
  }
}
```

PWA CHANGE:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

Current HTTP outcomes — 200, 401, 404, 422, 500.

### GET /api/v1/daily-logs/{dailyLog}/worker-attendances

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 81,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "worker_id": 12,
      "started_at": "2026-11-22T07:00:00.000000Z",
      "finished_at": "2026-11-22T11:00:00.000000Z"
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 81,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "worker_id": 12,
      "started_at": "2026-11-22T07:00:00.000000Z",
      "finished_at": "2026-11-22T11:00:00.000000Z"
    }
  ]
}
```

PWA CHANGE:

- Key this list by DailyLog ID. Invalidate after edits by either member.

Current HTTP outcomes — 200, 401, 403, 404, 422.

### POST /api/v1/daily-logs/{dailyLog}/worker-attendances

PREVIOUS REQUEST:

```json
{
  "worker_id": 12,
  "started_at": "2026-11-22 08:00:00",
  "finished_at": "2026-11-22 12:00:00",
  "advance_payment": 5
}
```

CURRENT REQUEST:

```json
{
  "worker_id": 12,
  "started_at": "2026-11-22 08:00:00",
  "finished_at": "2026-11-22 12:00:00",
  "advance_payment": 5
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Worker attendance created successfully.",
  "data": {
    "id": 81,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "worker_id": 12,
    "started_at": "2026-11-22T07:00:00.000000Z",
    "finished_at": "2026-11-22T11:00:00.000000Z"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Worker attendance created successfully.",
  "data": {
    "id": 81,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "worker_id": 12,
    "started_at": "2026-11-22T07:00:00.000000Z",
    "finished_at": "2026-11-22T11:00:00.000000Z"
  }
}
```

PWA CHANGE:

- Use the canonical dailyLogId and invalidate that shared list after creation.

Current HTTP outcomes — 200, 401, 403, 404, 409, 422, 500.

Manual creation accepts an existing same-company worker ID. The daily attendance limit has been removed. Multiple non-overlapping periods on the same or different sites are valid for the same company and worker. Machine flows generate attendance on managerial confirmation; do not send an additional manual POST for a machine period.

### PATCH /api/v1/daily-logs/{dailyLog}/worker-attendances/{workerAttendance}

PREVIOUS REQUEST:

```json
{
  "started_at": "2026-11-22 08:00:00",
  "finished_at": "2026-11-22 13:00:00"
}
```

CURRENT REQUEST:

```json
{
  "started_at": "2026-11-22 08:00:00",
  "finished_at": "2026-11-22 13:00:00"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Worker attendance updated successfully.",
  "data": {
    "id": 81,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "worker_id": 12,
    "started_at": "2026-11-22T07:00:00.000000Z",
    "finished_at": "2026-11-22T12:00:00.000000Z"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Worker attendance updated successfully.",
  "data": {
    "id": 81,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "worker_id": 12,
    "started_at": "2026-11-22T07:00:00.000000Z",
    "finished_at": "2026-11-22T12:00:00.000000Z"
  }
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 409, 422, 500.

### DELETE /api/v1/daily-logs/{dailyLog}/worker-attendances/{workerAttendance}

PREVIOUS REQUEST:

```json
{
  "reason": "Duplicate"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Duplicate"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Worker attendance deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Worker attendance deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### GET /api/v1/daily-logs/{dailyLog}/expenses

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "expenses": [
      {
        "id": 91,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "title": "Fuel",
        "amount": "125.00"
      }
    ],
    "summary": {
      "site_manager": {
        "id": 2
      },
      "advanced": 300,
      "spent": 125,
      "remaining": 175,
      "utilization": 41.67
    }
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "expenses": [
      {
        "id": 91,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "title": "Fuel",
        "amount": "125.00"
      }
    ],
    "summary": {
      "site_manager": {
        "id": 2
      },
      "advanced": 300,
      "spent": 125,
      "remaining": 175,
      "utilization": 41.67
    }
  }
}
```

PWA CHANGE:

- Key this list by DailyLog ID. Invalidate after edits by either member.

Current HTTP outcomes — 200, 401, 403, 404, 422.

### POST /api/v1/daily-logs/{dailyLog}/expenses

PREVIOUS REQUEST:

```json
{
  "title": "Fuel",
  "amount": 125
}
```

CURRENT REQUEST:

```json
{
  "title": "Fuel",
  "amount": 125
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Expense created successfully.",
  "data": {
    "id": 91,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "title": "Fuel",
    "amount": "125.00"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Expense created successfully.",
  "data": {
    "id": 91,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "title": "Fuel",
    "amount": "125.00"
  }
}
```

PWA CHANGE:

- Use the canonical dailyLogId and invalidate that shared list after creation.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### PATCH /api/v1/daily-logs/{dailyLog}/expenses/{expense}

PREVIOUS REQUEST:

```json
{
  "title": "Diesel",
  "amount": 125
}
```

CURRENT REQUEST:

```json
{
  "title": "Diesel",
  "amount": 125
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Expense updated successfully.",
  "data": {
    "id": 91,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "title": "Diesel",
    "amount": "125.00"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Expense updated successfully.",
  "data": {
    "id": 91,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "title": "Diesel",
    "amount": "125.00"
  }
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### DELETE /api/v1/daily-logs/{dailyLog}/expenses/{expense}

PREVIOUS REQUEST:

```json
{
  "reason": "Duplicate"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Duplicate"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Expense deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Expense deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### GET /api/v1/daily-logs/{dailyLog}/notes

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 92,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "note": "Site inspected",
      "worker_count": 3,
      "notify_admin": false,
      "daily_log": {
        "id": 55,
        "construction_site_id": 4,
        "date": "2026-11-21T23:00:00.000000Z",
        "is_locked": false,
        "site_manager_id": 2,
        "site_manager": {
          "id": 2
        }
      }
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 92,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "note": "Site inspected",
      "worker_count": 3,
      "notify_admin": false,
      "daily_log": {
        "id": 55,
        "construction_site_id": 4,
        "date": "2026-11-21T23:00:00.000000Z",
        "is_locked": false,
        "site_managers": [
          {
            "id": 2,
            "first_name": "Ana",
            "last_name": "Ivić",
            "full_name": "Ana Ivić",
            "username": null,
            "email": null,
            "phone": null,
            "role": "site_manager",
            "hourly_rate": null,
            "is_active": true,
            "created_at": null,
            "updated_at": null,
            "is_available": true,
            "joined_at": "2026-11-22T07:00:00.000000Z"
          },
          {
            "id": 7,
            "first_name": "Milan",
            "last_name": "Petrović",
            "full_name": "Milan Petrović",
            "username": null,
            "email": null,
            "phone": null,
            "role": "site_manager",
            "hourly_rate": null,
            "is_active": true,
            "created_at": null,
            "updated_at": null,
            "is_available": true,
            "joined_at": "2026-11-22T07:30:00.000000Z"
          }
        ]
      }
    }
  ]
}
```

PWA CHANGE:

- Key this list by DailyLog ID. Invalidate after edits by either member.

Current HTTP outcomes — 200, 401, 403, 404, 422.

### POST /api/v1/daily-logs/{dailyLog}/notes

PREVIOUS REQUEST:

```json
{
  "note": "Site inspected",
  "worker_count": 3,
  "notify_admin": false
}
```

CURRENT REQUEST:

```json
{
  "note": "Site inspected",
  "worker_count": 3,
  "notify_admin": false
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Note created successfully.",
  "data": {
    "id": 92,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "note": "Site inspected",
    "worker_count": 3,
    "notify_admin": false
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Note created successfully.",
  "data": {
    "id": 92,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "note": "Site inspected",
    "worker_count": 3,
    "notify_admin": false
  }
}
```

PWA CHANGE:

- Use the canonical dailyLogId and invalidate that shared list after creation.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### PATCH /api/v1/daily-logs/{dailyLog}/notes/{note}

PREVIOUS REQUEST:

```json
{
  "note": "Site inspected again",
  "worker_count": 3,
  "notify_admin": false,
  "reason": "Correction"
}
```

CURRENT REQUEST:

```json
{
  "note": "Site inspected again",
  "worker_count": 3,
  "notify_admin": false,
  "reason": "Correction"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Note updated successfully.",
  "data": {
    "id": 92,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "note": "Site inspected again",
    "worker_count": 3,
    "notify_admin": false
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Note updated successfully.",
  "data": {
    "id": 92,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "note": "Site inspected again",
    "worker_count": 3,
    "notify_admin": false
  }
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### DELETE /api/v1/daily-logs/{dailyLog}/notes/{note}

PREVIOUS REQUEST:

```json
{
  "reason": "Duplicate"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Duplicate"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Note deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Note deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### GET /api/v1/daily-logs/{dailyLog}/delivery-notes

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 93,
      "name": "Concrete delivery",
      "description": "Pallet 1"
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 93,
      "name": "Concrete delivery",
      "description": "Pallet 1"
    }
  ]
}
```

PWA CHANGE:

- Key this list by DailyLog ID. Invalidate after edits by either member.

Current HTTP outcomes — 200, 401, 403, 404, 422.

### POST /api/v1/daily-logs/{dailyLog}/delivery-notes

PREVIOUS REQUEST:

```json
{
  "supplier_id": 3,
  "name": "Concrete delivery",
  "description": "Pallet 1"
}
```

CURRENT REQUEST:

```json
{
  "supplier_id": 3,
  "name": "Concrete delivery",
  "description": "Pallet 1"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 93,
    "name": "Concrete delivery",
    "description": "Pallet 1"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 93,
    "name": "Concrete delivery",
    "description": "Pallet 1"
  }
}
```

PWA CHANGE:

- Use the canonical dailyLogId and invalidate that shared list after creation.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### PATCH /api/v1/daily-logs/{dailyLog}/delivery-notes/{deliveryNote}

PREVIOUS REQUEST:

```json
{
  "supplier_id": 3,
  "name": "Concrete delivery",
  "description": "Pallet 2",
  "reason": "Correction"
}
```

CURRENT REQUEST:

```json
{
  "supplier_id": 3,
  "name": "Concrete delivery",
  "description": "Pallet 2",
  "reason": "Correction"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Delivery note updated successfully.",
  "data": {
    "id": 93,
    "name": "Concrete delivery",
    "description": "Pallet 2"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Delivery note updated successfully.",
  "data": {
    "id": 93,
    "name": "Concrete delivery",
    "description": "Pallet 2"
  }
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### DELETE /api/v1/daily-logs/{dailyLog}/delivery-notes/{deliveryNote}

PREVIOUS REQUEST:

```json
{
  "reason": "Duplicate"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Duplicate"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Delivery note deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Delivery note deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### POST /api/v1/daily-logs/{dailyLog}/subcontractors

PREVIOUS REQUEST:

```json
{
  "subcontractor_id": 3,
  "worker_count": 2,
  "started_at": "2026-11-22 08:00:00",
  "finished_at": "2026-11-22 10:00:00"
}
```

CURRENT REQUEST:

```json
{
  "subcontractor_id": 3,
  "worker_count": 2,
  "started_at": "2026-11-22 08:00:00",
  "finished_at": "2026-11-22 10:00:00"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Subcontractor added successfully.",
  "data": {
    "id": 94,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "subcontractor_id": 3,
    "worker_count": 2
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Subcontractor added successfully.",
  "data": {
    "id": 94,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "subcontractor_id": 3,
    "worker_count": 2
  }
}
```

PWA CHANGE:

- Use the canonical dailyLogId and invalidate that shared list after creation.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### PATCH /api/v1/daily-logs/{dailyLog}/subcontractors/{subcontractorLog}

PREVIOUS REQUEST:

```json
{
  "worker_count": 3,
  "reason": "Correction"
}
```

CURRENT REQUEST:

```json
{
  "worker_count": 3,
  "reason": "Correction"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Subcontractor updated successfully.",
  "data": {
    "id": 94,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "subcontractor_id": 3,
    "worker_count": 3
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Subcontractor updated successfully.",
  "data": {
    "id": 94,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "subcontractor_id": 3,
    "worker_count": 3
  }
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### DELETE /api/v1/daily-logs/{dailyLog}/subcontractors/{subcontractorLog}

PREVIOUS REQUEST:

```json
{
  "reason": "Duplicate"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Duplicate"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "SubcontractorLog deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "SubcontractorLog deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- Update or remove that shared child by ID and refresh its daily list.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### GET /api/v1/daily-logs/{dailyLog}/expenses/history

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "expenses": [
      {
        "id": 91,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "title": "Fuel",
        "amount": "125.00",
        "daily_log": {
          "id": 55,
          "site_manager_id": 2
        }
      }
    ],
    "summary": {
      "site_manager": {
        "id": 2
      },
      "advanced": 300,
      "spent": 125,
      "remaining": 175,
      "utilization": 41.67
    },
    "cash_advances": []
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "expenses": [
      {
        "id": 91,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "title": "Fuel",
        "amount": "125.00",
        "daily_log": {
          "id": 55,
          "site_managers": [
            {
              "id": 2,
              "first_name": "Ana",
              "last_name": "Ivić",
              "full_name": "Ana Ivić",
              "username": null,
              "email": null,
              "phone": null,
              "role": "site_manager",
              "hourly_rate": null,
              "is_active": true,
              "created_at": null,
              "updated_at": null,
              "is_available": true,
              "joined_at": "2026-11-22T07:00:00.000000Z"
            },
            {
              "id": 7,
              "first_name": "Milan",
              "last_name": "Petrović",
              "full_name": "Milan Petrović",
              "username": null,
              "email": null,
              "phone": null,
              "role": "site_manager",
              "hourly_rate": null,
              "is_active": true,
              "created_at": null,
              "updated_at": null,
              "is_available": true,
              "joined_at": "2026-11-22T07:30:00.000000Z"
            }
          ]
        }
      }
    ],
    "summary": {
      "site_manager": {
        "id": 2
      },
      "advanced": 300,
      "spent": 125,
      "remaining": 175,
      "utilization": 41.67
    },
    "cash_advances": []
  }
}
```

PWA CHANGE:

- Key this list by DailyLog ID. Invalidate after edits by either member.

Current HTTP outcomes — 200, 401, 403, 404, 422.

### GET /api/v1/expenses

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 91,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "title": "Fuel",
      "amount": "125.00",
      "daily_log": {
        "id": 55,
        "site_manager_id": 2
      }
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 91,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "title": "Fuel",
      "amount": "125.00",
      "daily_log": {
        "id": 55,
        "site_managers": [
          {
            "id": 2,
            "first_name": "Ana",
            "last_name": "Ivić",
            "full_name": "Ana Ivić",
            "username": null,
            "email": null,
            "phone": null,
            "role": "site_manager",
            "hourly_rate": null,
            "is_active": true,
            "created_at": null,
            "updated_at": null,
            "is_available": true,
            "joined_at": "2026-11-22T07:00:00.000000Z"
          },
          {
            "id": 7,
            "first_name": "Milan",
            "last_name": "Petrović",
            "full_name": "Milan Petrović",
            "username": null,
            "email": null,
            "phone": null,
            "role": "site_manager",
            "hourly_rate": null,
            "is_active": true,
            "created_at": null,
            "updated_at": null,
            "is_available": true,
            "joined_at": "2026-11-22T07:30:00.000000Z"
          }
        ]
      }
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/admin/expenses

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "expenses": [
      {
        "id": 91,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "title": "Fuel",
        "amount": "125.00",
        "daily_log": {
          "id": 55,
          "site_manager_id": 2
        }
      }
    ],
    "total": "125.00"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "expenses": [
      {
        "id": 91,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "title": "Fuel",
        "amount": "125.00",
        "daily_log": {
          "id": 55,
          "site_managers": [
            {
              "id": 2,
              "first_name": "Ana",
              "last_name": "Ivić",
              "full_name": "Ana Ivić",
              "username": null,
              "email": null,
              "phone": null,
              "role": "site_manager",
              "hourly_rate": null,
              "is_active": true,
              "created_at": null,
              "updated_at": null,
              "is_available": true,
              "joined_at": "2026-11-22T07:00:00.000000Z"
            },
            {
              "id": 7,
              "first_name": "Milan",
              "last_name": "Petrović",
              "full_name": "Milan Petrović",
              "username": null,
              "email": null,
              "phone": null,
              "role": "site_manager",
              "hourly_rate": null,
              "is_active": true,
              "created_at": null,
              "updated_at": null,
              "is_available": true,
              "joined_at": "2026-11-22T07:30:00.000000Z"
            }
          ]
        }
      }
    ],
    "total": "125.00"
  }
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 403, 422.

### GET /api/v1/notes

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 92,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "note": "Site inspected",
      "worker_count": 3,
      "notify_admin": false,
      "daily_log": {
        "id": 55,
        "construction_site_id": 4,
        "date": "2026-11-21T23:00:00.000000Z",
        "is_locked": false,
        "site_manager_id": 2,
        "site_manager": {
          "id": 2
        }
      }
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 92,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "note": "Site inspected",
      "worker_count": 3,
      "notify_admin": false,
      "daily_log": {
        "id": 55,
        "construction_site_id": 4,
        "date": "2026-11-21T23:00:00.000000Z",
        "is_locked": false,
        "site_managers": [
          {
            "id": 2,
            "first_name": "Ana",
            "last_name": "Ivić",
            "full_name": "Ana Ivić",
            "username": null,
            "email": null,
            "phone": null,
            "role": "site_manager",
            "hourly_rate": null,
            "is_active": true,
            "created_at": null,
            "updated_at": null,
            "is_available": true,
            "joined_at": "2026-11-22T07:00:00.000000Z"
          },
          {
            "id": 7,
            "first_name": "Milan",
            "last_name": "Petrović",
            "full_name": "Milan Petrović",
            "username": null,
            "email": null,
            "phone": null,
            "role": "site_manager",
            "hourly_rate": null,
            "is_active": true,
            "created_at": null,
            "updated_at": null,
            "is_available": true,
            "joined_at": "2026-11-22T07:30:00.000000Z"
          }
        ]
      }
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/delivery-notes

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 93,
      "name": "Concrete delivery",
      "description": "Pallet 1",
      "daily_log": {
        "id": 55,
        "construction_site_id": 4,
        "date": "2026-11-21T23:00:00.000000Z",
        "is_locked": false,
        "site_manager_id": 2,
        "site_manager": {
          "id": 2
        }
      }
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 93,
      "name": "Concrete delivery",
      "description": "Pallet 1",
      "daily_log": {
        "id": 55,
        "construction_site_id": 4,
        "date": "2026-11-21T23:00:00.000000Z",
        "is_locked": false,
        "site_managers": [
          {
            "id": 2,
            "first_name": "Ana",
            "last_name": "Ivić",
            "full_name": "Ana Ivić",
            "username": null,
            "email": null,
            "phone": null,
            "role": "site_manager",
            "hourly_rate": null,
            "is_active": true,
            "created_at": null,
            "updated_at": null,
            "is_available": true,
            "joined_at": "2026-11-22T07:00:00.000000Z"
          },
          {
            "id": 7,
            "first_name": "Milan",
            "last_name": "Petrović",
            "full_name": "Milan Petrović",
            "username": null,
            "email": null,
            "phone": null,
            "role": "site_manager",
            "hourly_rate": null,
            "is_active": true,
            "created_at": null,
            "updated_at": null,
            "is_available": true,
            "joined_at": "2026-11-22T07:30:00.000000Z"
          }
        ]
      }
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/subcontractor-logs

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 94,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "subcontractor_id": 3,
      "worker_count": 2
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 94,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "subcontractor_id": 3,
      "worker_count": 2
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/worker-attendances

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 81,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "worker_id": 12,
      "started_at": "2026-11-22T07:00:00.000000Z",
      "finished_at": "2026-11-22T11:00:00.000000Z"
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 81,
      "daily_log_id": 55,
      "site_manager_id": 2,
      "worker_id": 12,
      "started_at": "2026-11-22T07:00:00.000000Z",
      "finished_at": "2026-11-22T11:00:00.000000Z"
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/subcontractors

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 3
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 3
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 422.

### POST /api/v1/daily-logs/{dailyLog}/attachments

PREVIOUS REQUEST:

multipart/form-data — attachments[] (files)

CURRENT REQUEST:

multipart/form-data — attachments[] (files)

PREVIOUS RESPONSE:

```json
{
  "data": [
    {
      "id": 29,
      "name": "receipt.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf"
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "data": [
    {
      "id": 29,
      "name": "receipt.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf"
    }
  ]
}
```

PWA CHANGE:

- Authenticate uploads and downloads. Only construct a Blob for a successful download and handle empty 204 on delete.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### POST /api/v1/expenses/{expense}/attachments

PREVIOUS REQUEST:

multipart/form-data — attachments[] (files)

CURRENT REQUEST:

multipart/form-data — attachments[] (files)

PREVIOUS RESPONSE:

```json
{
  "data": [
    {
      "id": 29,
      "name": "receipt.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf"
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "data": [
    {
      "id": 29,
      "name": "receipt.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf"
    }
  ]
}
```

PWA CHANGE:

- Authenticate uploads and downloads. Only construct a Blob for a successful download and handle empty 204 on delete.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### GET /api/v1/attachments/{attachment}

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

File stream with Content-Disposition.

CURRENT RESPONSE:

File stream with Content-Disposition.

PWA CHANGE:

- Authenticate uploads and downloads. Only construct a Blob for a successful download and handle empty 204 on delete.

Current HTTP outcomes — 200, 401, 403, 404.

### DELETE /api/v1/attachments/{attachment}

PREVIOUS REQUEST:

```json
{
  "reason": "Wrong document"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Wrong document"
}
```

PREVIOUS RESPONSE:

NO BODY — 204

CURRENT RESPONSE:

NO BODY — 204

PWA CHANGE:

- Authenticate uploads and downloads. Only construct a Blob for a successful download and handle empty 204 on delete.

Current HTTP outcomes — 204, 401, 403, 404, 422, 500.

### POST /api/v1/excavator-logs/operator

PREVIOUS REQUEST:

```json
{
  "construction_site_id": 4,
  "machine_id": 31,
  "operator_started_at": "2026-11-22 08:00:00"
}
```

CURRENT REQUEST:

```json
{
  "construction_site_id": 4,
  "machine_id": 31,
  "operator_started_at": "2026-11-22 08:00:00"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Excavator log created successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": null,
    "site_manager_finished_at": null
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Excavator log created successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": null,
    "site_manager_finished_at": null
  }
}
```

PWA CHANGE:

- Mark the assignment pending and invalidate available/current/occupied.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### POST /api/v1/excavator-logs/daily-logs/{dailyLog}

PREVIOUS REQUEST:

```json
{
  "machine_id": 31,
  "worker_id": 12,
  "site_manager_started_at": "2026-11-22 08:00:00",
  "site_manager_finished_at": "2026-11-22 10:00:00"
}
```

CURRENT REQUEST:

```json
{
  "machine_id": 31,
  "worker_id": 12,
  "site_manager_started_at": "2026-11-22 08:00:00",
  "site_manager_finished_at": "2026-11-22 10:00:00"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Excavator log created successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Excavator log created successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

PWA CHANGE:

- Refresh the shared assignment and salary attendance after takeover.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### PATCH /api/v1/excavator-logs/{excavatorLog}

PREVIOUS REQUEST:

```json
{
  "site_manager_started_at": "2026-11-22 08:00:00",
  "site_manager_finished_at": "2026-11-22 10:00:00"
}
```

CURRENT REQUEST:

```json
{
  "site_manager_started_at": "2026-11-22 08:00:00",
  "site_manager_finished_at": "2026-11-22 10:00:00"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Excavator log updated successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Excavator log updated successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

PWA CHANGE:

- Use separate forms for manager and self updates; disable mutation while locked.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### DELETE /api/v1/excavator-logs/{excavatorLog}

PREVIOUS REQUEST:

```json
{
  "reason": "Wrong assignment"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Wrong assignment"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Excavator log deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Excavator log deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- After success invalidate the log, assignment and worker/machine availability queries.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### GET /api/v1/excavator-logs/available

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Available excavators retrieved successfully.",
  "data": []
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Available excavators retrieved successfully.",
  "data": []
}
```

PWA CHANGE:

- Refresh available after a member modifies any active period.

Current HTTP outcomes — 200, 401.

### GET /api/v1/excavator-logs/occupied

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Occupied excavators retrieved successfully.",
  "data": [
    {
      "id": 33,
      "site_manager": {
        "id": 2
      }
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Occupied excavators retrieved successfully.",
  "data": [
    {
      "id": 33,
      "site_manager": null
    }
  ]
}
```

PWA CHANGE:

- Keep occupied visibility separate from global availability.

Current HTTP outcomes — 200, 401.

### POST /api/v1/truck-logs/driver

PREVIOUS REQUEST:

```json
{
  "construction_site_id": 4,
  "machine_id": 31,
  "operator_started_at": "2026-11-22 08:00:00"
}
```

CURRENT REQUEST:

```json
{
  "construction_site_id": 4,
  "machine_id": 31,
  "operator_started_at": "2026-11-22 08:00:00"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Truck assigned successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": null,
    "site_manager_finished_at": null
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Truck assigned successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": null,
    "site_manager_finished_at": null
  }
}
```

PWA CHANGE:

- Mark the assignment pending and invalidate available/current/occupied.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### POST /api/v1/truck-logs/daily-logs/{dailyLog}

PREVIOUS REQUEST:

```json
{
  "machine_id": 31,
  "worker_id": 12,
  "site_manager_started_at": "2026-11-22 08:00:00",
  "site_manager_finished_at": "2026-11-22 10:00:00"
}
```

CURRENT REQUEST:

```json
{
  "machine_id": 31,
  "worker_id": 12,
  "site_manager_started_at": "2026-11-22 08:00:00",
  "site_manager_finished_at": "2026-11-22 10:00:00"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Truck assigned successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Truck assigned successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

PWA CHANGE:

- Refresh the shared assignment and salary attendance after takeover.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### PATCH /api/v1/truck-logs/{truckLog}

PREVIOUS REQUEST:

```json
{
  "site_manager_started_at": "2026-11-22 08:00:00",
  "site_manager_finished_at": "2026-11-22 10:00:00"
}
```

CURRENT REQUEST:

```json
{
  "site_manager_started_at": "2026-11-22 08:00:00",
  "site_manager_finished_at": "2026-11-22 10:00:00"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Truck log updated successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Truck log updated successfully.",
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

PWA CHANGE:

- Use separate forms for manager and self updates; disable mutation while locked.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### DELETE /api/v1/truck-logs/{truckLog}

PREVIOUS REQUEST:

```json
{
  "reason": "Wrong assignment"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Wrong assignment"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Truck log deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Truck log deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- After success invalidate the log, assignment and worker/machine availability queries.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### GET /api/v1/truck-logs/available

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Available trucks retrieved successfully.",
  "data": []
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Available trucks retrieved successfully.",
  "data": []
}
```

PWA CHANGE:

- Refresh available after a member modifies any active period.

Current HTTP outcomes — 200, 401.

### GET /api/v1/truck-logs/occupied

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Occupied trucks retrieved successfully.",
  "data": [
    {
      "id": 33,
      "site_manager": {
        "id": 2
      }
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Occupied trucks retrieved successfully.",
  "data": [
    {
      "id": 33,
      "site_manager": null
    }
  ]
}
```

PWA CHANGE:

- Keep occupied visibility separate from global availability.

Current HTTP outcomes — 200, 401.

### GET /api/v1/machine-assignments

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 33,
      "site_manager": {
        "id": 2
      }
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 33,
      "site_manager": null
    }
  ]
}
```

PWA CHANGE:

- Refresh by site/date and active DailyLog, and keep attribution separate from access.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/machine-assignments/current

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Current machine retrieved successfully.",
  "data": {
    "id": 33,
    "site_manager": {
      "id": 2
    }
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Current machine retrieved successfully.",
  "data": {
    "id": 33,
    "site_manager": null
  }
}
```

PWA CHANGE:

- Refresh by site/date and active DailyLog, and keep attribution separate from access.

Current HTTP outcomes — 200, 401, 404, 422.

### GET /api/v1/construction-sites

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 4,
      "name": "North site",
      "can_select": false
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 4,
      "name": "North site",
      "can_select": true
    }
  ]
}
```

PWA CHANGE:

- Connect can_select to the /select action.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/workers

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 12,
      "is_available": true
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 12,
      "is_available": false
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/workers/{worker}

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 12
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 12
  }
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 404.

### GET /api/v1/workers/{worker}/work-history

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "worker": {
      "id": 12
    },
    "type": "excavator_log",
    "history": [
      {
        "id": 61
      }
    ]
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "worker": {
      "id": 12
    },
    "type": "excavator_log",
    "history": []
  }
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 404, 422.

### GET /api/v1/site-managers/{siteManager}/assigned-sites

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 4,
      "name": "North site",
      "can_select": null
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 4,
      "name": "North site",
      "can_select": null
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 404.

### GET /api/v1/site-managers/{siteManager}/overall

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "cash_advance_total": "300.00",
      "construction_site": {
        "id": 4,
        "name": "North site",
        "can_select": null
      },
      "expenses": [
        {
          "id": 91,
          "daily_log_id": 55,
          "site_manager_id": 2,
          "title": "Fuel",
          "amount": "125.00",
          "daily_log": {
            "id": 55,
            "site_manager_id": 2
          }
        }
      ],
      "worker_attendance": [
        {
          "id": 81,
          "daily_log_id": 55,
          "site_manager_id": 2,
          "worker_id": 12,
          "started_at": "2026-11-22T07:00:00.000000Z",
          "finished_at": "2026-11-22T11:00:00.000000Z"
        }
      ]
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "cash_advance_total": "300.00",
      "construction_site": {
        "id": 4,
        "name": "North site",
        "can_select": null
      },
      "expenses": [
        {
          "id": 91,
          "daily_log_id": 55,
          "site_manager_id": 2,
          "title": "Fuel",
          "amount": "125.00",
          "daily_log": {
            "id": 55,
            "site_managers": [
              {
                "id": 2,
                "first_name": "Ana",
                "last_name": "Ivić",
                "full_name": "Ana Ivić",
                "username": null,
                "email": null,
                "phone": null,
                "role": "site_manager",
                "hourly_rate": null,
                "is_active": true,
                "created_at": null,
                "updated_at": null,
                "is_available": true,
                "joined_at": "2026-11-22T07:00:00.000000Z"
              },
              {
                "id": 7,
                "first_name": "Milan",
                "last_name": "Petrović",
                "full_name": "Milan Petrović",
                "username": null,
                "email": null,
                "phone": null,
                "role": "site_manager",
                "hourly_rate": null,
                "is_active": true,
                "created_at": null,
                "updated_at": null,
                "is_available": true,
                "joined_at": "2026-11-22T07:30:00.000000Z"
              }
            ]
          }
        }
      ],
      "worker_attendance": [
        {
          "id": 81,
          "daily_log_id": 55,
          "site_manager_id": 2,
          "worker_id": 12,
          "started_at": "2026-11-22T07:00:00.000000Z",
          "finished_at": "2026-11-22T11:00:00.000000Z"
        }
      ]
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 404, 422.

### GET /api/v1/construction-sites/{constructionSite}/financial-summary

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "expenses": [
      {
        "id": 91,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "title": "Fuel",
        "amount": "125.00",
        "daily_log": {
          "id": 55,
          "site_manager_id": 2
        }
      }
    ],
    "cash_advances": []
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "expenses": [
      {
        "id": 91,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "title": "Fuel",
        "amount": "125.00",
        "daily_log": {
          "id": 55,
          "site_managers": [
            {
              "id": 2,
              "first_name": "Ana",
              "last_name": "Ivić",
              "full_name": "Ana Ivić",
              "username": null,
              "email": null,
              "phone": null,
              "role": "site_manager",
              "hourly_rate": null,
              "is_active": true,
              "created_at": null,
              "updated_at": null,
              "is_available": true,
              "joined_at": "2026-11-22T07:00:00.000000Z"
            },
            {
              "id": 7,
              "first_name": "Milan",
              "last_name": "Petrović",
              "full_name": "Milan Petrović",
              "username": null,
              "email": null,
              "phone": null,
              "role": "site_manager",
              "hourly_rate": null,
              "is_active": true,
              "created_at": null,
              "updated_at": null,
              "is_available": true,
              "joined_at": "2026-11-22T07:30:00.000000Z"
            }
          ]
        }
      }
    ],
    "cash_advances": []
  }
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 404, 422.

### GET /api/v1/construction-sites/{constructionSite}/statistics

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "total_expenses": 125,
    "total_worker_hours": 4,
    "total_subcontractor_hours": 2,
    "total_machine_hours": 3
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "total_expenses": 125,
    "total_worker_hours": 4,
    "total_subcontractor_hours": 2,
    "total_machine_hours": 3
  }
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 404, 422.

### GET /api/v1/financial-summary

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "site_manager": {
        "id": 2
      },
      "advanced": 300,
      "spent": 125,
      "remaining": 175,
      "utilization": 41.67
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "site_manager": {
        "id": 2
      },
      "advanced": 300,
      "spent": 125,
      "remaining": 175,
      "utilization": 41.67
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401.

### GET /api/v1/admin/construction-financial-summary

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "summary": {
      "expenses": {
        "total": 125,
        "count": 1
      }
    }
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "summary": {
      "expenses": {
        "total": 125,
        "count": 1
      }
    }
  }
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/construction-sites/{constructionSite}/site-managers

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 2
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 2
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 404.

### GET /api/v1/tasks

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 80,
      "title": "Inspect site"
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 80,
      "title": "Inspect site"
    }
  ]
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 422.

### GET /api/v1/admin/worker-salaries

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "workers": [
      {
        "worker_id": 12,
        "period": {
          "earned": "100.00",
          "worked_hours": "4.00"
        }
      }
    ]
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": {
    "workers": [
      {
        "worker_id": 12,
        "period": {
          "earned": "100.00",
          "worked_hours": "4.00"
        }
      }
    ]
  }
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 403, 422.

### GET /api/v1/workers/{worker}/salary

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "data": {
    "worker_id": 12,
    "earned": "100.00",
    "advances": "5.00",
    "payments": "10.00",
    "outstanding": "85.00",
    "attendances": [
      {
        "id": 81,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "worker_id": 12,
        "started_at": "2026-11-22T07:00:00.000000Z",
        "finished_at": "2026-11-22T11:00:00.000000Z"
      }
    ]
  }
}
```

CURRENT RESPONSE:

```json
{
  "data": {
    "worker_id": 12,
    "earned": "100.00",
    "advances": "5.00",
    "payments": "10.00",
    "outstanding": "85.00",
    "attendances": [
      {
        "id": 81,
        "daily_log_id": 55,
        "site_manager_id": 2,
        "worker_id": 12,
        "started_at": "2026-11-22T07:00:00.000000Z",
        "finished_at": "2026-11-22T11:00:00.000000Z"
      }
    ]
  }
}
```

PWA CHANGE:

- Key report/list requests by active tenant and reset the view when the tenant changes.

Current HTTP outcomes — 200, 401, 404, 422.

### POST /api/v1/construction-sites/{constructionSite}/site-managers/{worker}

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Site manager assigned successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Site manager assigned successfully.",
  "data": null
}
```

PWA CHANGE:

- Use same-company administrator controls for permanent assignment. For delete consume an empty 204.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

### DELETE /api/v1/construction-sites/{constructionSite}/site-managers/{worker}

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

NO BODY — 204

CURRENT RESPONSE:

NO BODY — 204

PWA CHANGE:

- Use same-company administrator controls for permanent assignment. For delete consume an empty 204.

Current HTTP outcomes — 204, 401, 403, 404, 500.

### POST /api/v1/tasks

PREVIOUS REQUEST:

```json
{
  "title": "Inspect site",
  "construction_site_id": 4
}
```

CURRENT REQUEST:

```json
{
  "title": "Inspect site",
  "construction_site_id": 4
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Task created successfully.",
  "data": {
    "id": 80,
    "title": "Inspect site"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Task created successfully.",
  "data": {
    "id": 80,
    "title": "Inspect site"
  }
}
```

PWA CHANGE:

- Invalidate the current tenant task list after mutation.

Current HTTP outcomes — 200, 401, 404, 422.

### PATCH /api/v1/tasks/{task}

PREVIOUS REQUEST:

```json
{
  "title": "Inspect north site",
  "construction_site_id": 4,
  "reason": "Clarification"
}
```

CURRENT REQUEST:

```json
{
  "title": "Inspect north site",
  "construction_site_id": 4,
  "reason": "Clarification"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Task updated successfully.",
  "data": {
    "id": 80,
    "title": "Inspect north site"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Task updated successfully.",
  "data": {
    "id": 80,
    "title": "Inspect north site"
  }
}
```

PWA CHANGE:

- Invalidate the current tenant task list after mutation.

Current HTTP outcomes — 200, 401, 404, 422.

### DELETE /api/v1/tasks/{task}

PREVIOUS REQUEST:

```json
{
  "reason": "Cancelled"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Cancelled"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Task deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Task deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- Invalidate the current tenant task list after mutation.

Current HTTP outcomes — 200, 401, 404, 422.

### GET /api/v1/cash-advances

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 25,
      "site_manager": {
        "id": 2
      },
      "amount": "300.00"
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 25,
      "site_manager": {
        "id": 2
      },
      "amount": "300.00"
    }
  ]
}
```

PWA CHANGE:

- Scope cash-advance lists and selector to the active company.

Current HTTP outcomes — 200, 401, 422.

### POST /api/v1/cash-advances

PREVIOUS REQUEST:

```json
{
  "site_manager_id": 2,
  "amount": 300,
  "date": "2026-11-22"
}
```

CURRENT REQUEST:

```json
{
  "site_manager_id": 2,
  "amount": 300,
  "date": "2026-11-22"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Cash advance created successfully.",
  "data": {
    "id": 25,
    "site_manager": {
      "id": 2
    },
    "amount": "300.00"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Cash advance created successfully.",
  "data": {
    "id": 25,
    "site_manager": {
      "id": 2
    },
    "amount": "300.00"
  }
}
```

PWA CHANGE:

- Scope cash-advance lists and selector to the active company.

Current HTTP outcomes — 200, 401, 404, 422, 500.

### PATCH /api/v1/cash-advances/{cashAdvance}

PREVIOUS REQUEST:

```json
{
  "site_manager_id": 2,
  "amount": 310,
  "date": "2026-11-22",
  "reason": "Correction"
}
```

CURRENT REQUEST:

```json
{
  "site_manager_id": 2,
  "amount": 310,
  "date": "2026-11-22",
  "reason": "Correction"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Cash advance updated successfully.",
  "data": {
    "id": 25,
    "site_manager": {
      "id": 2
    },
    "amount": "310.00"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Cash advance updated successfully.",
  "data": {
    "id": 25,
    "site_manager": {
      "id": 2
    },
    "amount": "310.00"
  }
}
```

PWA CHANGE:

- Scope cash-advance lists and selector to the active company.

Current HTTP outcomes — 200, 401, 404, 422, 500.

### DELETE /api/v1/cash-advances/{cashAdvance}

PREVIOUS REQUEST:

```json
{
  "reason": "Duplicate"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Duplicate"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Cash advance deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Cash advance deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- Scope cash-advance lists and selector to the active company.

Current HTTP outcomes — 200, 401, 404, 422.

### GET /api/v1/documents

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 30,
      "name": "Map"
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 30,
      "name": "Map"
    }
  ]
}
```

PWA CHANGE:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

Current HTTP outcomes — 200, 401, 422.

### POST /api/v1/documents

PREVIOUS REQUEST:

multipart/form-data — construction_site_id=4, optional site_manager_id=2, files[]

CURRENT REQUEST:

multipart/form-data — construction_site_id=4, optional site_manager_id=2, files[]

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Documents uploaded successfully.",
  "data": [
    {
      "id": 30,
      "name": "Map"
    }
  ]
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Documents uploaded successfully.",
  "data": [
    {
      "id": 30,
      "name": "Map"
    }
  ]
}
```

PWA CHANGE:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

Current HTTP outcomes — 200, 401, 403, 404, 422.

### PATCH /api/v1/documents/{document}

PREVIOUS REQUEST:

```json
{
  "construction_site_id": 4,
  "site_manager_id": null,
  "name": "Site map",
  "reason": "Correction"
}
```

CURRENT REQUEST:

```json
{
  "construction_site_id": 4,
  "site_manager_id": null,
  "name": "Site map",
  "reason": "Correction"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Document updated successfully.",
  "data": {
    "id": 30,
    "name": "Site map"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Document updated successfully.",
  "data": {
    "id": 30,
    "name": "Site map"
  }
}
```

PWA CHANGE:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

Current HTTP outcomes — 200, 401, 403, 404, 422.

### DELETE /api/v1/documents/{document}

PREVIOUS REQUEST:

```json
{
  "reason": "Obsolete"
}
```

CURRENT REQUEST:

```json
{
  "reason": "Obsolete"
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Document deleted successfully.",
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Document deleted successfully.",
  "data": null
}
```

PWA CHANGE:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

Current HTTP outcomes — 200, 401, 403, 404, 422.

### GET /api/v1/documents/{document}/download

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

PREVIOUS RESPONSE:

File stream with Content-Disposition.

CURRENT RESPONSE:

File stream with Content-Disposition.

PWA CHANGE:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

Current HTTP outcomes — 200, 401, 403, 404.

### GET /api/v1/workers/available

PREVIOUS REQUEST:

ENDPOINT DID NOT EXIST

CURRENT REQUEST:

NO BODY

Use one timing mode:

- No time parameters checks availability right now. Example `GET /api/v1/workers/available?role=worker`. This is a point query, so future assignments do not exclude a worker.
- `at` checks an explicit point. Example `GET /api/v1/workers/available?role=worker&at=2026-11-24T14%3A00%3A00%2B01%3A00`.
- `started_at` with optional `finished_at` checks the entire interval. When the finish is omitted, the interval ends at local midnight.

An `at` value combined with either interval parameter returns 422. A finish without a start also returns 422. `role` is optional and supports `worker`, `operator`, and `driver`. Without a role, all three eligible roles are considered. Encode `+` as `%2B` in raw URLs.

With 08:00–12:00 and 16:00–20:00 recorded, the worker is available at 14:00. A retroactive 10:00–14:00 write still conflicts with 08:00–12:00, regardless of server time. Attendance POST/PATCH remain authoritative and reject stale selections with 409.

PREVIOUS RESPONSE:

ENDPOINT DID NOT EXIST

CURRENT RESPONSE:

HTTP 200 with `success`, `message` and a `data` array of WorkerResource values. These responses were captured with worker 10 having an 08:00–12:00 attendance.

`GET /api/v1/workers/available?role=worker&started_at=2026-11-24T12%3A00%3A00%2B01%3A00&finished_at=2026-11-24T16%3A00%3A00%2B01%3A00`

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 10,
      "first_name": "Milan",
      "last_name": "Jović",
      "full_name": null,
      "username": "milan",
      "email": null,
      "phone": null,
      "role": "worker",
      "hourly_rate": "25.00",
      "is_active": true,
      "created_at": "2026-11-22T08:00:00.000000Z",
      "updated_at": "2026-11-22T08:00:00.000000Z",
      "is_available": true,
      "display_order": 0,
      "company": {
        "id": 1,
        "name": "Cole, Mertz and Keeling",
        "created_at": "2026-11-24T00:16:21.000000Z",
        "updated_at": "2026-11-24T00:16:21.000000Z"
      },
      "construction_sites": []
    }
  ]
}
```

`GET /api/v1/workers/available?role=worker&started_at=2026-11-24T11%3A30%3A00%2B01%3A00&finished_at=2026-11-24T16%3A00%3A00%2B01%3A00`

```json
{
  "success": true,
  "message": null,
  "data": []
}
```

PWA CHANGE:

- Call GET /api/v1/workers/available?role=worker for available now. Use at for an explicit point, or started_at and optional finished_at for a planned interval. Present server candidates directly; never subtract everyone with attendance that day. Availability reads are advisory. POST and PATCH validate the entered interval across all company sites and reject overlapping work even when both periods are in the past.

Current HTTP outcomes — 200, 401, 422.

### POST /api/v1/workers

PREVIOUS REQUEST:

```json
{
  "first_name": "Ana",
  "last_name": "Petrovic",
  "role": "worker",
  "username": "ana",
  "password": "example-password"
}
```

CURRENT REQUEST:

```json
{
  "first_name": "Ana",
  "last_name": "Petrovic",
  "role": "worker",
  "username": "ana",
  "password": "example-password",
  "display_order": 2
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Worker created successfully.",
  "data": {
    "id": 10,
    "first_name": "Ana"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Worker created successfully.",
  "data": {
    "id": 10,
    "first_name": "Ana",
    "display_order": 2
  }
}
```

PWA CHANGE:

- Parse Worker.display_order and preserve the server order in worker pickers. Send display_order only when the administrator sets it. Worker.display_order is a non-null integer, default 0. On create and update it is optional; send a JSON integer from 0 to 2147483647. Omitting it on update keeps the current value. Sending 0 removes manual priority. Duplicate values are allowed.

### PATCH /api/v1/workers/{worker}

PREVIOUS REQUEST:

```json
{
  "first_name": "Ana"
}
```

CURRENT REQUEST:

```json
{
  "display_order": 0
}
```

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": "Worker updated successfully.",
  "data": {
    "id": 10,
    "first_name": "Ana"
  }
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": "Worker updated successfully.",
  "data": {
    "id": 10,
    "first_name": "Ana",
    "display_order": 0
  }
}
```

PWA CHANGE:

- Parse Worker.display_order and preserve the server order in worker pickers. Send display_order only when the administrator sets it. Worker.display_order is a non-null integer, default 0. On create and update it is optional; send a JSON integer from 0 to 2147483647. Omitting it on update keeps the current value. Sending 0 removes manual priority. Duplicate values are allowed.

## Verification and final return

Exercise site switching, first and repeat selection, shared edits by a different manager, locked reads and writes, adjacent attendance, cross-site conflicts, open intervals and exact-finish boundaries, nullable pending machines, takeover reusing an ID, and company scoping. Report the commands and results. In the final response include:

- files modified
- files created
- interfaces/types changed
- stores/state changed
- API integrations changed
- cache changes
- UI behavior changes
- tests run
- build result
- unresolved backend/client mismatch.
