# ArbeitszeitCheck – Complete Workflow Map

> **Security-critical app.** Every workflow must work flawlessly. This document maps all workflows, entry points, logic, settings, tests, and gaps.

---

## 1. Workflow Index

| # | Workflow | Routes | Test | Notes |
|---|----------|--------|------|-------|
| 1.1 | Time tracking: clock in/out | `POST /api/clock/in`, `/api/clock/out` | TimeTrackingControllerTest | Core |
| 1.2 | Time tracking: breaks | `POST /api/break/start`, `/api/break/end` | TimeTrackingControllerTest | Core |
| 1.3 | Manual time entry CRUD | `POST/GET/PUT/DELETE /time-entries`, `/api/time-entries` | TimeEntryControllerTest | Core |
| 1.4 | Time entry correction request | `POST /api/time-entries/{id}/request-correction` | TimeEntryControllerTest | Auto-approve if no manager |
| 1.5 | Absence create/update/delete | `GET/POST /absences`, `/api/absences`, CRUD | AbsenceControllerTest | Core |
| 1.6 | Absence substitute flow | `GET /substitution-requests`, `POST /api/substitution-requests/{id}/approve\|decline` | SubstituteControllerTest | Vertretungs-Freigabe |
| 1.7 | Absence manager approval | `POST /api/manager/absences/{id}/approve\|reject` | ManagerControllerTest | Core |
| 1.8 | Manager dashboard | `GET /manager`, `/api/manager/*` | ManagerControllerTest | Team overview, approvals |
| 1.9 | Time correction manager approval | `POST /api/manager/time-entries/{id}/approve-correction\|reject-correction` | ManagerControllerTest | Core |
| 1.10 | Compliance: employee view | `GET /compliance`, `/compliance/violations`, `/compliance/reports` | ComplianceControllerTest | Status, violations, reports |
| 1.11 | Compliance: resolve violation | `POST /api/compliance/violations/{id}/resolve` | ComplianceControllerTest | Admin/manager only |
| 1.12 | Compliance: run check (admin) | `POST /api/compliance/run-check` | ComplianceControllerTest | Admin-only, forbidden for non-admin |
| 1.13 | Reports | `GET /api/reports/daily|weekly|monthly|overtime|absence|team` | ReportControllerTest | Core |
| 1.14 | Admin: dashboard, users, settings, models | `GET /admin/*`, `GET/POST /api/admin/*` | AdminControllerTest | Core |
| 1.15 | Admin: teams CRUD | `GET /admin/teams`, CRUD `/api/admin/teams` | — | **No dedicated test** |
| 1.16 | Admin: audit log | `GET /admin/audit-log`, export | AdminControllerTest | Core |
| 1.17 | Exports | `GET /export/time-entries|absences|compliance|datev` | ExportControllerTest | Core |
| 1.18 | User settings | `GET/POST /settings`, `POST /api/settings/onboarding-completed` | SettingsControllerTest | Core |
| 1.19 | GDPR export/delete | `GET /gdpr/export`, `POST /gdpr/delete` | GdprControllerTest | Core |
| 1.20 | Health check | `GET /health` | HealthControllerTest | Core |

---

## 2. Workflow Details

### 2.1 Time Tracking (Clock / Break)

**Entry:** `POST /api/clock/in`, `/api/clock/out`, `/api/break/start`, `/api/break/end`

**Flow:**  
Clock in → TimeEntry `active` → Start break → `break` → End break → `active` → Clock out → `completed` (or `paused`)

**Settings:** None (compliance rules from admin settings).

**Edge cases:** Break < 15 min (ArbZG §4) – compliance violation. Max 10 h/day (ArbZG §3).

---

### 2.2 Manual Time Entry

**Entry:** `POST /time-entries`, `POST /api/time-entries`, edit/delete via UI

**Flow:** User submits date, start, end, optional breaks → TimeEntry created (manual) → compliance check.

**Settings:** `max_daily_hours`, `compliance_strict_mode` (admin).

**Edge cases:** Overlapping entries, end before start, > 10 h/day – validation/rejection.

---

### 2.3 Time Entry Correction

**Entry:** `POST /api/time-entries/{id}/request-correction` (proposed start/end/breaks)

**Flow:**  
Request → Entry `pending_approval` → Notify manager →  
**If employee has manager:** Manager approves/rejects in dashboard.  
**If no manager:** Auto-approve (no stuck items).

**Edge cases:** No manager → auto-approve (fixed). Entry older than 14 days – edit restricted.

---

### 2.4 Absence (Create / Substitute / Manager)

**Entry:** `GET /absences/create`, `POST /absences`, `POST /api/absences`

**Flow:**  
Create → If substitute selected: `substitute_pending`, notify substitute.  
Else: `pending`.  
Substitute approve → `pending`. Substitute decline → `substitute_declined`.  
Manager approve/reject → `approved` / `rejected`.  
**If employee has no manager:** Auto-approve after substitute (or on create if no substitute).

**Settings:** `require_substitute_types` (admin), `send_ical_to_substitute` (admin).

**Edge cases:** No colleagues → hide substitute field. No manager → auto-approve.

---

### 2.5 Substitution Requests

**Entry:** `GET /substitution-requests`, `GET /api/substitution-requests`, approve/decline

**Flow:** User sees absences where they are substitute and status is `substitute_pending`. Approve → `pending`. Decline → `substitute_declined`.

**Nav:** Link hidden when user has no pending substitution requests.

---

### 2.6 Manager Dashboard

**Entry:** `GET /manager`, `/api/manager/team-overview`, `pending-approvals`, `team-compliance`, etc.

**Flow:** Resolves team via `TeamResolverService` (app teams or Nextcloud groups). Shows pending absences and time corrections for team members.

**Visibility:** Admin OR user with manager/leiter group OR user with team members.

---

### 2.7 Compliance

**Entry:** `GET /compliance`, `/compliance/violations`, `/compliance/reports`, resolve, run-check

**Flow:** Employee views status/violations/reports. Admin/manager can resolve. Admin can run manual check (`POST /api/compliance/run-check`).

**Settings:** `auto_compliance_check`, `realtime_compliance_check`, `compliance_strict_mode`, `enable_violation_notifications` (admin).

---

### 2.8 Reports

**Entry:** `GET /api/reports/daily|weekly|monthly|overtime|absence|team` (with date/user filters)

**Flow:** PermissionService checks access. Manager/admin can see team; employee sees own.

---

### 2.9 Admin

**Entry:** `GET /admin`, `/admin/users`, `/admin/settings`, `/admin/working-time-models`, `/admin/teams`, `/admin/audit-log`

**Flow:** Admin-only. CRUD for users, models, teams, settings, audit export.

---

### 2.10 Exports

**Entry:** `GET /export/time-entries|absences|compliance|datev` (format params)

**Flow:** Owner-only (or manager for team). CSV/JSON/Datev/PDF.

---

### 2.11 User Settings

**Entry:** `GET /settings`, `POST /settings`, `POST /api/settings/onboarding-completed`

**Flow:** User prefs: notifications, break reminders, auto-break, onboarding flag.

---

### 2.12 GDPR

**Entry:** `GET /gdpr/export`, `POST /gdpr/delete`

**Flow:** Export all user data (JSON) or delete all. Nextcloud GDPR integration.

---

## 3. Settings Inventory

### Admin (App Config)

| Key | Default | Description |
|-----|---------|-------------|
| `auto_compliance_check` | `1` | Daily compliance job |
| `realtime_compliance_check` | `1` | Real-time check |
| `compliance_strict_mode` | `0` | Strict mode |
| `require_break_justification` | `1` | Break justification |
| `enable_violation_notifications` | `1` | Violation notifications |
| `require_substitute_types` | `[]` | Types requiring substitute |
| `send_ical_approved_absences` | `1` | iCal on approval |
| `send_ical_to_substitute` | `0` | iCal to substitute |
| `max_daily_hours` | `10` | ArbZG max |
| `min_rest_period` | `11` | Min rest (hours) |
| `german_state` | `NW` | State for holidays |
| `retention_period` | `2` | Years |
| `default_working_hours` | `8` | Default daily |
| `use_app_teams` | `0` | App teams vs groups |

### User (UserSettingsMapper)

| Key | Description |
|-----|-------------|
| `notifications_enabled` | General notifications |
| `break_reminders_enabled` | Break reminders |
| `auto_break_calculation` | Auto-break logic |
| `onboarding_completed` | Onboarding done |
| `vacation_entitlement_days` | Vacation days |
| `personalnummer` | Personal number (DATEV) |

---

## 4. Test Coverage Gaps

| Area | Status | Action |
|------|--------|--------|
| ComplianceController::runCheck | ✓ Added | testRunCheckSucceedsWhenAdmin, testRunCheckReturnsForbiddenWhenNotAdmin |
| AdminController teams CRUD | No dedicated test | Add createTeam, updateTeam, deleteTeam, members, managers |
| TeamResolverService | Mocked only | Optional: dedicated unit tests |
| PermissionService | Mocked only | Optional: dedicated unit tests |
| AbsenceIcalMailService | No test | Optional: unit tests |
| TimeEntryController requestCorrection auto-approve | Partial | Verify auto-approve path tested |

---

## 5. Permission Matrix & Team/Organigram Notes

**Manager link:** Navigation now uses `PermissionService::canAccessManagerDashboard()` (single source of truth). Previously it used ad-hoc groupManager + manager/leiter group + teamResolver, which could show the link to users who would be redirected from the manager dashboard. Now the link only shows when the user can actually access the dashboard.

**Owner cannot resolve own violation:** By design (separation of duties). Only admin or manager can resolve. See ROLES_AND_PERMISSIONS.md.

**App teams vs groups:** When `use_app_teams=1`, team resolution uses `at_teams`, `at_team_members`, `at_team_managers`. When `0`, uses Nextcloud groups. See TeamResolverService and ROLES_AND_PERMISSIONS.md.

| Action | Who |
|--------|-----|
| Clock in/out, manual entry, own corrections | Owner |
| Absence create | Owner (substitute must be colleague) |
| Substitute approve/decline | `absence.substitute_user_id === currentUser` |
| Manager approve absence/time correction | `canManageEmployee(approver, employee)` |
| Resolve compliance violation | `canResolveViolation(actor, owner)` |
| Run compliance check | Admin only |
| Admin dashboard, users, settings, teams | Admin only |
| Reports (team) | Manager/admin |
| Exports | Owner (or manager for team) |

---

## 6. Audit Actions

| Action | Audit key |
|--------|-----------|
| Absence created | `absence_created` |
| Absence auto-approved | `absence_auto_approved` |
| Absence approved | `absence_approved` |
| Substitute approved | `absence_substitute_approved` |
| Time correction requested | `time_entry_correction_requested` |
| Time correction approved | `time_entry_correction_approved` |
| Time correction auto-approved | `time_entry_correction_auto_approved` |

---

## 7. UX / WCAG 2.1 AA

- Semantic HTML, headings, landmarks
- `aria-label`, `aria-current`, `aria-describedby` where needed
- Focus visible, keyboard navigable
- Contrast ratios, min touch targets (44px)
- Responsive layout (breakpoints)
- Clear sections, spacing, simple layout
