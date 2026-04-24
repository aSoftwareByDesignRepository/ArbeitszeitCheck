## ArbeitszeitCheck 1.2.6

### Highlights

- Hardens critical absence and vacation workflows for consistency under concurrent requests.
- Adds forensic approver identity persistence (`approved_by_user_id`) for approvals, rejections, and auto-approvals.
- Enforces entitlement snapshot uniqueness with deterministic upsert behavior on `(user_id, period_key, as_of_date)`.

### Included hardening work

- Added migration-backed unique key and dedup preparation for entitlement snapshots.
- Added robust conflict handling for concurrent upserts in entitlement snapshots and vacation year balances.
- Added user-scoped mutation locks and transactional lock/recheck paths for absence mutations.
- Normalized substitute status transitions and stricter date parsing in absence flows.
- Updated workflow-related unit and integration tests for the hardened logic.
