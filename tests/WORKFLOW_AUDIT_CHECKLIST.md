# Arbeitszeitcheck Critical Workflow Test Checklist

This checklist codifies the audit-critical workflow scenarios that must remain green.
It complements PHPUnit coverage with explicit end-to-end expectations.

## Time Tracking State Machine
- Clock-in rejects when already active.
- Clock-in rejects when currently on break.
- Clock-in resumes same-day paused entry (no duplicate active session).
- Clock-in rejects when max daily hours already reached.
- Clock-out succeeds from active and break state.
- Start break fails when no active session.
- End break fails when no active break.
- Status polling is read-only (no auto-complete side effects).

## Manual Time Entry Lifecycle
- Create manual entry validates date format (ISO/German date only).
- Create manual entry rejects overlap.
- Create manual entry enforces rest-period validation.
- Update entry rejects invalid date/time formats.
- Update entry enforces overlap + month-closure mutability.
- Delete rejects non-deletable automatic entries.

## Absence + Approval Workflow
- Create absence rejects overlap and invalid substitute.
- Create absence auto-approves when no assignable manager.
- Substitute approval transitions to pending or auto-approve path.
- Manager approve/reject requires managed employee scope.
- Cancel/shorten enforce status/date transition preconditions.

## Month Closure
- Finalized month blocks time-entry and absence mutations.
- Reopen requires admin + reason.
- Finalized month report returns snapshot-consistent data.

## Reporting/Compliance/Export
- Report endpoints enforce role-based report scope.
- Report date ranges enforce max span limits.
- Compliance rest-period endpoint rejects non-ISO timestamps.
- Team report refuses unauthorized team/user scopes.

## Public Surface and Error Handling
- Health endpoint excludes version fingerprint fields.
- API error payloads do not leak internal exception messages.
