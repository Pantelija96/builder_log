# BuilderLog API client migration — Multi-manager (M1–M4)

Baseline: pre-multi-manager backend at `443f69a`. Use the same authenticated API prefix. JSON examples are projections of actual payloads and omit unchanged fields where useful. Query parameters are separate from request bodies. Unless specified otherwise, existing pagination, filtering, multipart upload and response envelopes remain in place. **Day** means the server’s day. DailyLog.date keeps its existing ISO timestamp format. Midnight in the server time zone can serialize as the previous UTC date. Do not infer the active ID or day from the UTC date substring.

## Select an active DailyLog

1. Load `GET /api/v1/construction-sites` or the permanent assignment list.
2. On selection, call `POST /api/v1/construction-sites/{constructionSite}/select`.
3. Store `data.id`, `data.is_locked` and the member list in active state. Use that ID in every nested path.
4. Accept both 200 and 201. Two managers at one site on the same date share an ID. One manager can use several sites and IDs on one day.
5. Drop queries such as “first log for my manager ID today”. Refresh shared records after a second manager edits them. Treat a 409 from selection as a deleted site/day conflict, separate from duplicate attendance.

## Participation, attribution and creation

- `DailyLog.site_managers[]` records participants and the time they joined. `joined_at` for the historical backfill is based on original log creation and may be null.
- The DailyLog query parameter `site_manager_id` filters **membership**. The same-named parameter on an expense or machine assignment filters **attribution**.
- `Expense.site_manager_id` and `WorkerAttendance.site_manager_id` identify attribution. `MachineAssignment.site_manager_id` identifies the manager who assigned/took over the machine. On the assignment JSON, read the nullable `site_manager` relation. A self-assignment has no manager until managerial action and produces no immediate salary entry.
- Delivery notes, notes and subcontractor logs keep their own attribution where exposed. Original `created_by` is retained and later actors appear in audit.
- The site’s `site_managers[]` lists permanent assignments when included. It is separate from daily participation.
- The existing standalone `POST /api/v1/expenses` is the administrator path for an expense without managerial attribution. Send `construction_site_id`, `title`, `amount` and `date`. It continues to return null `daily_log_id` and `site_manager_id`. A daily child create that requires a manager returns 422 for an administrator.

## Error handling

Show access denied for 403. Clear stale active IDs or hide unavailable records on 404. Treat 409 as a conflict and 422 as validation or missing attribution for the current actor. Some verified business failures still use 500, including locked mutations, a duplicate ordinary attendance in the same log, and conflicting machine periods. Refresh the shared log and show the server message for those cases. Treat other 500 responses as server errors.


## Endpoint inventory

79 method/path pairs. New 1, removed 0, changed existing request schemas 0, changed existing response schemas 13, authorization-only 48, behavior-only 17. Two distinct DailyLog fields were removed. A field removed from a nested representation is counted once per affected endpoint, and once as a distinct field. Classifications use the primary change; an endpoint with a shape change can also have an access change.

### POST /api/v1/construction-sites/{constructionSite}/select

CHANGE TYPE:

NEW

PREVIOUS REQUEST:

Endpoint did not exist.

CURRENT REQUEST:

```json
{}
```

REQUEST CHANGE:

New operation. Send an empty JSON object or omit the body.

PREVIOUS RESPONSE:

NO RESPONSE — endpoint did not exist.

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
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ]
  }
}
```

RESPONSE CHANGE:

Canonical DailyLog in the success/message/data envelope.

STATUS CODES BEFORE:

404 — endpoint did not exist.

STATUS CODES NOW:

201 on creation; 200 on join or repeat selection; 401 no session; 403 not an active, permanently assigned SITE_MANAGER; 404 foreign/missing site; 409 a soft-deleted log occupies this site/date.

ANDROID CHANGES:

- Call after site selection. Persist data.id as the active DailyLog ID and use it for child requests.

PWA CHANGES:

- Call from the site selector and invalidate queries by returned data.id.

NOTES:

- The same site/date returns the same ID for all members. A repeat preserves joined_at. Selection can return an existing locked log; do not create a second log.

### GET /api/v1/daily-logs

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
          "joined_at": "2026-11-22T07:00:00.000000Z"
        },
        {
          "id": 7,
          "joined_at": "2026-11-22T07:30:00.000000Z"
        }
      ]
    }
  ]
}
```

RESPONSE CHANGE:

Removed DailyLog.site_manager_id and DailyLog.site_manager; added site_managers[] with joined_at.

STATUS CODES BEFORE:

200; 401; 404 for missing ID; 422 invalid filters.

STATUS CODES NOW:

200; 401; 422 invalid filters.

ANDROID CHANGES:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

PWA CHANGES:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

NOTES:

- site_manager_id remains a query parameter and matches membership. A site manager sees the intersection with their own memberships.

### GET /api/v1/daily-logs/{dailyLog}

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ]
  }
}
```

RESPONSE CHANGE:

Removed DailyLog.site_manager_id and DailyLog.site_manager; added site_managers[] with joined_at.

STATUS CODES BEFORE:

200; 401; 404 for missing ID; 422 invalid filters.

STATUS CODES NOW:

200; 401; 404 missing, foreign, or invisible DailyLog.

ANDROID CHANGES:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

PWA CHANGES:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

NOTES:

- Use the canonical returned ID; do not reconstruct the ID from manager and date.

### POST /api/v1/daily-logs

CHANGE TYPE:

CHANGED

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

REQUEST CHANGE:

NONE

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
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ]
  }
}
```

RESPONSE CHANGE:

Removed DailyLog.site_manager_id and DailyLog.site_manager; added site_managers[] with joined_at.

STATUS CODES BEFORE:

201; 401; 422 invalid body; 500 duplicate or unassigned site.

STATUS CODES NOW:

201; 401; 403 role/inactive or not assigned on today selection; 404 foreign/missing site; 409 soft-deleted conflict today; 422 invalid body; 500 duplicate historical log, existing log with uploads, or historical site not assigned.

ANDROID CHANGES:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

PWA CHANGES:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

NOTES:

- Compatibility endpoint. company_id and site_manager_id are still validated, but the actor and site determine the actual tenant and participant. For today, a compatible request can join the existing log. Use /select for current-day clients.

### POST /api/v1/daily-logs/{dailyLog}/lock

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 55,
    "construction_site_id": 4,
    "date": "2026-11-21T23:00:00.000000Z",
    "is_locked": true,
    "site_managers": [
      {
        "id": 2,
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ]
  }
}
```

RESPONSE CHANGE:

Removed DailyLog.site_manager_id and DailyLog.site_manager; added site_managers[] with joined_at.

STATUS CODES BEFORE:

200; 401; 404 missing ID; 422 validation; 500 legacy business rule.

STATUS CODES NOW:

200; 401; 404 missing or foreign log; 422 invalid body where applicable; 500 unauthorized or invalid lock state.

ANDROID CHANGES:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

PWA CHANGES:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

NOTES:

- Lock is shared. A member or same-company administrator can lock. Unlock is restricted to a same-company administrator and requires reason.

### POST /api/v1/daily-logs/{dailyLog}/unlock

CHANGE TYPE:

CHANGED

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

REQUEST CHANGE:

NONE

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
        "joined_at": "2026-11-22T07:00:00.000000Z"
      },
      {
        "id": 7,
        "joined_at": "2026-11-22T07:30:00.000000Z"
      }
    ]
  }
}
```

RESPONSE CHANGE:

Removed DailyLog.site_manager_id and DailyLog.site_manager; added site_managers[] with joined_at.

STATUS CODES BEFORE:

200; 401; 404 missing ID; 422 validation; 500 legacy business rule.

STATUS CODES NOW:

200; 401; 404 missing or foreign log; 422 invalid body where applicable; 500 unauthorized or invalid lock state.

ANDROID CHANGES:

- Update the DailyLog DTO and persisted cache. Parse a collection of members and key this record by its server ID.

PWA CHANGES:

- Update the DailyLog interface. Use data.id in state, routes, and cache keys.

NOTES:

- Lock is shared. A member or same-company administrator can lock. Unlock is restricted to a same-company administrator and requires reason.

### GET /api/v1/daily-logs/{dailyLog}/worker-attendances

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 403 nonmember; 404 foreign/missing parent; 422 invalid filters.

ANDROID CHANGES:

- Read using the active DailyLog ID. Display records from all members on the daily list.

PWA CHANGES:

- Key this list by DailyLog ID. Invalidate after edits by either member.

NOTES:

- Membership grants access. The site_manager_id on the child remains attribution.

### POST /api/v1/daily-logs/{dailyLog}/worker-attendances

CHANGE TYPE:

BEHAVIOR CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation or administrator without explicit attribution; 500 locked daily log. 409 duplicate worker/day in another log; 500 duplicate in the same log.

ANDROID CHANGES:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

PWA CHANGES:

- Use the canonical dailyLogId and invalidate that shared list after creation.

NOTES:

- Manual attendance is for an ordinary WORKER. OPERATOR and DRIVER attendance comes from confirmed machine periods.

### PATCH /api/v1/daily-logs/{dailyLog}/worker-attendances/{workerAttendance}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### DELETE /api/v1/daily-logs/{dailyLog}/worker-attendances/{workerAttendance}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### GET /api/v1/daily-logs/{dailyLog}/expenses

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 403 nonmember; 404 foreign/missing parent; 422 invalid filters.

ANDROID CHANGES:

- Read using the active DailyLog ID. Display records from all members on the daily list.

PWA CHANGES:

- Key this list by DailyLog ID. Invalidate after edits by either member.

NOTES:

- The daily expenses are shared. summary still describes the requesting manager’s attributed totals.

### POST /api/v1/daily-logs/{dailyLog}/expenses

CHANGE TYPE:

BEHAVIOR CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 91,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "title": "Fuel",
    "amount": "125.00"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation or administrator without explicit attribution; 500 locked daily log.

ANDROID CHANGES:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

PWA CHANGES:

- Use the canonical dailyLogId and invalidate that shared list after creation.

NOTES:

- The authenticated site manager supplies attribution. No new manager field is accepted in the body.

### PATCH /api/v1/daily-logs/{dailyLog}/expenses/{expense}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 91,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "title": "Diesel",
    "amount": "125.00"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### DELETE /api/v1/daily-logs/{dailyLog}/expenses/{expense}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### GET /api/v1/daily-logs/{dailyLog}/notes

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
            "joined_at": "2026-11-22T07:00:00.000000Z"
          },
          {
            "id": 7,
            "joined_at": "2026-11-22T07:30:00.000000Z"
          }
        ]
      }
    }
  ]
}
```

RESPONSE CHANGE:

Nested daily_log uses site_managers[]; any singular DailyLog owner fields are removed. Child attribution keys are retained.

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 403 nonmember; 404 foreign/missing parent; 422 invalid filters.

ANDROID CHANGES:

- Read using the active DailyLog ID. Display records from all members on the daily list.

PWA CHANGES:

- Key this list by DailyLog ID. Invalidate after edits by either member.

NOTES:

- Membership grants access. The site_manager_id on the child remains attribution.

### POST /api/v1/daily-logs/{dailyLog}/notes

CHANGE TYPE:

BEHAVIOR CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation or administrator without explicit attribution; 500 locked daily log.

ANDROID CHANGES:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

PWA CHANGES:

- Use the canonical dailyLogId and invalidate that shared list after creation.

NOTES:

- The authenticated site manager supplies attribution. No new manager field is accepted in the body.

### PATCH /api/v1/daily-logs/{dailyLog}/notes/{note}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### DELETE /api/v1/daily-logs/{dailyLog}/notes/{note}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### GET /api/v1/daily-logs/{dailyLog}/delivery-notes

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 403 nonmember; 404 foreign/missing parent; 422 invalid filters.

ANDROID CHANGES:

- Read using the active DailyLog ID. Display records from all members on the daily list.

PWA CHANGES:

- Key this list by DailyLog ID. Invalidate after edits by either member.

NOTES:

- Membership grants access. The site_manager_id on the child remains attribution.

### POST /api/v1/daily-logs/{dailyLog}/delivery-notes

CHANGE TYPE:

BEHAVIOR CHANGE

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

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation or administrator without explicit attribution; 500 locked daily log.

ANDROID CHANGES:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

PWA CHANGES:

- Use the canonical dailyLogId and invalidate that shared list after creation.

NOTES:

- The authenticated site manager supplies attribution. No new manager field is accepted in the body.

### PATCH /api/v1/daily-logs/{dailyLog}/delivery-notes/{deliveryNote}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 93,
    "name": "Concrete delivery",
    "description": "Pallet 2"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### DELETE /api/v1/daily-logs/{dailyLog}/delivery-notes/{deliveryNote}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### POST /api/v1/daily-logs/{dailyLog}/subcontractors

CHANGE TYPE:

BEHAVIOR CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 94,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "subcontractor_id": 3,
    "worker_count": 2
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation or administrator without explicit attribution; 500 locked daily log.

ANDROID CHANGES:

- Create under the active DailyLog ID. Handle blocked writes without creating a second log.

PWA CHANGES:

- Use the canonical dailyLogId and invalidate that shared list after creation.

NOTES:

- The authenticated site manager supplies attribution. No new manager field is accepted in the body.

### PATCH /api/v1/daily-logs/{dailyLog}/subcontractors/{subcontractorLog}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 94,
    "daily_log_id": 55,
    "site_manager_id": 2,
    "subcontractor_id": 3,
    "worker_count": 3
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### DELETE /api/v1/daily-logs/{dailyLog}/subcontractors/{subcontractorLog}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Use the child’s existing ID under the canonical DailyLog; preserve reason where required.

PWA CHANGES:

- Update or remove that shared child by ID and refresh its daily list.

NOTES:

- A second member can update or delete. Original attribution and created_by are retained. A parent/child mismatch returns 404.

### GET /api/v1/daily-logs/{dailyLog}/expenses/history

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
              "joined_at": "2026-11-22T07:00:00.000000Z"
            },
            {
              "id": 7,
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

RESPONSE CHANGE:

Nested daily_log uses site_managers[]; any singular DailyLog owner fields are removed. Child attribution keys are retained.

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 403 nonmember; 404 foreign/missing parent; 422 invalid filters.

ANDROID CHANGES:

- Read using the active DailyLog ID. Display records from all members on the daily list.

PWA CHANGES:

- Key this list by DailyLog ID. Invalidate after edits by either member.

NOTES:

- This is attribution history for the manager and site; it is separate from the shared daily list. Keep expenses, summary and cash_advances.

### GET /api/v1/expenses

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
            "joined_at": "2026-11-22T07:00:00.000000Z"
          },
          {
            "id": 7,
            "joined_at": "2026-11-22T07:30:00.000000Z"
          }
        ]
      }
    }
  ]
}
```

RESPONSE CHANGE:

Nested daily_log contains site_managers[] with joined_at when present and no singular DailyLog owner. Other shapes remain.

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Existing financial and child attribution fields are preserved.

### GET /api/v1/admin/expenses

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
    "total": 125
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
              "joined_at": "2026-11-22T07:00:00.000000Z"
            },
            {
              "id": 7,
              "joined_at": "2026-11-22T07:30:00.000000Z"
            }
          ]
        }
      }
    ],
    "total": 125
  }
}
```

RESPONSE CHANGE:

Nested daily_log contains site_managers[] with joined_at when present and no singular DailyLog owner. Other shapes remain.

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Total covers matching expenses before paging. Uses attributed rows and remains scoped by company.

### GET /api/v1/notes

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
            "joined_at": "2026-11-22T07:00:00.000000Z"
          },
          {
            "id": 7,
            "joined_at": "2026-11-22T07:30:00.000000Z"
          }
        ]
      }
    }
  ]
}
```

RESPONSE CHANGE:

Nested daily_log contains site_managers[] with joined_at when present and no singular DailyLog owner. Other shapes remain.

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Existing financial and child attribution fields are preserved.

### GET /api/v1/delivery-notes

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
            "joined_at": "2026-11-22T07:00:00.000000Z"
          },
          {
            "id": 7,
            "joined_at": "2026-11-22T07:30:00.000000Z"
          }
        ]
      }
    }
  ]
}
```

RESPONSE CHANGE:

Nested daily_log contains site_managers[] with joined_at when present and no singular DailyLog owner. Other shapes remain.

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Global delivery note list may include a nested daily_log. Parse its plural membership.

### GET /api/v1/subcontractor-logs

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Existing financial and child attribution fields are preserved.

### GET /api/v1/worker-attendances

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Company and membership constrain the list. Confirmed machine periods can appear more than once per worker/day.

### GET /api/v1/subcontractors

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Manager filtering by subcontractor work requires visibility of the corresponding daily log.

### POST /api/v1/daily-logs/{dailyLog}/attachments

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

multipart/form-data — attachments[] (files)

CURRENT REQUEST:

multipart/form-data — attachments[] (files)

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200 for upload/download or 204 for delete; 401; 404 missing; 422 invalid upload/delete where applicable.

STATUS CODES NOW:

200; 401; 403 denied; 404 foreign/missing; 422 validation; 500 locked log.

ANDROID CHANGES:

- Authorize against the actual attachment owner. Keep the existing upload form or reason. Handle 403 and 404 before treating data as file bytes.

PWA CHANGES:

- Authenticate uploads and downloads. Only construct a Blob for a successful download and handle empty 204 on delete.

NOTES:

- For a daily attachment or an expense attached to a daily log, access follows that parent. A standalone expense retains its own access rules.

### POST /api/v1/expenses/{expense}/attachments

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

multipart/form-data — attachments[] (files)

CURRENT REQUEST:

multipart/form-data — attachments[] (files)

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200 for upload/download or 204 for delete; 401; 404 missing; 422 invalid upload/delete where applicable.

STATUS CODES NOW:

200; 401; 403 denied; 404 foreign/missing; 422 validation; 500 locked log.

ANDROID CHANGES:

- Authorize against the actual attachment owner. Keep the existing upload form or reason. Handle 403 and 404 before treating data as file bytes.

PWA CHANGES:

- Authenticate uploads and downloads. Only construct a Blob for a successful download and handle empty 204 on delete.

NOTES:

- For a daily attachment or an expense attached to a daily log, access follows that parent. A standalone expense retains its own access rules.

### GET /api/v1/attachments/{attachment}

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

File stream with Content-Disposition.

CURRENT RESPONSE:

File stream with Content-Disposition.

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200 for upload/download or 204 for delete; 401; 404 missing; 422 invalid upload/delete where applicable.

STATUS CODES NOW:

200; 401; 403 denied; 404 foreign, missing, or missing contents.

ANDROID CHANGES:

- Authorize against the actual attachment owner. Keep the existing upload form or reason. Handle 403 and 404 before treating data as file bytes.

PWA CHANGES:

- Authenticate uploads and downloads. Only construct a Blob for a successful download and handle empty 204 on delete.

NOTES:

- For a daily attachment or an expense attached to a daily log, access follows that parent. A standalone expense retains its own access rules.

### DELETE /api/v1/attachments/{attachment}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

NO BODY — 204

CURRENT RESPONSE:

NO BODY — 204

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200 for upload/download or 204 for delete; 401; 404 missing; 422 invalid upload/delete where applicable.

STATUS CODES NOW:

204; 401; 403 denied; 404 foreign or missing; 422 validation; 500 locked log.

ANDROID CHANGES:

- Authorize against the actual attachment owner. Keep the existing upload form or reason. Handle 403 and 404 before treating data as file bytes.

PWA CHANGES:

- Authenticate uploads and downloads. Only construct a Blob for a successful download and handle empty 204 on delete.

NOTES:

- For a daily attachment or an expense attached to a daily log, access follows that parent. A standalone expense retains its own access rules.

### POST /api/v1/excavator-logs/operator

CHANGE TYPE:

BEHAVIOR CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 61,
    "site_manager_started_at": null,
    "site_manager_finished_at": null
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 wrong role; 404 foreign/missing site or machine; 422 validation; 500 locked or conflicting reservation.

ANDROID CHANGES:

- Show a pending reservation with nullable attribution. Expect no salary attendance yet.

PWA CHANGES:

- Mark the assignment pending and invalidate available/current/occupied.

NOTES:

- Self-assignment works before the daily log exists. An existing locked daily log blocks creation.

### POST /api/v1/excavator-logs/daily-logs/{dailyLog}

CHANGE TYPE:

BEHAVIOR CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log. 422 also returned for administrator without manager attribution; 500 also returned for overlaps.

ANDROID CHANGES:

- Send managerial times and canonical DailyLog ID. Upsert the returned log by ID; takeover can reuse the self-assigned ID.

PWA CHANGES:

- Refresh the shared assignment and salary attendance after takeover.

NOTES:

- The manager who takes over is attributed. An authoritative start triggers salary attendance. Pending reservations and overlaps may block the request.

### PATCH /api/v1/excavator-logs/{excavatorLog}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log. 500 also returned for overlapping periods.

ANDROID CHANGES:

- Operator/driver PATCH may include operator fields and machine readings; omit managerial times, note_site_manager and advance_payment.

PWA CHANGES:

- Use separate forms for manager and self updates; disable mutation while locked.

NOTES:

- The original creator remains. Keep excavator and truck validation and null handling distinct.

### DELETE /api/v1/excavator-logs/{excavatorLog}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Delete by the log ID with reason and remove it from shared state.

PWA CHANGES:

- After success invalidate the log, assignment and worker/machine availability queries.

NOTES:

- The assignment and linked attendance are soft-deleted with the log.

### GET /api/v1/excavator-logs/available

CHANGE TYPE:

BEHAVIOR CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": []
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": []
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401.

STATUS CODES NOW:

200; 401.

ANDROID CHANGES:

- Use available as the company-wide result, regardless of which assignments you can read.

PWA CHANGES:

- Refresh available after a member modifies any active period.

NOTES:

- In this example the machine is unavailable. A missing start/end or start <= now < finish is occupied. An empty occupied list for this caller does not mean free.

### GET /api/v1/excavator-logs/occupied

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401.

STATUS CODES NOW:

200; 401.

ANDROID CHANGES:

- Parse a nullable site_manager relation. Display assignments from both members only when visible.

PWA CHANGES:

- Keep occupied visibility separate from global availability.

NOTES:

- Managers see their participating logs, administrator sees the company, and operators/drivers see their own assignments.

### POST /api/v1/truck-logs/driver

CHANGE TYPE:

BEHAVIOR CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 61,
    "site_manager_started_at": null,
    "site_manager_finished_at": null
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 wrong role; 404 foreign/missing site or machine; 422 validation; 500 locked or conflicting reservation.

ANDROID CHANGES:

- Show a pending reservation with nullable attribution. Expect no salary attendance yet.

PWA CHANGES:

- Mark the assignment pending and invalidate available/current/occupied.

NOTES:

- Self-assignment works before the daily log exists. An existing locked daily log blocks creation.

### POST /api/v1/truck-logs/daily-logs/{dailyLog}

CHANGE TYPE:

BEHAVIOR CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log. 422 also returned for administrator without manager attribution; 500 also returned for overlaps.

ANDROID CHANGES:

- Send managerial times and canonical DailyLog ID. Upsert the returned log by ID; takeover can reuse the self-assigned ID.

PWA CHANGES:

- Refresh the shared assignment and salary attendance after takeover.

NOTES:

- The manager who takes over is attributed. An authoritative start triggers salary attendance. Pending reservations and overlaps may block the request.

### PATCH /api/v1/truck-logs/{truckLog}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 61,
    "site_manager_started_at": "2026-11-22T07:00:00.000000Z",
    "site_manager_finished_at": "2026-11-22T09:00:00.000000Z"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log. 500 also returned for overlapping periods.

ANDROID CHANGES:

- Operator/driver PATCH may include operator fields and machine readings; omit managerial times, note_site_manager and advance_payment.

PWA CHANGES:

- Use separate forms for manager and self updates; disable mutation while locked.

NOTES:

- The original creator remains. Keep excavator and truck validation and null handling distinct.

### DELETE /api/v1/truck-logs/{truckLog}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 insufficient access; 404 foreign, missing, or wrong-parent resource; 422 validation; 500 locked daily log.

ANDROID CHANGES:

- Delete by the log ID with reason and remove it from shared state.

PWA CHANGES:

- After success invalidate the log, assignment and worker/machine availability queries.

NOTES:

- The assignment and linked attendance are soft-deleted with the log.

### GET /api/v1/truck-logs/available

CHANGE TYPE:

BEHAVIOR CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": []
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": []
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401.

STATUS CODES NOW:

200; 401.

ANDROID CHANGES:

- Use available as the company-wide result, regardless of which assignments you can read.

PWA CHANGES:

- Refresh available after a member modifies any active period.

NOTES:

- In this example the machine is unavailable. A missing start/end or start <= now < finish is occupied. An empty occupied list for this caller does not mean free.

### GET /api/v1/truck-logs/occupied

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401.

STATUS CODES NOW:

200; 401.

ANDROID CHANGES:

- Parse a nullable site_manager relation. Display assignments from both members only when visible.

PWA CHANGES:

- Keep occupied visibility separate from global availability.

NOTES:

- Managers see their participating logs, administrator sees the company, and operators/drivers see their own assignments.

### GET /api/v1/machine-assignments

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Make site_manager nullable. Do not filter the shared list to the current manager’s attribution.

PWA CHANGES:

- Refresh by site/date and active DailyLog, and keep attribution separate from access.

NOTES:

- The site_manager_id query filter on MachineAssignment still means attribution. It differs from the DailyLog membership filter.

### GET /api/v1/machine-assignments/current

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 33,
    "site_manager": null
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 404 for a foreign worker_id; 200 with data:null if no visible current assignment.

ANDROID CHANGES:

- Make site_manager nullable. Do not filter the shared list to the current manager’s attribution.

PWA CHANGES:

- Refresh by site/date and active DailyLog, and keep attribution separate from access.

NOTES:

- The site_manager_id query filter on MachineAssignment still means attribution. It differs from the DailyLog membership filter.

### GET /api/v1/construction-sites

CHANGE TYPE:

BEHAVIOR CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Use can_select and invoke /select on click.

PWA CHANGES:

- Connect can_select to the /select action.

NOTES:

- For SITE_MANAGER, can_select represents active permanent assignment. Another manager having opened today’s log does not block selection.

### GET /api/v1/workers

CHANGE TYPE:

BEHAVIOR CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Worker lists are scoped to the company. Operator and driver is_available reflects current machine assignments and attendance.

### GET /api/v1/workers/{worker}

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable. 404 if the route ID is missing.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 404 for missing or foreign route ID.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Existing financial and child attribution fields are preserved.

### GET /api/v1/workers/{worker}/work-history

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable. 404 if the route ID is missing.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 404 for missing or foreign route ID.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- A manager sees machine history only on participating daily logs. The response shape remains worker/type/history.

### GET /api/v1/site-managers/{siteManager}/assigned-sites

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable. 404 if the route ID is missing.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 404 for missing or foreign route ID.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Existing financial and child attribution fields are preserved.

### GET /api/v1/site-managers/{siteManager}/overall

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "cash_advance_total": 300,
      "construction_site": {
        "id": 4,
        "name": "North site",
        "can_select": true
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
      "cash_advance_total": 300,
      "construction_site": {
        "id": 4,
        "name": "North site",
        "can_select": true
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
                "joined_at": "2026-11-22T07:00:00.000000Z"
              },
              {
                "id": 7,
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

RESPONSE CHANGE:

Nested daily_log contains site_managers[] with joined_at when present and no singular DailyLog owner. Other shapes remain.

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable. 404 if the route ID is missing.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 404 for missing or foreign route ID.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Expense and attendance belong to the stored child attribution; shared membership grants viewing and never multiplies rows.

### GET /api/v1/construction-sites/{constructionSite}/financial-summary

CHANGE TYPE:

CHANGED

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
              "joined_at": "2026-11-22T07:00:00.000000Z"
            },
            {
              "id": 7,
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

RESPONSE CHANGE:

Nested daily_log contains site_managers[] with joined_at when present and no singular DailyLog owner. Other shapes remain.

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable. 404 if the route ID is missing.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 404 for missing or foreign route ID.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Existing financial and child attribution fields are preserved.

### GET /api/v1/construction-sites/{constructionSite}/statistics

CHANGE TYPE:

BEHAVIOR CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable. 404 if the route ID is missing.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 404 for missing or foreign route ID.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Site totals count each child once and are scoped by company. total_machine_hours still comes from excavator work_hours.

### GET /api/v1/financial-summary

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- One summary per manager of the caller’s company, calculated from attributed cash advances and expenses.

### GET /api/v1/admin/construction-financial-summary

CHANGE TYPE:

BEHAVIOR CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Company summary and site/manager/date groups count expenses and attendance once. Use date_from/date_to.

### GET /api/v1/construction-sites/{constructionSite}/site-managers

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable. 404 if the route ID is missing.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 404 for missing or foreign route ID.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Existing financial and child attribution fields are preserved.

### GET /api/v1/tasks

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Existing financial and child attribution fields are preserved.

### GET /api/v1/admin/worker-salaries

CHANGE TYPE:

BEHAVIOR CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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
          "worked_hours": 4
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
          "worked_hours": 4
        }
      }
    ]
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 403 for non-admin.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Compensation fields and formulas are unchanged. Reports include confirmed machine periods and preserve ordinary-worker daily uniqueness.

### GET /api/v1/workers/{worker}/salary

CHANGE TYPE:

BEHAVIOR CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "success": true,
  "message": null,
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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable. 404 if the route ID is missing.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable. 404 for missing or foreign route ID.

ANDROID CHANGES:

- Replace cached results with the response scoped to the active company.

PWA CHANGES:

- Key report/list requests by active tenant and reset the view when the tenant changes.

NOTES:

- Compensation fields and formulas are unchanged. Reports include confirmed machine periods and preserve ordinary-worker daily uniqueness.

### POST /api/v1/construction-sites/{constructionSite}/site-managers/{worker}

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 missing; 500 legacy assignment conflict.

STATUS CODES NOW:

200; 401; 403 non-admin; 404 missing or foreign worker/site; 422 not an active SITE_MANAGER; 500 legacy assignment conflict.

ANDROID CHANGES:

- Call this from administrator site management only. Use same-company IDs and no body.

PWA CHANGES:

- Use same-company administrator controls for permanent assignment. For delete consume an empty 204.

NOTES:

- Permanent assignment is separate from participation in a DailyLog. Existing rule for removing an assignment when today’s log exists remains.

### DELETE /api/v1/construction-sites/{constructionSite}/site-managers/{worker}

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

NO BODY — 204

CURRENT RESPONSE:

NO BODY — 204

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

204; 401; 404 missing; 500 legacy assignment conflict.

STATUS CODES NOW:

204; 401; 403 non-admin; 404 missing or foreign worker/site; 500 legacy assignment conflict.

ANDROID CHANGES:

- Call this from administrator site management only. Use same-company IDs and no body.

PWA CHANGES:

- Use same-company administrator controls for permanent assignment. For delete consume an empty 204.

NOTES:

- Permanent assignment is separate from participation in a DailyLog. Existing rule for removing an assignment when today’s log exists remains.

### POST /api/v1/tasks

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 80,
    "title": "Inspect site"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 404 foreign task or target site; 422 validation; existing legacy business errors remain.

ANDROID CHANGES:

- Use task/site IDs from the current tenant only and handle 404.

PWA CHANGES:

- Invalidate the current tenant task list after mutation.

NOTES:

- Existing task payloads and lifecycle are preserved.

### PATCH /api/v1/tasks/{task}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 80,
    "title": "Inspect north site"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 404 foreign task or target site; 422 validation; existing legacy business errors remain.

ANDROID CHANGES:

- Use task/site IDs from the current tenant only and handle 404.

PWA CHANGES:

- Invalidate the current tenant task list after mutation.

NOTES:

- Existing task payloads and lifecycle are preserved.

### DELETE /api/v1/tasks/{task}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 404 foreign task or target site; 422 validation; existing legacy business errors remain.

ANDROID CHANGES:

- Use task/site IDs from the current tenant only and handle 404.

PWA CHANGES:

- Invalidate the current tenant task list after mutation.

NOTES:

- Existing task payloads and lifecycle are preserved.

### GET /api/v1/cash-advances

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Load and select targets from the current company; show 404 if a cached target is no longer valid.

PWA CHANGES:

- Scope cash-advance lists and selector to the active company.

NOTES:

- site_manager_id is an explicit attribution to a worker with SITE_MANAGER role. Cash advances have no DailyLog ownership.

### POST /api/v1/cash-advances

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 25,
    "site_manager": {
      "id": 2
    },
    "amount": "300.00"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 404 foreign/missing advance or manager; 422 validation; 500 target is not a site manager.

ANDROID CHANGES:

- Load and select targets from the current company; show 404 if a cached target is no longer valid.

PWA CHANGES:

- Scope cash-advance lists and selector to the active company.

NOTES:

- site_manager_id is an explicit attribution to a worker with SITE_MANAGER role. Cash advances have no DailyLog ownership.

### PATCH /api/v1/cash-advances/{cashAdvance}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 25,
    "site_manager": {
      "id": 2
    },
    "amount": "310.00"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 404 foreign/missing advance or manager; 422 validation; 500 target is not a site manager.

ANDROID CHANGES:

- Load and select targets from the current company; show 404 if a cached target is no longer valid.

PWA CHANGES:

- Scope cash-advance lists and selector to the active company.

NOTES:

- site_manager_id is an explicit attribution to a worker with SITE_MANAGER role. Cash advances have no DailyLog ownership.

### DELETE /api/v1/cash-advances/{cashAdvance}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 404 foreign/missing advance or manager; 422 validation; 500 target is not a site manager.

ANDROID CHANGES:

- Load and select targets from the current company; show 404 if a cached target is no longer valid.

PWA CHANGES:

- Scope cash-advance lists and selector to the active company.

NOTES:

- site_manager_id is an explicit attribution to a worker with SITE_MANAGER role. Cash advances have no DailyLog ownership.

### GET /api/v1/documents

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 422 for invalid filters where applicable.

STATUS CODES NOW:

200; 401; 422 for invalid filters where applicable.

ANDROID CHANGES:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

PWA CHANGES:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

NOTES:

- Standalone documents remain distinct from DailyLog attachments. An optional SITE_MANAGER attribution must belong to the current company.

### POST /api/v1/documents

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

multipart/form-data — construction_site_id=4, optional site_manager_id=2, files[]

CURRENT REQUEST:

multipart/form-data — construction_site_id=4, optional site_manager_id=2, files[]

REQUEST CHANGE:

NONE

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

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 role or document access; 404 foreign/missing record, site or manager; 422 validation or wrong role on explicit target where applicable.

ANDROID CHANGES:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

PWA CHANGES:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

NOTES:

- Standalone documents remain distinct from DailyLog attachments. An optional SITE_MANAGER attribution must belong to the current company.

### PATCH /api/v1/documents/{document}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
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
  "message": null,
  "data": {
    "id": 30,
    "name": "Site map"
  }
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 role or document access; 404 foreign/missing record, site or manager; 422 validation or wrong role on explicit target where applicable.

ANDROID CHANGES:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

PWA CHANGES:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

NOTES:

- Standalone documents remain distinct from DailyLog attachments. An optional SITE_MANAGER attribution must belong to the current company.

### DELETE /api/v1/documents/{document}

CHANGE TYPE:

AUTH CHANGE

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

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

CURRENT RESPONSE:

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 role or document access; 404 foreign/missing record, site or manager; 422 validation or wrong role on explicit target where applicable.

ANDROID CHANGES:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

PWA CHANGES:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

NOTES:

- Standalone documents remain distinct from DailyLog attachments. An optional SITE_MANAGER attribution must belong to the current company.

### GET /api/v1/documents/{document}/download

CHANGE TYPE:

AUTH CHANGE

PREVIOUS REQUEST:

NO BODY

CURRENT REQUEST:

NO BODY

REQUEST CHANGE:

NONE

PREVIOUS RESPONSE:

File stream with Content-Disposition.

CURRENT RESPONSE:

File stream with Content-Disposition.

RESPONSE CHANGE:

NONE

STATUS CODES BEFORE:

200; 401; 404 for a missing resource; 422 validation; 500 for legacy business rules where applicable.

STATUS CODES NOW:

200; 401; 403 role or document access; 404 foreign/missing record, site or manager; 422 validation or wrong role on explicit target where applicable.

ANDROID CHANGES:

- Use same-company document and site IDs. When metadata is editable, keep site_manager_id nullable.

PWA CHANGES:

- Restrict document selectors to same-company sites and managers. Preserve success envelope and stream handling.

NOTES:

- Standalone documents remain distinct from DailyLog attachments. An optional SITE_MANAGER attribution must belong to the current company.

## Locked mutation example

For `PATCH /api/v1/excavator-logs/61` a valid manager mutation on an open log returns 200 with the updated log. If the shared log is locked, the same request returns HTTP 500. In debug environments the business response can include the exception and request context. Drive write controls from `is_locked` and refresh the log after a rejected mutation.

Request (unchanged)

```json
{
  "site_manager_started_at": "2026-11-22 08:00:00",
  "site_manager_finished_at": "2026-11-22 10:00:00"
}
```

Response when the log is open (HTTP 200, projection)

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

Response when locked with `APP_DEBUG=false`

```json
{
  "message": "Server Error"
}
```

## ANDROID MIGRATION CHECKLIST

- Call select after site choice and persist the canonical DailyLog ID. Accept several sites per manager per day.
- Remove singular DailyLog members from DTOs, JSON adapters, and local entities. Parse `site_managers[]` with nullable `joined_at`.
- Use DailyLog ID and child ID for offline/cache keys, writes, and reconciliation. Migrate persisted entities if used. Keep pending local writes pinned to their original DailyLog.
- Display children from other members and keep their attribution. Permit nullable machine attribution and upsert takeover by returned ID.
- Handle 403, 404, 409, 422 and known business 500 responses. Disable locked mutations and preserve read access.
- Verify serialization, cached log hydration, offline replay, overlapping periods, and repeated selection.

## PWA MIGRATION CHECKLIST

- Call select from the site picker and set active state by returned DailyLog ID.
- Remove singular DailyLog ownership keys from types and views. Parse `site_managers[]`; retain child attribution fields.
- Invalidate shared children and machine queries by DailyLog ID, machine ID, or the applicable company/date filter. Remove manager/date ownership keys.
- Support nullable machine attribution and pending assignments. Refresh availability globally for the company.
- Disable mutations on locked logs and keep reads enabled. Handle 403, 404, 409, 422 and known business 500 responses.
- Verify shared cache refresh, empty 204 responses, streamed attachments and a second manager joining the current log.
