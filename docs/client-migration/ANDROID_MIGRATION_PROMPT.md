You are modifying the BuilderLog Android client to support the completed multi-manager backend API migration.

Use the attached `api-client-changes.json` as the authoritative client API change specification.

Inspect the existing Android code before making changes.

Do not guess class names or architecture.


## Implementation tasks


1. Inspect the networking/API client, DailyLog DTOs/entities and serializers, repositories/use cases/ViewModels, and any local database or offline cache before changes.
2. Locate every use of site_manager_id, site_manager and owner assumptions. Remove singular DailyLog output fields while retaining legitimate child attribution and legacy request/filter inputs.
3. Wire the existing site selector to POST /api/v1/construction-sites/{id}/select, accepting 200 and 201 and persisting data.id.
4. Model site_managers[] and nullable joined_at. Support several logs per manager/day and distinguish permanent site assignments.
5. Use DailyLog.id as the canonical key for active site state, child repositories, offline cache, and queued writes.
6. Update WorkerAttendance, its same-day conflict handling, and salary-related screens using confirmed periods and original attribution.
7. Update expenses, notes, delivery notes, subcontractor logs and attachments to use shared data and the correct request envelopes.
8. Accept pending machine assignments with nullable site_manager relation and local attribution ID. Update takeover to upsert the returned log ID and await server attendance.
9. Use global company machine availability and current server availability for operator/driver lists.
10. Allow viewing a locked DailyLog and disable create, update, delete, takeover and finish where relevant. Handle refreshed state and stale offline requests.
11. Handle 403, 404, 409, 422, and known legacy 500 by endpoint. Preserve authentication and unrelated behavior.
12. Review local database migrations and serializers if persisted. Keep queued mutations bound to their original DailyLog and clear invalid manager/date caches.
13. Add or update API parsing, repository, and ViewModel/UI tests where supported; run the existing suites and compile the Android application.


## Required API invariants

- Do not use `DailyLog.site_manager_id` or `DailyLog.site_manager` as output fields. Do not synthesize an owner from `site_managers[0]`. Use `DailyLog.site_managers[]` for participation display only, and `DailyLog.id` as the canonical shared log identifier.
- Manager A and Manager B on the same DailyLog see the same WorkerAttendance, Expenses, Notes, DeliveryNotes, SubcontractorLogs, Attachments, MachineAssignments, ExcavatorLogs and TruckLogs. Do not duplicate them or filter out records created by the other manager, except for routes with documented attribution semantics such as a manager’s expense history.
- Keep `site_manager_id` on children where the API exposes it. The `site_manager_id` query parameter on DailyLog filters membership. On MachineAssignment it filters attribution. The existing legacy DailyLog create body still validates its legacy fields. Prefer the select operation for current-day work.
- Pending machine self-assignment can have null stored `MachineAssignment.site_manager_id`. On wire the resource exposes nullable/conditional `site_manager`; it does not emit a scalar `site_manager_id`. Takeover attributes the actual manager; upsert by the returned log ID. Self-assignment alone creates no salary attendance. A manager-authoritative start drives salary synchronization.
- If `DailyLog.is_locked` is true, allow authorized viewing and disable create, update, delete, takeover and finish where applicable. Keep the backend authoritative. An administrator can still use the dedicated unlock operation if permitted. Selection can return an already locked log.
- Handle both 200 and 201 from select. Handle 403 permission failure, 404 for foreign/missing/invisible or wrong-parent records, 409 for known conflicts, and 422 for validation or required attribution. Existing lock, duplicate and overlap cases can use 500; after a generic 500, refresh authoritative state before deciding whether a business condition caused it.
- Keep unrelated behavior, current architecture, and API fields as implemented. Do not change the backend, invent compatibility fields, or perform unrelated refactors. Do not invent financial attribution from participants. The client must compile and the existing tests must continue to pass.



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

ANDROID CHANGE:

- Call after site selection. Persist data.id as the active DailyLog ID and use it for child requests.

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

ANDROID CHANGE:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

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

ANDROID CHANGE:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

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

ANDROID CHANGE:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

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

ANDROID CHANGE:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

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

ANDROID CHANGE:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

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

ANDROID CHANGE:

- Read using the active DailyLog ID. Display records from all members on the daily list.

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

ANDROID CHANGE:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

Current HTTP outcomes — 200, 401, 403, 404, 409, 422, 500.

Manual creation accepts an existing same-company worker ID. The unique company/worker/date rule applies to ordinary WORKER manual attendance. Machine flows generate attendance on managerial confirmation; do not send an additional manual POST for a machine period.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

Current HTTP outcomes — 200, 401, 403, 404, 422, 500.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

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

ANDROID CHANGE:

- Read using the active DailyLog ID. Display records from all members on the daily list.

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

ANDROID CHANGE:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

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

ANDROID CHANGE:

- Read using the active DailyLog ID. Display records from all members on the daily list.

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

ANDROID CHANGE:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

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

ANDROID CHANGE:

- Read using the active DailyLog ID. Display records from all members on the daily list.

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

ANDROID CHANGE:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

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

ANDROID CHANGE:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

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

ANDROID CHANGE:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

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

ANDROID CHANGE:

- Read using the active DailyLog ID. Display records from all members on the daily list.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Authorize against the actual attachment owner. Keep the existing upload form or reason. Handle 403 and 404 before treating data as file bytes.

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

ANDROID CHANGE:

- Authorize against the actual attachment owner. Keep the existing upload form or reason. Handle 403 and 404 before treating data as file bytes.

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

ANDROID CHANGE:

- Authorize against the actual attachment owner. Keep the existing upload form or reason. Handle 403 and 404 before treating data as file bytes.

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

ANDROID CHANGE:

- Authorize against the actual attachment owner. Keep the existing upload form or reason. Handle 403 and 404 before treating data as file bytes.

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

ANDROID CHANGE:

- Show a pending reservation with nullable attribution. Expect no salary attendance yet.

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

ANDROID CHANGE:

- Send managerial times and canonical DailyLog ID. Upsert the returned log by ID; takeover can reuse the self-assigned ID.

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

ANDROID CHANGE:

- Operator/driver PATCH may include operator fields and machine readings; omit managerial times, note_site_manager and advance_payment.

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

ANDROID CHANGE:

- Delete by the log ID with reason and remove it from shared state.

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

ANDROID CHANGE:

- Use available as the company-wide result, regardless of which assignments you can read.

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

ANDROID CHANGE:

- Parse a nullable site_manager relation. Display assignments from both members only when visible.

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

ANDROID CHANGE:

- Show a pending reservation with nullable attribution. Expect no salary attendance yet.

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

ANDROID CHANGE:

- Send managerial times and canonical DailyLog ID. Upsert the returned log by ID; takeover can reuse the self-assigned ID.

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

ANDROID CHANGE:

- Operator/driver PATCH may include operator fields and machine readings; omit managerial times, note_site_manager and advance_payment.

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

ANDROID CHANGE:

- Delete by the log ID with reason and remove it from shared state.

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

ANDROID CHANGE:

- Use available as the company-wide result, regardless of which assignments you can read.

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

ANDROID CHANGE:

- Parse a nullable site_manager relation. Display assignments from both members only when visible.

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

ANDROID CHANGE:

- Make site_manager nullable. Do not filter the shared list to the current manager’s attribution.

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

ANDROID CHANGE:

- Make site_manager nullable. Do not filter the shared list to the current manager’s attribution.

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

ANDROID CHANGE:

- Use can_select and invoke /select on click.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Replace cached results with the response scoped to the active company.

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

ANDROID CHANGE:

- Call this from administrator site management only. Use same-company IDs and no body.

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

ANDROID CHANGE:

- Call this from administrator site management only. Use same-company IDs and no body.

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

ANDROID CHANGE:

- Use task/site IDs from the current tenant only and handle 404.

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

ANDROID CHANGE:

- Use task/site IDs from the current tenant only and handle 404.

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

ANDROID CHANGE:

- Use task/site IDs from the current tenant only and handle 404.

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

ANDROID CHANGE:

- Load and select targets from the current company; show 404 if a cached target is no longer valid.

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

ANDROID CHANGE:

- Load and select targets from the current company; show 404 if a cached target is no longer valid.

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

ANDROID CHANGE:

- Load and select targets from the current company; show 404 if a cached target is no longer valid.

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

ANDROID CHANGE:

- Load and select targets from the current company; show 404 if a cached target is no longer valid.

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

ANDROID CHANGE:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

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

ANDROID CHANGE:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

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

ANDROID CHANGE:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

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

ANDROID CHANGE:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

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

ANDROID CHANGE:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

Current HTTP outcomes — 200, 401, 403, 404.

## Verification and final return

Exercise site switching, first and repeat selection, shared edits by a different manager, locked reads and writes, the ordinary duplicate rule, nullable pending machines, takeover reusing an ID, and company scoping. Report the commands and results. In the final response include:

- files modified
- files created
- API models changed
- local DB migrations
- endpoint integrations changed
- deprecated assumptions removed
- tests run
- build result
- unresolved backend/client mismatch.
