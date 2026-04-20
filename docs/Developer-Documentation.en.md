# Developer Documentation – ArbeitszeitCheck

**Version:** 1.1.15-dev  
**Last Updated:** 2026-04-20

This guide is for developers who want to contribute to ArbeitszeitCheck or integrate with it.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Development Setup](#development-setup)
3. [Code Structure](#code-structure)
4. [Database Schema](#database-schema)
5. [API Development](#api-development)
6. [Frontend Development](#frontend-development)
7. [Testing](#testing)
8. [Contributing](#contributing)
9. [Code Standards](#code-standards)
10. [Security Guidelines](#security-guidelines)
11. [Vacation carryover (Resturlaub)](#vacation-carryover-resturlaub)
12. [HR notification matrix and admin notifications](#hr-notification-matrix-and-admin-notifications)
13. [Vacation entitlement policy engine (tariff rules)](#vacation-entitlement-policy-engine-tariff-rules)
14. [Revision-safe month closure](#revision-safe-month-closure)

---

## Architecture Overview

### Technology Stack

- **Backend:** PHP 8.1+ with Nextcloud App Framework
- **Frontend:** Vanilla JavaScript with PHP templates
- **Database:** MySQL/MariaDB, PostgreSQL, or SQLite
- **Build Tools:** None required (vanilla JS)
- **Testing:** PHPUnit

### Architecture Pattern

ArbeitszeitCheck follows Nextcloud's standard app architecture:

```
apps/arbeitszeitcheck/
├── appinfo/           # App metadata and routes
├── lib/               # PHP backend code
│   ├── Controller/    # API controllers
│   ├── Service/       # Business logic
│   ├── Db/            # Database entities and mappers
│   └── BackgroundJob/ # Background jobs
├── js/                # Vanilla JavaScript
│   ├── common/        # Common utilities and components
│   └── [page].js      # Page-specific JavaScript
├── css/               # Stylesheets
│   ├── common/        # Common styles
│   └── [page].css     # Page-specific styles
├── templates/         # PHP templates
├── tests/             # Test files
└── docs/              # Documentation
```

### Design Principles

1. **Separation of Concerns:**
   - Controllers handle HTTP requests/responses
   - Services contain business logic
   - Mappers handle database operations
   - Entities represent data models

2. **Dependency Injection:**
   - Use Nextcloud's DI container
   - Inject dependencies via constructor
   - No static dependencies

3. **Type Safety:**
   - PHP strict types enabled
   - Type hints for all parameters and returns
   - No mixed types

---

## Development Setup

### Prerequisites

- Nextcloud 32+ installed and running
- PHP 8.1+ with required extensions
- Node.js 18+ and npm
- Composer
- Git

### Initial Setup

1. **Clone repository:**
   ```bash
   cd /path/to/nextcloud/apps/
   git clone https://github.com/aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck.git arbeitszeitcheck
   cd arbeitszeitcheck
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies** (for linting and dev tooling; the app uses vanilla JS, no build step required):
   ```bash
   npm install
   ```

4. **Enable app:**
   ```bash
   php occ app:enable arbeitszeitcheck
   ```

### Development workflow

There is **no** webpack/Vite bundle step for the app UI (`npm run build` is a no-op). For **JavaScript unit tests**, use:

```bash
npm run test:watch
```

Run Nextcloud using your usual stack (Docker Compose, web server, or `php -S` for quick experiments—not a substitute for a full instance). After changing PHP or static assets, reload the app in the browser.

### IDE Configuration

**PHPStorm/IntelliJ:**
- Set PHP language level to 8.1
- Enable PSR-12 code style
- Configure PHPUnit for tests

**VS Code:**
- Install PHP extensions
- Configure ESLint and Prettier (optional, for JavaScript)

---

## Code Structure

### Backend Structure

#### Controllers

Controllers handle HTTP requests and return responses:

```php
<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ExampleController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function index(): JSONResponse
    {
        return new JSONResponse([
            'success' => true,
            'data' => []
        ]);
    }
}
```

**Controller Annotations:**
- `@NoAdminRequired` - Endpoint accessible to all authenticated users
- `@NoCSRFRequired` - JSON API endpoints use this because the CSRF check runs before the request body is decoded; the frontend still sends `requesttoken` in headers for session integrity. Use sparingly.
- `@PublicPage` - No auth required (e.g. health check for load balancers). **Security:** Never expose raw exception messages or sensitive data on PublicPage endpoints.

#### Services

Services contain business logic:

```php
<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCP\ILogger;

class TimeTrackingService
{
    private TimeEntryMapper $timeEntryMapper;
    private ILogger $logger;

    public function __construct(
        TimeEntryMapper $timeEntryMapper,
        ILogger $logger
    ) {
        $this->timeEntryMapper = $timeEntryMapper;
        $this->logger = $logger;
    }

    public function clockIn(string $userId): TimeEntry
    {
        // Business logic here
        $entry = new TimeEntry();
        $entry->setUserId($userId);
        $entry->setStartTime(new \DateTime());
        
        return $this->timeEntryMapper->insert($entry);
    }
}
```

#### Mappers

Mappers handle database operations:

```php
<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class TimeEntryMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'at_entries', TimeEntry::class);
    }

    public function findByUser(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->orderBy('start_time', 'DESC');
        
        return $this->findEntities($qb);
    }
}
```

#### Entities

Entities represent database rows:

```php
<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

class TimeEntry extends Entity
{
    protected string $userId = '';
    protected \DateTime $startTime;
    protected ?\DateTime $endTime = null;
    protected float $durationHours = 0.0;
    protected string $status = 'active';

    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'startTime' => $this->startTime->format('c'),
            'durationHours' => $this->durationHours,
            'status' => $this->status
        ];
    }
}
```

### Frontend Structure

#### PHP Templates

Templates render data server-side using PHP:

```php
<?php
// templates/example.php
use OCP\Util;

Util::addScript('arbeitszeitcheck', 'common/utils');
Util::addScript('arbeitszeitcheck', 'example');
Util::addStyle('arbeitszeitcheck', 'example');
?>

<div id="app-content">
    <?php foreach ($_['items'] as $item): ?>
        <div class="item-card">
            <h3><?php p($item['name']); ?></h3>
            <button type="button" class="button primary" data-item-id="<?php p($item['id']); ?>">
                <?php p($l->t('Click me')); ?>
            </button>
        </div>
    <?php endforeach; ?>
</div>
```

#### Vanilla JavaScript

JavaScript handles interactions and AJAX updates:

```javascript
// js/example.js
(function() {
    'use strict';

    const Utils = window.ArbeitszeitCheckUtils || {};
    const Messaging = window.ArbeitszeitCheckMessaging || {};

    function init() {
        bindEvents();
    }

    function bindEvents() {
        const buttons = Utils.$$('.button[data-item-id]');
        buttons.forEach(btn => {
            Utils.on(btn, 'click', handleClick);
        });
    }

    function handleClick(e) {
        const itemId = e.target.dataset.itemId;
        
        Utils.ajax('/apps/arbeitszeitcheck/api/example/' + itemId, {
            method: 'GET',
            onSuccess: function(data) {
                if (data.success) {
                    Messaging.showSuccess('Operation successful');
                }
            },
            onError: function(error) {
                Messaging.showError('Operation failed');
                console.error('Error:', error);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

---

## Database Schema

### Tables

All tables use the `at_` prefix (short for arbeitszeitcheck):

- `oc_at_entries` - Time entries
- `oc_at_absences` - Absence requests
- `oc_at_vacation_year_balance` - Per user and calendar year: opening **carryover** days (Resturlaub from prior year, as recorded for year *Y*)
- `oc_at_vacation_rollover_log` - Idempotency for automatic carryover rollover from year *Y* to *Y+1* (one row per user/from_year/to_year when rollover ran)
- `oc_at_violations` - Compliance violations
- `oc_at_models` - Working time models
- `oc_at_user_models` - User working time model assignments
- `oc_at_settings` - User settings
- `oc_at_audit` - Audit logs
- `oc_at_month_closure` - Per user and calendar month: revision-safe finalization (status, canonical snapshot JSON, SHA-256 hash chain fields, version)
- `oc_at_month_closure_revision` - Append-only sealed rows per closure version (immutable copy for audit trail)
- `oc_at_tariff_rule_sets` - Versioned tariff rule set metadata (code, validity window, activation mode, status)
- `oc_at_tariff_rule_modules` - Ordered module blocks per rule set (`base_formula`, `additional_entitlements`, `deductions`, `rounding_rule`, `pro_rata_rule`)
- `oc_at_user_vacation_policies` - Per-user vacation policy assignments with effective date range and selected mode (`manual_fixed`, `model_based_simple`, `tariff_rule_based`, `manual_exception`)
- `oc_at_entitlement_snapshots` - Stored entitlement computation snapshots (as-of date, source, rule-set reference, calculation trace, policy fingerprint)

There is **no** `at_absence_calendar` table in current releases: migration `Version1012Date20260406120000` drops it. ArbeitszeitCheck does **not** integrate with the Nextcloud **Calendar** app (no CalDAV, no `OCA\Calendar` API). The in-app month view and optional email `.ics` attachments are separate from Calendar-app sync.

### Migrations

Migrations are in `lib/Migration/`:

```php
<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20241229000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        $schema = $schemaClosure();
        
        if (!$schema->hasTable('at_entries')) {
            $table = $schema->createTable('at_entries');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            // ... more columns
        }
        
        return $schema;
    }
}
```

### Vacation carryover (Resturlaub)

Carryover is **not** a separate “adjustment” column: the editable opening balance is `carryover_days` on `at_vacation_year_balance` for `(user_id, year)`.

**Config (app `IConfig`, keys in `Constants.php`):**

- `vacation_carryover_expiry_month` (1–12, default `3`)
- `vacation_carryover_expiry_day` (1–31, default `31`)
- `vacation_carryover_max_days` (optional, empty = no cap): clamps opening carryover everywhere (allocation, admin save, CSV import).
- `vacation_rollover_enabled` (`0`/`1`, default `1`): enables the daily `VacationRolloverJob`.
- `vacation_rollover_include_unused_annual` (`0`/`1`, default `0`): if `1`, rollover adds unused **annual** remainder to next year’s opening (Tarifvertrag-specific; off by default).

For calendar year *Y*, carryover from that row may only apply to vacation working days on dates **on or before** that month/day in year *Y*. After that calendar deadline, **new** vacation submissions (prospective validation with no prior request date) cannot draw from the carryover pool; they consume **annual entitlement only** for the whole chunk, matching `carryover_usable_for_new_requests` in `VacationAllocationService::computeYearAllocation`.

**Grandfathering (pending requests):** If an absence row already exists with `created_at` **on or before** the carryover deadline for year *Y*, validation at update/approve/auto-approve still allows FIFO carryover for that request, so a request filed in time is not blocked because approval happens later.

**Year transition (automatic rollover):** After the carryover deadline for year *Y*, unused carryover pool (FIFO `carryover_remaining_after_approved`, evaluated with `asOf` = first day after the deadline) can be written to opening `carryover_days` for year *Y+1* by `VacationRolloverService`, subject to the global cap and idempotency in `at_vacation_rollover_log`. The daily `VacationRolloverJob` runs when `vacation_rollover_enabled` is on; manual runs: `occ arbeitszeitcheck:vacation-rollover` (`--dry-run`, `--force`, `--year`, `--user`, `--ignore-disabled`). If `at_vacation_year_balance` already has a non-zero opening for *Y+1*, automatic rollover **skips** that user unless `--force`. HR may still set or import balances via **Admin → Users** or `occ arbeitszeitcheck:import-vacation-balance`.

Consumption order for **approved** vacation is **FIFO** (sort by `start_date`, then `id`), implemented in `VacationAllocationService` and used by `AbsenceService::getVacationStats` and vacation validation (including re-check on approve / auto-approve).

**CLI (initial migration from other HR systems):**

```bash
php occ arbeitszeitcheck:import-vacation-balance /path/to/balances.csv --dry-run
```

CSV columns: `user_id`, `year`, `carryover_days` (header row). Validates users exist; use `--dry-run` to preview. Values are clamped to `vacation_carryover_max_days` when set.

**CLI (rollover):**

```bash
php occ arbeitszeitcheck:vacation-rollover --dry-run
```

**Privacy:** `UserDeletedListener` deletes all `at_vacation_year_balance` and `at_vacation_rollover_log` rows for the removed user id.

**Known limitations (product):** Entitlement per historical year uses the **current** working time model assignment unless extended later; concurrent pending vacation requests are not “soft reserved” in the DB—approval-time validation prevents overdraw on commit under normal use. Rollover uses the **server date**; align organisation policy with the instance timezone.

---

## HR notification matrix and admin notifications

Today’s admin-notification update introduces a dedicated admin page and an explicit matrix-driven configuration model.

**UI and routes**

- Page route: `GET /admin/notifications` (`AdminController::notifications`)
- API routes:
  - `GET /api/admin/notifications/settings`
  - `POST /api/admin/notifications/settings`
- Frontend implementation:
  - template: `templates/admin-notifications.php`
  - script: `js/admin-notifications.js`
  - styles: `css/admin-notifications.css`

**Stored settings**

- `hr_notifications_enabled` (`Constants::CONFIG_HR_NOTIFICATIONS_ENABLED`)
- `hr_notification_recipients` (`Constants::CONFIG_HR_NOTIFICATION_RECIPIENTS`) - comma-separated, normalized and deduplicated
- `hr_notification_matrix_v1` (`Constants::CONFIG_HR_NOTIFICATION_MATRIX_V1`) - JSON matrix `absence_type => event => bool`

**Supported matrix dimensions**

- Absence types come from `Constants::ABSENCE_TYPES` (vacation, sick leave, personal leave, parental leave, special leave, unpaid leave, home office, business trip).
- Event keys come from `Constants::HR_NOTIFICATION_EVENTS`:
  - `request_created`
  - `substitute_approved`
  - `substitute_declined`
  - `manager_approved`
  - `manager_rejected`
  - `employee_cancelled`
  - `employee_shortened`

**Validation and constraints**

- Recipient input length is bounded.
- Maximum recipients: **20**.
- Invalid e-mail addresses are rejected with a 400 response.
- If HR notifications are enabled, at least one valid recipient is required.
- Matrix payload is normalized server-side so missing keys never result in undefined behavior.

`AbsenceNotificationMailService::sendHrOfficeNotification(...)` reads this config and sends HR updates only when:

1. feature is enabled,
2. matrix says the absence-type/event combination is allowed, and
3. at least one valid recipient exists.

The same admin page now centralizes related absence notification settings (carryover expiry/cap, rollover toggles, substitute requirements, iCal mail switches, substitution workflow mail toggles), so operators can maintain all absence-notification behavior in one place.

---

## Vacation entitlement policy engine (tariff rules)

Today’s entitlement work introduces a policy-driven engine that separates entitlement calculation from static model values.

**Core services**

- `VacationEntitlementEngine`
  - resolves active user policy for an `asOfDate`
  - supports four modes:
    - `manual_fixed`
    - `model_based_simple`
    - `tariff_rule_based`
    - `manual_exception`
  - returns structured output: `days`, `source`, `ruleSetId`, `trace`
- `EntitlementSnapshotService`
  - persists `at_entitlement_snapshots` records via upsert semantics for the same user/period/as-of date

**Tariff rule model**

- Rule set entity: `TariffRuleSet` (`draft`, `active`, `retired`)
- Rule modules: `TariffRuleModule` with ordered module execution
- Activation endpoint logic enforces date windows and retires/truncates overlapping active versions with same `tariff_code`.
- Active rule sets are immutable via update API; create a new version instead.

**Admin API surface**

- Rule sets:
  - `GET /api/admin/tariff-rule-sets`
  - `POST /api/admin/tariff-rule-sets`
  - `PUT /api/admin/tariff-rule-sets/{id}`
  - `POST /api/admin/tariff-rule-sets/{id}/activate`
  - `POST /api/admin/tariff-rule-sets/{id}/retire`
- User policies:
  - `PUT /api/admin/users/{userId}/vacation-policy`
  - `POST /api/admin/vacation-policy/simulate`

**Allocation and traceability integration**

- `VacationAllocationService::computeYearAllocation(...)` now resolves entitlement via `VacationEntitlementEngine`, not only legacy settings.
- Returned allocation payload now includes:
  - `entitlement_source`
  - `entitlement_rule_set_id`
  - `entitlement_trace`
- Each compute call stores a snapshot in `at_entitlement_snapshots` for auditability and future diagnostics.

**Migration and compatibility**

- `Version1017Date20260420120000` creates tariff/policy/snapshot tables.
- `Version1018Date20260420123000` backfills `at_user_vacation_policies` from existing model assignments (best-effort, idempotent) so legacy installations keep working with default `manual_fixed` policies.
- If no policy exists at runtime, the engine falls back to legacy manual entitlement resolution (`at_user_models` / user setting default).

**Data lifecycle**

- `UserDeletedListener` now deletes vacation policy assignments and entitlement snapshots for the deleted user to avoid orphaned policy/computation artifacts.

---

## Revision-safe month closure

**Purpose:** Optional per-employee monthly seal with tamper-evident snapshot (hash chain) and PDF export for archiving.

**Configuration:**

- `month_closure_enabled` (`Constants::CONFIG_MONTH_CLOSURE_ENABLED`), default `'0'`. When disabled, new finalizations are rejected with HTTP 403/consistent errors; **months already finalized remain locked** (mutation guards still apply).
- `month_closure_grace_days_after_eom` (`Constants::CONFIG_MONTH_CLOSURE_GRACE_DAYS_AFTER_EOM`), **0–90**, default `0`. After the end of a calendar month, employees have this many **calendar days** to finalize manually. If the month is **still open** after that deadline, the daily `MonthClosureAutoFinalizeJob` runs automatic finalization (same canonical snapshot as manual finalize). **Pending** time-entry correction approvals and **open absence workflow** states (`pending`, `substitute_pending`, `substitute_declined`, etc.) **block** auto-finalize until cleared. Reopening a month remains **admin-only**.

**Core classes:**

| Class | Role |
| --- | --- |
| `MonthClosureService` | Builds canonical payload (`buildCanonicalPayload`), finalizes/reopens inside DB transactions, audit logging, PDF text |
| `MonthClosureCanonical` | Stable JSON encoding (`encode`) and `hashChain` SHA-256 |
| `MonthClosureGuard` | Calls `MonthClosureService::assertDateRangeMutable` for time entries, absences, and “clock” days |
| `MonthClosureController` | JSON API under `/api/month-closure/*` (feature, periods, status, finalize, pdf, reopen) — `GET periods` lists `{ year, month }` for ended months that have at least one time entry (employee UI dropdown). `finalize` and `status` enforce the same rules server-side (including at least one time entry in that month); auto-finalize skips months with no entries. Responses include grace/deadline metadata (`graceDaysAfterEom`, etc.) for the employee UI. |
| `MonthClosureAutoFinalizeJob` | Daily: finalizes open months whose grace window has passed (see `MonthClosureService`). |

**Admin UI:** Administrators can **reopen** a finalized month from the app **admin settings** page: **search and select the employee** (Nextcloud account; uses `GET /api/admin/users` for suggestions), then year, month, and mandatory reason. The action runs immediately via **“Reopen month”** and is **not** part of **Save all settings**. The `reopen` API still expects `userId` in the JSON body.

**How to verify (manual):** Enable **revision-safe month finalization** and (optionally) set **grace days after month end**; save. As a normal user on **Time entries**, finalize a **past calendar month that has already ended** (not the current month) when no approvals are pending in that month. Confirm **status** / **PDF** on the same page and in `GET /api/month-closure/status`. As admin, **reopen** that month from settings, then confirm the employee can edit again; finalize a second time and check **`version`** increments and **`at_month_closure_revision`** gains a new row. **Automated:** `tests/Unit/Service/MonthClosureCanonicalTest.php` exercises canonical JSON and the hash chain only (not full finalize/reopen flows).

**Integration points:** `TimeEntryController`, `TimeTrackingService`, `ManagerController` (corrections), `AbsenceController`, `ReportController` (monthly report uses `getFinalizedMonthlyReportForUser` when the month is finalized and the request matches a full calendar month for one user).

**Concurrency:** Finalize uses a transaction; unique `(user_id, year, month)` prevents duplicate rows; pending correction entries block finalization.

**Not in scope:** Qualified electronic signature (QES). Integrity is enforced for **application-level** use; direct database edits bypass the app (organizational controls apply).

**Tests:** `tests/Unit/Service/MonthClosureCanonicalTest.php` covers canonical JSON and hash behavior.

**PDF output:** The downloadable month-closure PDF is a human-readable summary for archiving (tables, totals, hash metadata). The **full canonical JSON is not embedded** in the PDF; verification always uses the stored server-side payload and SHA-256 hash. Text uses standard PDF fonts with Windows-1252–compatible encoding; the document `/Lang` follows the user locale. This is not a full PDF/UA tagged document; users who rely primarily on screen readers should use data exports or APIs for machine-oriented verification.

---

## Absence and correction approval (assignable manager)

**Single source of truth:** `TeamResolverService::hasAssignableManagerForEmployee(string $employeeUserId): bool`

- **`use_app_teams` enabled:** Returns true iff `getManagerIdsForEmployee()` is non-empty (explicit team managers; the employee’s own UID is never counted as their own manager). **Colleagues alone do not imply an approver**—this matches `PermissionService::canManageEmployee()` for non-admin actors.
- **Legacy (groups only):** Returns true iff `getColleagueIds()` is non-empty (proxy only; there are no explicit manager rows). **Known product caveat:** manager HTTP APIs still require app teams + assignment for non-admins; auto-approval in legacy mode avoids deadlocks where nobody could approve.

**Consumers:**

- `AbsenceService`: auto-approves new `pending` requests (and after substitute approval when applicable) when the predicate is false; `doAutoApproveDbWork` records audit `absence_auto_approved`.
- `TimeEntryController::requestCorrection`: auto-completes correction when the predicate is false (same deadlock avoidance as absences).

**Repair:** `OCA\ArbeitszeitCheck\Repair\ReleaseStuckPendingAbsences` (registered in `appinfo/info.xml`) calls `AbsenceService::autoApprovePendingIfNoAssignableManager()` for each `pending` absence—idempotent, safe to re-run.

**Tests:** `TeamResolverServiceTest`, `AbsenceServiceTest` (including auto-approve path), `TimeEntryControllerTest`; matrix notes in `tests/WORKFLOW_ROLE_MATRIX.md`.

---

## API Development

### Adding New Endpoints

1. **Add route in `appinfo/routes.php`:**
   ```php
   ['name' => 'controller#method', 'url' => '/api/endpoint', 'verb' => 'GET']
   ```

2. **Add method in controller:**
   ```php
   /**
    * @NoAdminRequired
    */
   public function method(): JSONResponse
   {
       // Implementation
   }
   ```

3. **Document behaviour where relevant:**
   - If the endpoint is public or security‑relevant, add a short note to `README.md` or the appropriate doc in `docs/` (z. B. Rollen/Compliance)
   - Include request/response examples in code comments or tests if they are non‑obvious

### Manager API: pending approvals

`GET /apps/arbeitszeitcheck/api/manager/pending-approvals` (see `ManagerController::getPendingApprovals`) returns `pendingApprovals[]` items. For **`type=absence`**, each item includes **`summary`** from `Absence::getSummary()` plus a server-added field:

- **`summary.typeLabel`** — Localized human-readable absence type (same translations as elsewhere in the app, e.g. `Vacation` → German *Urlaub*). The manager dashboard UI prefers this for card titles so labels stay correct even if the raw `summary.type` code varies in edge cases.

The dashboard script `js/manager-dashboard.js` falls back to mapping `summary.type` client-side when `typeLabel` is absent (older responses).

### Error Handling

Always return proper HTTP status codes:

```php
try {
    // Operation
    return new JSONResponse(['success' => true], Http::STATUS_OK);
} catch (NotFoundException $e) {
    return new JSONResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
} catch (\Exception $e) {
    $this->logger->error('Error', ['exception' => $e]);
    return new JSONResponse(['error' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
}
```

---

## Frontend Development

### Using Common JavaScript Utilities

The app provides common utilities in `js/common/`:

```javascript
// DOM manipulation
const element = ArbeitszeitCheckUtils.$('#my-element');
const elements = ArbeitszeitCheckUtils.$$('.my-class');

// AJAX requests
ArbeitszeitCheckUtils.ajax('/apps/arbeitszeitcheck/api/endpoint', {
    method: 'POST',
    data: { key: 'value' },
    onSuccess: function(data) {
        // Handle success
    },
    onError: function(error) {
        // Handle error
    }
});

// Messaging
ArbeitszeitCheckMessaging.showSuccess('Operation successful');
ArbeitszeitCheckMessaging.showError('Operation failed');

// Components
ArbeitszeitCheckComponents.openModal('my-modal-id');
```

### Frontend URL and request policy (important)

For reliability and security, all frontend network calls should follow one path:

1. **Prefer** `ArbeitszeitCheckUtils.ajax(...)` for app API calls.
2. If a raw `fetch(...)` is unavoidable, resolve URLs with `ArbeitszeitCheckUtils.resolveUrl(...)` and read CSRF token via `ArbeitszeitCheckUtils.getRequestToken()`.
3. Do not hardcode raw app URLs in fetch calls (e.g. `fetch('/apps/arbeitszeitcheck/...')`) — lint rules reject this.

Behavior implemented in `js/common/utils.js`:

- `resolveUrl(...)` normalizes app URLs for both pretty-URL and `/index.php` deployments.
- `ajax(...)` injects `requesttoken` and `credentials: 'same-origin'`.
- External cross-origin URLs are blocked by default in `ajax(...)`; explicit opt-in is required with `allowExternal: true`.

### Mobile/iPhone layout guidance

Shared layout behavior is centralized in:

- `css/common/app-layout.css`
- `css/common/responsive.css`
- `css/navigation.css`

Key rules:

- Use safe-area aware spacing (`env(safe-area-inset-*)`) for iPhone notch/home-indicator devices.
- Keep interactive controls at least ~44px height (WCAG touch-target guidance).
- Preserve clear section hierarchy: one strong heading, concise helper text, and consistent card/action spacing.
- Keep mobile behavior in shared CSS first; page CSS should only add local adjustments.

### CSS Organization

**Use BEM naming:**
```css
.arbeitszeitcheck-block {}
.arbeitszeitcheck-block__element {}
.arbeitszeitcheck-block__element--modifier {}
```

**Use CSS variables:**
```css
.arbeitszeitcheck-button {
  background: var(--color-primary);
  color: var(--color-main-text);
}
```

**Common styles are in `css/common/`:**
- `base.css` - Base styles and resets
- `components.css` - Reusable UI components
- `layout.css` - Grid and layout utilities
- `utilities.css` - Helper utility classes

### Internationalization

Use PHP translation in templates:

```php
<?php p($l->t('Hello world')); ?>
```

Add translations to `l10n/de.json` and `l10n/en.json`.

For JavaScript, use Nextcloud's translation system if needed:
```javascript
// Translations are typically handled server-side in PHP templates
// For dynamic content, use AJAX to fetch translated strings
```

---

## Testing

### PHP Unit Tests

```php
<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use PHPUnit\Framework\TestCase;

class TimeTrackingServiceTest extends TestCase
{
    public function testClockInCreatesEntry(): void
    {
        // Test implementation
        $this->assertTrue(true);
    }
}
```

Run tests:
```bash
composer test
```

### JavaScript Tests

JavaScript unit tests are run with **Vitest** (jsdom environment).

Run tests:
```bash
npm test
```

### E2E workflow tests (Playwright)

E2E tests run against a real Nextcloud instance and cover role-based workflows.

Environment variables required:
- `NC_BASE_URL` (example: `http://localhost:8081`)
- `NC_EMPLOYEE_USER` / `NC_EMPLOYEE_PASS`
- `NC_MANAGER_USER` / `NC_MANAGER_PASS`
- `NC_ADMIN_USER` / `NC_ADMIN_PASS` (for admin-only scenarios when added)
- `NC_SUBSTITUTE_USER` / `NC_SUBSTITUTE_PASS`

Run:
```bash
npm run e2e
```

### Docker-based development (optional)

If you use a Docker Compose stack for Nextcloud, run tests inside the container from the app directory under `custom_apps`.

**Recommended (this repository):** From the Nextcloud **server repository root** (where `docker-compose.yml` and `docker/run-app-phpunit.sh` live), with the stack up (`docker compose up -d`):

```bash
./docker/run-app-phpunit.sh arbeitszeitcheck
```

The script targets the `nextcloud-app` container by default; set `NEXTCLOUD_DOCKER_CONTAINER` if your service name differs.

From **`apps/arbeitszeitcheck`** on the host you can also run:

```bash
composer test:docker
# or
npm run test:php:docker
```

Run PHP tests manually inside the container (adjust the Compose service name if needed, e.g. `nextcloud-app`):

```bash
docker compose exec -T nextcloud-app bash -lc "cd /var/www/html/custom_apps/arbeitszeitcheck && composer test"
docker compose exec -T nextcloud-app bash -lc "cd /var/www/html/custom_apps/arbeitszeitcheck && composer test:unit"
docker compose exec -T nextcloud-app bash -lc "cd /var/www/html/custom_apps/arbeitszeitcheck && composer test:integration"
```

Run focused security role-gating checks in Docker:
```bash
make test-security-role-gating-docker
# or
composer test:security-role-gating:docker
```

Run JS unit tests inside the Nextcloud container:
```bash
docker compose exec -T nextcloud bash -lc "cd /var/www/html/custom_apps/arbeitszeitcheck && npm ci && npm test"
```

Run E2E tests from your host machine (recommended) against the Dockerized Nextcloud at `http://localhost:8081`:
```bash
NC_BASE_URL="http://localhost:8081" \
NC_EMPLOYEE_USER="employee1" NC_EMPLOYEE_PASS="..." \
NC_MANAGER_USER="manager1" NC_MANAGER_PASS="..." \
NC_SUBSTITUTE_USER="substitute1" NC_SUBSTITUTE_PASS="..." \
npm run e2e
```

---

## Contributing

### Pull Request Process

1. **Fork repository**
2. **Create feature branch:**
   ```bash
   git checkout -b feature/my-feature
   ```
3. **Make changes:**
   - Follow code standards
   - Add tests
   - Update documentation
4. **Commit changes:**
   ```bash
   git commit -m "feat: Add new feature"
   ```
5. **Push and create PR:**
   ```bash
   git push origin feature/my-feature
   ```

### Commit Message Format

Follow [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `test:` Tests
- `refactor:` Code refactoring
- `style:` Code style changes
- `chore:` Maintenance tasks

### Code Review Checklist

Before submitting PR:

- [ ] Code follows PSR-12 (PHP) / ESLint rules (JS)
- [ ] All tests passing
- [ ] New tests added for new features
- [ ] Documentation updated
- [ ] No console errors
- [ ] Accessibility verified
- [ ] CSS properly scoped
- [ ] No hardcoded colors
- [ ] Translations added

---

## Code Standards

### PHP Standards

- **PSR-12** coding style
- **Strict types** enabled (`declare(strict_types=1);`)
- **Type hints** for all parameters and returns
- **PHPDoc** comments for all public methods
- **No mixed types**

### JavaScript Standards

- **ESLint** with strict configuration (optional)
- **Vanilla JavaScript** - no frameworks required
- **IIFE pattern** for code isolation
- **No console.log** in production code
- **Proper error handling**
- **Use common utilities** from `js/common/`

### CSS Standards

- **BEM naming** convention
- **Scoped styles** only
- **CSS variables** for colors
- **No !important** (unless documented)

---

## Security Guidelines

### Input Validation

Always validate and sanitize input:

```php
public function create(string $date, float $hours): JSONResponse
{
    // Validate date format
    $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj) {
        throw new \InvalidArgumentException('Invalid date format');
    }
    
    // Validate hours
    if ($hours < 0 || $hours > 24) {
        throw new \InvalidArgumentException('Hours must be between 0 and 24');
    }
    
    // Continue with validated data
}
```

### Authorization Checks

Always check permissions:

```php
public function getEntry(int $id): JSONResponse
{
    $entry = $this->timeEntryMapper->find($id);
    
    // Check ownership
    if ($entry->getUserId() !== $this->userId) {
        throw new \Exception('Access denied');
    }
    
    return new JSONResponse($entry->getSummary());
}
```

### App-admin authorization model

- The app distinguishes between **Nextcloud platform admins** and optional **ArbeitszeitCheck app admins**.
- Config key: `app_admin_user_ids` (`Constants::CONFIG_APP_ADMIN_USER_IDS`) stores a JSON array of allowed user IDs.
- Empty list is intentionally backward compatible: all Nextcloud admins are app admins.
- `AppAdminMiddleware` is registered in `Application::register()` and gates `AdminController` methods centrally.
- Unauthorized access to admin pages throws `NotAppAdminException` and resolves to a 403 response.

### Frontend request security model

- Central request guardrails are implemented in `ArbeitszeitCheckUtils.ajax(...)`.
- Cross-origin URLs are denied by default; callers must explicitly set `allowExternal: true`.
- URL normalization and token handling are centralized to avoid route drift and accidental insecure request patterns.
- ESLint guardrails in `.eslintrc.cjs` enforce this policy for `fetch(...)` usage.

### SQL Injection Prevention

Always use parameterized queries:

```php
// ✅ CORRECT
$qb->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

// ❌ WRONG
$qb->where($qb->expr()->eq('user_id', "'$userId'"));
```

---

## Resources

- **Nextcloud App Development:** https://docs.nextcloud.com/server/latest/developer_manual/
- **MDN Web Docs:** https://developer.mozilla.org/
- **Nextcloud App Framework:** https://docs.nextcloud.com/server/latest/developer_manual/
- **PHPUnit Documentation:** https://phpunit.de/
- **Vitest Documentation:** https://vitest.dev/ (JavaScript unit tests, if used)
