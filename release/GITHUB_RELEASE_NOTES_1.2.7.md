## ArbeitszeitCheck 1.2.7

### Highlights

- Hardens clock and break mutations with user-scoped locks and transactions while keeping status polling read-only.
- Strengthens public API validation and unexpected-error responses across reporting, export, compliance, manager, and time tracking endpoints.
- Enforces month-closure mutability checks across absence mutation and approval/substitution flows.
- Removes app and Nextcloud version exposure from the public health response.

### Included hardening work

- Added `tests/WORKFLOW_AUDIT_CHECKLIST.md` as a release checklist for critical time tracking and compliance workflows.
- Routed automatic break fallback and daily maximum enforcement through explicit mutation paths and background jobs.
- Tightened date/time parsing and validation responses on user-facing API surfaces.
- Rechecked closed-month state before absence update, delete, cancel, shorten, approval, and substitute mutations.
