# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## 1.2.1 - 2026-04-21

### Fixed

- **Paused-entry recovery and lifecycle**: Paused entries can now be accessed again in edit/delete workflows and are consistently finalized as `completed` when edited with an end time.
- **Resume behavior for same-day paused sessions**: Clock-in now resumes a same-day paused entry instead of creating duplicate automatic entries, while preserving the pause gap as break history.
- **Historical paused leftovers**: Added migration `Version1020Date20260421000000` to repair all remaining orphaned `paused` rows (including cases not covered by the earlier one-time migration).

## 1.2.0 - 2026-04-21

### Added

- **Vacation entitlement policy engine**: New policy-driven calculation flow with support for `manual_fixed`, `model_based_simple`, `tariff_rule_based`, and `manual_exception`, plus admin simulation endpoint.
- **Tariff rule data model and APIs**: Added versioned tariff rule sets/modules and admin endpoints to create, update, activate, retire, and assign policies to users.
- **Entitlement computation snapshots**: Added persistent entitlement snapshots (`at_entitlement_snapshots`) with calculation trace/policy fingerprint for auditability and diagnostics.
- **Admin notifications page**: New dedicated admin UI (`/admin/notifications`) with HR recipient + event matrix management and a dedicated notifications settings API.

### Changed

- **Vacation allocation integration**: Year allocation now resolves entitlement via `VacationEntitlementEngine` and returns entitlement source/rule-set/trace metadata in allocation payloads.
- **Policy migration compatibility**: Existing user model vacation values are backfilled into policy assignments during migration (`Version1018Date20260420123000`) to keep legacy installs consistent.
- **Admin settings flow**: Absence notification-related controls (carryover expiry/cap, rollover switches, substitute-required types, iCal and substitution-mail toggles) are centralized on admin notifications APIs/UI.
- **Working time model schema**: Added `work_days_per_week` to `at_models` (`Version1019Date20260420150000`) to support entitlement formulas.

### Fixed

- **User deletion cleanup**: Deleting a user now also removes vacation policy assignments and entitlement snapshots, preventing orphaned policy/computation data.

## 1.1.14 - 2026-04-14

### Fixed

- **Approver deadlock (app teams)**: Absence and time-entry correction workflows no longer treat “has colleagues” as “has a manager”. Auto-approval when **no assignable approver** exists now follows `TeamResolverService::hasAssignableManagerForEmployee()` (explicit team managers in app-teams mode; legacy group mode still uses colleagues as a proxy). Prevents requests stuck in “awaiting manager approval” when nobody can approve.
- **Time entry corrections**: Same assignability rule as absences (previously used colleague IDs only).
- **Admin users API requests on `/index.php` instances**: Refresh/edit/history/update actions now reliably resolve app URLs and no longer produce invalid requests like `search=[object PointerEvent]`.
- **Admin teams and settings API reliability on rewrite-less setups**: Central URL resolution now includes a robust `/index.php` fallback when `OC.generateUrl()` is unavailable/incomplete in page context.

### Added

- **Repair step** `ReleaseStuckPendingAbsences`: post-migration repair auto-approves legacy `pending` absences that still match the “no assignable approver” condition (idempotent).
- **Frontend URL security guardrails**: Shared AJAX layer now blocks external cross-origin calls by default (explicit `allowExternal: true` required), with unit tests covering URL normalization and external URL handling.
- **Lint guardrails**: ESLint rules now prevent introducing raw `fetch('/apps/arbeitszeitcheck/...')` and implicit external `fetch(...)` patterns outside approved abstractions.

### Changed

- **UX**: Absences UI shows an informational callout when app teams are enabled and no approver is assigned; detail view shows a defensive warning if an old `pending` row is still stuck (until repair/admin fixes team setup).
- **Frontend architecture**: `ArbeitszeitCheckUtils` now provides centralized `getRequestToken()`, `resolveUrl()`, and `isExternalUrl()` primitives used by page scripts (`admin-users`, `reports`, `settings`, `validation`).
- **Mobile UX consistency (WCAG 2.1 AA focused)**: iPhone-safe-area-aware spacing, improved touch targets, clearer section rhythm, and better visual hierarchy for normal user pages (`dashboard`, `time-entries`, `absences`) and manager pages (`manager-dashboard`, `manager-time-entries`, employee absences view).

### Documentation

- User manuals (EN/DE), `tests/WORKFLOW_ROLE_MATRIX.md`, and developer documentation updated for assignable-manager semantics and repair step.
- README and developer documentation updated with centralized frontend URL policy, strict external-call behavior, and mobile/iOS layout guidance.

## 1.1.13 - 2026-04-13

### Added

- **Month closure grace period and auto-finalization**: Admin setting `month_closure_grace_days_after_eom` (0–90, default 0). After end-of-month, employees have that many calendar days to finalize manually; if the month is still open afterward, a daily background job finalizes it automatically (same snapshot as manual finalize). Pending time entry approvals and open absence workflow states block auto-finalization. Reopening remains admin-only.
- **App-admin allowlist**: New admin setting `app_admin_user_ids` to restrict ArbeitszeitCheck administration to a selected subset of Nextcloud admins. Empty selection keeps backward-compatible behavior (all Nextcloud admins can administer the app).
- **Security role-gating Docker test target**: Added `scripts/test-security-role-gating-docker.sh` wiring via `make test-security-role-gating-docker` and `composer test:security-role-gating:docker` for fast authorization regression checks in containerized setups.

### Changed

- **Month closure UX and API**: Employee UI uses a clearer card layout, visible feedback for success/errors (WCAG-friendly), server-driven `canFinalize` with localized block reasons (feature off, future month, pending approvals). Manual finalize rejects future calendar months. Absence workflow (`pending`, `substitute_pending`, `substitute_declined`) is enforced alongside pending time entry corrections. Unauthorized API access returns 401 where appropriate. Admin settings: dedicated “Month closure” section; grace-days field stays editable with copy explaining it is saved even when closure is off; reopen uses searchable employee picker and clearer administrator vs. employee wording. Form validation error callouts use higher-contrast text and tinted surfaces across themes. Auto-finalize job logs per-user failures for operations.
- **Release/signing workflow hardened for integrity checks**: `make release-signed` now signs the extracted release archive payload (not the local development checkout), validates forbidden development paths are excluded, and repacks the signed archive for deployment/App Store upload.
- **Admin authorization enforcement**: Access to `AdminController` routes now uses middleware-level app-admin checks with a dedicated exception and a consistent 403 response page for authenticated users without app-admin rights.

### Documentation

- **Deployment guidance**: Release docs now explicitly require production deployment from the signed tarball only and document the common integrity-failure pattern (`.git/*` / `node_modules/*` lists) caused by signing a dev tree.
- **Deployment helper script**: Added `release/deploy-from-release.sh` to deploy from signed release archives with safety checks (forbidden path scan, required `signature.json`, optional app disable/enable and `occ integrity:check-app`).
- **Admin operations**: User/developer docs now describe how to configure app-admin allowlisting, what the default fallback is, and how to verify authorization gating in Docker-based test runs.

## 1.1.12 - 2026-04-09

### Added

- **Revision-safe month finalization (optional)**: Admin toggle `month_closure_enabled` (default off). Employees can finalize a full calendar month; the app stores a canonical JSON snapshot, SHA-256 hash chain, append-only revision rows, audit events, and a minimal PDF download. Finalized months are read-only through normal app APIs; administrators may reopen a month with a mandatory reason (audit). Monthly reports for a finalized month use the stored snapshot. Database: `at_month_closure`, `at_month_closure_revision` (migration `Version1014Date20260409120000`).

### Documentation

- User manuals (EN/DE), developer documentation, and compliance notes updated for month closure, retention context, and limits (in-app tamper evidence, not QES).

## 1.1.11 - 2026-04-09

### Added

- **Manager employee absences view**: New in-app page and API for managers/admins to review employee absences with secure scope filtering, pagination, and localized status labels.
- **Working time model copy flow**: Added copy action with modal UX, unique default naming, and safeguards against duplicate submits.

### Changed

- **Manager navigation structure**: Sidebar regrouped into clearer manager/admin submenus; reports moved under manager context; compliance link placement adjusted for reduced top-level clutter.
- **Manager employee time entries UX**: Date defaults and formatting/translation handling improved for clearer filtering behavior.
- **Calendar behavior (rollback cleanup)**: Removed in-progress direct calendar-write functionality and related admin controls/status/test endpoints. The supported behavior remains unchanged: no Nextcloud Calendar app sync; optional `.ics` attachments are sent by email for configured absence workflows.

### Fixed

- **Working time model modals**: Corrected copy modal interaction flow, source-model presentation, and delete-confirmation localization/rendering issues.
- **Absence iCal hardening**: Added stricter status/date guards, recipient deduplication, and privacy-safe event descriptions for substitute/manager recipients.

### Documentation

- User manuals and changelogs updated to reflect the final calendar model (email `.ics` optional, no direct Nextcloud Calendar app sync) and current manager/admin UX structure.

## 1.1.10 - 2026-04-07

### Added

- **Vacation rollover**: `VacationRolloverService`, background job, `occ arbeitszeitcheck:vacation-rollover`, migration `Version1013Date20260407120000` with `at_vacation_rollover_log`; unit tests.

### Changed

- **Frontend l10n**: Shared `templates/common/main-ui-l10n.php` and `teams-l10n.php` so translated strings are available early across pages; related template and JS updates.

### Fixed

- **Manager dashboard — pending absences**: API includes `summary.typeLabel` (server-localized absence type); UI prefers it so cards show translated labels (e.g. German *Urlaub*) instead of raw codes like `vacation`.

### Documentation

- `docs/Developer-Documentation.en.md`: pending-approvals API note for `typeLabel`; user manuals (EN/DE): manager pending approvals show localized absence types.

## 1.1.9 - 2026-04-05

### Removed

- **Nextcloud Calendar app (CalDAV)**: Absence sync into the Calendar app is removed; migration `Version1012Date20260406120000` drops the `at_absence_calendar` table. Calendars previously created in the Calendar app are not deleted automatically.

### Changed

- **Holiday service**: Public holiday calendar logic consolidated in `HolidayService`.

### Fixed

- **AdminController**: Duplicate `use` statement for `HolidayService` caused a PHP fatal error (e.g. when PHPUnit loaded the class).

### Documentation

- User manuals (EN/DE) in `docs/`, README and developer documentation updated; helper script `docker/run-app-phpunit.sh` for containerized PHPUnit.

## 1.1.7 - 2026-04-05

### Added

- **Vacation carryover (Resturlaub)**: Per user and calendar year, opening balance `carryover_days` in `at_vacation_year_balance`; global admin setting for carryover expiry (month/day, default 31 March). `VacationAllocationService` applies FIFO consumption of approved vacation (by `start_date`, then `id`) and splits working days before/after expiry so carryover is used first where still valid.
- **Validation & approvals**: Vacation requests are re-validated when a manager approves (and on auto-approve) so concurrent pending requests cannot overdraw balances after approval.
- **API & UI**: `AbsenceController::stats` exposes entitlement, carryover, totals, expiry-related fields; dashboard and absences pages show a clear vacation summary; admin settings include expiry fields.
- **GDPR**: `UserDeletedListener` removes vacation year balance rows when a user account is deleted.
- **Migration / bulk setup**: `occ arbeitszeitcheck:import-vacation-balance` imports CSV `user_id,year,carryover_days` with `--dry-run`.

### Tests

- Unit tests for `VacationAllocationService`; extended `AbsenceService` and related controller tests.

## 1.1.6 - 2026-03-27

### Added

- **Development tooling**: `occ arbeitszeitcheck:generate-test-data` CLI for deterministic demo data (time entries, absences, optional violations, demo app team) to exercise UI, reports, and workflows locally.
- **Exports**: `TimeEntryExportTransformer` centralizes field mapping and CSV shaping for time-entry exports; `ExportController` delegates to it for a single, testable pipeline.

### Fixed

- **Reports UI**: Report type cards are no longer incorrectly disabled when a team-related scope is selected (team scopes still use the team report API where applicable).
- **Reports (tests)**: Team report CSV download test now reads download bodies via `DataDownloadResponse::render()` (Nextcloud API).
- **Team reports**: Deduplicate user IDs before permission checks and aggregation to avoid double-counting when users appear in multiple teams.
- **Absence type badges**: Stronger, theme-safe contrast for vacation / sick / home office / other badges (readable on pale Nextcloud palettes).

### Changed

- **Compatibility (dev)**: Local development stacks aligned with Nextcloud 33.x (example: official `nextcloud` Docker image).
- **Reports layout**: Reverted an overly aggressive “full width” parameter form rule that could interfere with scrolling/layout on the reports page.
- **Reports UI**: Templates, JavaScript, and styling updates for the reports page; admin settings hook for related options.
- **Reporting**: `ReportController` and `ReportingService` adjustments aligned with the export refactor.

### Tests

- Unit tests for `TimeEntryExportTransformer`; expanded `ReportController` tests; `ExportController` tests updated for the new wiring.

## 1.1.5 - 2026-03-26

### Fixed
- **Admin settings API URL handling**: Prevented duplicate `index.php/index.php` path generation when a route URL is already pre-generated by Nextcloud.
- **Frontend error handling**: Avoided unhandled Promise rejections in callback-based `Utils.ajax()` consumers after expected API failures.

## 1.1.4 – 2026-03-25

### Fixed
- **Routing/compatibility**: Added `indexApi()` compatibility aliases for legacy endpoints to prevent 500 errors in the Nextcloud log.
- **PHP fatal errors**: Fixed constructor signature issues in `AbsenceService` and `ComplianceService` that could crash the app when loading services or saving settings.
- **Reports security hardening**: Hardened report preview endpoints with `start <= end` validation and a maximum date-range limit to reduce DoS risk from untrusted parameters.
- **Admin “whole organization” scope**: Correctly handle admin organization scope (`userId=""` = all enabled users) and enforce access checks so preview/download data stays consistent.
- **Reports rendering**: Improved Preview rendering for **absence** and **compliance** reports to match the actual report data structure.

### Changed
- **Reports UI semantics**: Team scope is limited to the team overview/export semantics that the backend actually returns (prevents misleading previews/downloads).
- **Organization download guidance**: Added explicit UI messaging for organization scope download limitations until organization-wide export endpoints are implemented.

## 1.1.3 – 2025-03-14

### Fixed

- **ArbZG compliance**: Corrected break check logic (9h/45min branch now reachable; check ≥9h before ≥6h)
- **Manager logic**: `employeeHasManager()` now uses `getManagerIdsForEmployee()` instead of `getColleagueIds()`
- **Reporting**: `getTeamHoursSummary()` respects period parameter (week/month)
- **Admin users**: `hasTimeEntriesToday` is now per-user, not system-wide
- **UserSettingsMapper**: Fixed falsy zero/empty-string handling in getIntegerSetting, getFloatSetting, getStringSetting
- **Routing**: Moved exportUsers route above getUser to fix route shadowing
- **Version1009 migration**: Replaced MySQL backtick SQL with portable QueryBuilder; use OCP\DB\Types
- **Duplicate notifier**: Removed double registration from Application.php boot()
- **API security**: Generic error messages instead of raw exception output (SubstituteController, GdprController)
- **PDF export**: Returns HTTP 422 with clear message instead of silent CSV fallback
- **LIKE injection**: WorkingTimeModelMapper::searchByName() uses escapeLikeParameter()
- **XSS**: Modal titles escaped in components.js; compliance-violations.js innerHTML escaped
- **Admin-settings form**: Added CSRF requesttoken
- **AbsenceService DI**: Fixed constructor argument order (IDBConnection)
- Admin holidays and settings: English source strings for l10n keys
- UserDeletedListener: inject TeamMemberMapper and TeamManagerMapper
- XSS: sanitise team names in admin-teams.js

### Changed

- **CSS**: Shadow-light variable, scoped resets, dark-mode color-mix fixes, semantic color variables, navigation height/z-index
- **Clock buttons**: Double-submit guard (disabled during API calls)
- **initTimeline()**: Max retry count (20) to prevent infinite loop
- **Accessibility**: aria-label on header buttons, label for admin user search, aria-modal on welcome dialog, English l10n keys in navigation
- **Docs**: Removed internal docs; added docs/README; corrected repo URLs
- **Manager dashboard**: Injected l10n from PHP so JS translations work
- Constants.php for magic numbers; user-facing error messages

### Added

- **Version1010 migration**: Compound indices on at_entries, at_violations, at_holidays, at_absences

## 1.1.2 – 2025-03-07

### Changed

- **Long-term refactor**: Replaced all `\OC::$server` usage with proper OCP APIs and constructor injection
- CSPService: Injected ContentSecurityPolicyNonceManager via constructor
- Controllers: Removed manual cspNonce (configureCSP handles it); injected IURLGenerator, IConfig where needed
- PageController: Injected IURLGenerator, IConfig; passes urlGenerator to templates
- HealthController: Injected IDBConnection for database check
- ProjectCheckIntegrationService: Injected LoggerInterface instead of OC::$server->getLogger()
- Templates: Replaced `\OC::$server` with `\OCP\Server::get()` (OCP public API)
- Added GitHub Actions release workflow (`.github/workflows/release.yml`)
- Updated PageControllerTest with full constructor mocks

## 1.1.1 – 2025-01-07

### Fixed

- Resolved duplicate route names in absence API (absence#store, absence#show, absence#update, absence#delete)
- Corrected settings class names in info.xml to use full OCA namespace
- Added declare(strict_types=1) to routes.php

### Changed

- Removed non-existent screenshot references from info.xml until real screenshots are captured

## 1.1.0 – 2025-01-04

### Added

- ProjectCheck integration for project time tracking
- Additional migrations for schema updates

## 1.0.3 – 2025-01-03

### Added

- Further database schema refinements

## 1.0.2 – 2025-01-02

### Added

- Working time models
- User working time model assignments

## 1.0.1 – 2025-01-01

### Added

- Absence management
- Audit logging
- User settings
- Compliance violation tracking

## 1.0.0 – 2024-12-29

### Added

- Initial release
- German labor law (ArbZG) compliant time tracking
- Clock in/out and break tracking
- Time entry management (create, edit, delete, manual entries)
- Basic compliance checks (max 8h/day, break requirements)
- GDPR-compliant data processing
- English and German translations
- WCAG 2.1 AAA accessibility compliance
