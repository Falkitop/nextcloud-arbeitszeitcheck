# Developer Documentation – ArbeitszeitCheck

**Version:** aligned with the current app release (`appinfo/info.xml`)  
**Last Updated:** 2026-04-27

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
13. [Overtime and undertime traffic light](#overtime-and-undertime-traffic-light)
14. [Vacation entitlement policy engine (tariff rules)](#vacation-entitlement-policy-engine-tariff-rules)
15. [Revision-safe month closure](#revision-safe-month-closure)

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
- Node.js 20 or 22 and npm 10+
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

**Nextcloud schema portability (critical for upgrades):** Core runs `OC\DB\MigrationService::ensureOracleConstraints()` on app migrations whenever `info.xml` does **not** declare `<database>` dependencies (the default for this app). Among other rules, **new columns must not combine `Types::BOOLEAN` with `notnull => true`** — the migrator throws *"type Bool and also NotNull"*. Use `notnull => false` with a `default` instead (see `inherit_lower_layers` on `at_user_vacation_policies`) and treat `NULL` like `false` in PHP. See `lib/private/DB/MigrationService.php` in the Nextcloud tree and the developer manual section on database schema.

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

## Overtime and undertime traffic light

The app now supports a bidirectional balance traffic-light model for overtime and undertime with dedicated thresholds, recipients, and event matrix controls.

**Core services and classes**

- `OvertimeService`
  - remains the source for balance calculation (`cumulative_balance`).
  - still derives required hours from working days (`HolidayService`) and assigned working-time model.
- `OvertimeTrafficLightService`
  - normalizes and validates thresholds.
  - classifies balance into:
    - `green`
    - `yellow_over`, `red_over`
    - `yellow_under`, `red_under`
- `OvertimeTrafficLightJob`
  - daily job that evaluates all eligible users and sends notifications only on transitions.
- `OvertimeNotificationMailService`
  - sends plain-text mail notifications to configured recipients with validation and deduped recipient normalization.

**Configuration keys (`Constants.php`)**

- `overtime_traffic_light_enabled`
- `overtime_threshold_yellow_over`
- `overtime_threshold_red_over`
- `overtime_threshold_yellow_under`
- `overtime_threshold_red_under`
- `overtime_notification_recipients`
- `overtime_notification_matrix_v1`

The overtime matrix is normalized server-side as:

- direction: `over | under`
- level: `yellow | red`

Example shape:

```json
{
  "over": { "yellow": true, "red": true },
  "under": { "yellow": false, "red": true }
}
```

**Admin settings integration**

The traffic-light controls are integrated into the existing admin notifications page:

- backend: `AdminController::updateNotificationSettings`, `buildNotificationSettingsPayload`
- frontend: `templates/admin-notifications.php`, `js/admin-notifications.js`

Validation rules:

- recipient list length and item count limits apply (same base constraints as HR notifications).
- invalid mail addresses are rejected.
- if traffic-light notifications are enabled, at least one valid recipient is required.
- yellow threshold must be less than or equal to red threshold (for both over and under directions).

**Notification flow**

- in-app notifications:
  - dispatched via `NotificationService::notifyOvertimeTrafficLight`
  - rendered by `Notification\Notifier` subject `overtime_traffic_light`
- email notifications:
  - dispatched via `OvertimeNotificationMailService`
  - sent to normalized configured recipients

**Dedupe and anti-spam behavior**

- Job persists per-user last state (`overtime_traffic_light_last_state`).
- Notifications are sent only when:
  1. state changed, and
  2. new state is not `green`, and
  3. matrix allows the new direction/level.

This prevents repetitive daily spam when a user remains in the same warning/error state.

**UI exposure**

- Employee dashboard (`templates/dashboard.php`) now includes a traffic-light status badge next to overtime balance.
- State is computed in `PageController::dashboard` from `cumulative_balance`.
- Accessibility: status is represented by text + badge (not color-only), with `role="status"` and live-region semantics.

**Domain boundaries with vacation/carryover**

- Overtime traffic light uses overtime balance only (`OvertimeService`).
- Vacation/carryover entitlement logic remains in:
  - `AbsenceService`
  - `VacationAllocationService`
  - `VacationEntitlementEngine`
- Shared dependency is limited to working-day/holiday logic via `HolidayService`; no carryover pool data is used in traffic-light classification.

**Testing**

- unit test: `tests/Unit/Service/OvertimeTrafficLightServiceTest.php`
- full phpunit suite must pass after constructor changes in `PageController` tests.
- JS admin notification form validation is covered by existing frontend test setup and manual regression.

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
  - Calendar parameters (`effectiveFrom`, `effectiveTo`, `asOfDate`, and `GET …/vacation-layers?asOfDate=`) accept **only** strict `YYYY-MM-DD` strings that denote a real Gregorian day (overflows such as `2026-02-30` are rejected with HTTP 400, not silently normalised). L0/L1/L2 layer saves use the same rules inside `LayeredVacationDefaultsService` via `OCA\ArbeitszeitCheck\Support\StrictYmdDates`.

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

**Layered defaults (L0 / L1 / L2) — organisation, model, and team**

Shipped alongside the per-user L3 table (`at_user_vacation_policies`), the engine can draw defaults from:

| Layer | Table | Service / mapper |
| --- | --- | --- |
| L0 | `at_org_vacation_defaults` | `OrgVacationDefaultMapper`, `LayeredVacationDefaultsService::upsertOrgDefault` |
| L1 | `at_model_vacation_defaults` | `ModelVacationDefaultMapper`, `LayeredVacationDefaultsService::upsertModelDefault` |
| L2 | `at_team_vacation_policies` | `TeamVacationPolicyMapper`, `LayeredVacationDefaultsService::upsertTeamPolicy` |

Resolution order when layered resolution is **enabled** (default): **L3** (explicit non-`inherit` policy wins immediately) → **L2** (best matching team policy; tie-break: deeper team in the hierarchy, then **higher** `priority`, then smaller `team_id`) → **L1** (active default for the user’s working-time model on the date) → **L0** (organisation default valid on the date) → **legacy** (`resolveLegacyManualEntitlement`: model `vacation_days_per_year`, user setting, then `Constants::DEFAULT_VACATION_DAYS_PER_YEAR`).

- **Admin UI:** `GET …/admin/vacation-layers` (`AdminController::vacationLayers`) with JSON APIs under `/api/admin/vacation-layers/*` (CRUD for L0/L1/L2, simulator). Mutations use `ILockingProvider` advisory locks + DB transactions and write `AuditLogMapper` entries.
- **L3 `inherit`:** `UserVacationPolicyAssignment` supports `inherit_lower_layers` / vacation mode `inherit` so HR can defer to the chain without deleting the row.
- **Trace v1:** `VacationEntitlementEngine` emits a structured trace (`algorithm_version`, `as_of_date`, `matched_layer`, `layers_evaluated`, `winner`, `inputs_redacted`); allocation and snapshots consume the same contract.

**Production / legacy compatibility**

- **No mandatory data entry for upgrade:** After migration, **empty** L0/L1/L2 tables mean those layers never match; resolution behaves like before for tenants who never configure layers (still: L3 explicit policy, else legacy fallback).
- **Feature flag:** `Constants::CONFIG_LAYERED_ENTITLEMENTS_ENABLED` → app config key `layered_entitlements_enabled`, **default `1` (on)**. Set to `0` to force the pre-layered path: after L3 handling, the engine **skips** L2/L1/L0 and goes straight to the same **legacy** fallback as older releases (`VacationEntitlementEngine`, `isLayeredEnabled()`). Use this if you need to freeze behaviour during an audit or incident; it does **not** remove migrated rows.
- **Rounding (GAP-01):** All entitlement surfaces use `VacationEntitlementEngine::roundDays()` (half-up, 2 dp, clamp `[0, 366]`) so production numbers stay consistent across allocation, absences, and snapshots.

**Developer pitfall — `Entity` + `QBMapper::insert`**

Nextcloud’s `OCP\AppFramework\Db\Entity` only persists fields marked “dirty” by setters. If a typed property is **pre-initialised** in PHP to the same value you later `set…()`, the setter short-circuits and the column can be **omitted from `INSERT`**, causing `NOT NULL` errors (e.g. `vacation_mode`). The layered-default entities (`OrgVacationDefault`, `ModelVacationDefault`, `TeamVacationPolicy`) therefore use **nullable properties without PHP default literals** for insert-critical fields; the service layer always assigns values before `insert()`.

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

**Admin UI:** Administrators can **reopen** a finalized month from the app **admin settings** page: **search and select the employee** (Nextcloud account; uses `GET /api/admin/users?picker=1&search=…` via the shared admin user combobox — minimum 2 characters, enabled users only), then year, month, and mandatory reason. The action runs immediately via **“Reopen month”** and is **not** part of **Save all settings**. The `reopen` API still expects `userId` in the JSON body. The **Employees** admin table uses the full `GET /api/admin/users` payload with `limit`/`offset` pagination (50 per page) or `search` for quick lookup.

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

Add the same English source string to `l10n/en.json` and `l10n/de.json`. Run `php scripts/check-l10n-placeholders.php` when strings contain `{placeholders}` or printf-style tokens (`%s`, `%d`, `%1$s`, …).

**JavaScript (dynamic UI):** Prefer server-injected bundles on `window.ArbeitszeitCheck.l10n` so strings are translated before scripts run (works when `window.t` is unavailable on app pages).

| Partial | Included from | Used by |
|---------|---------------|---------|
| `templates/common/main-ui-l10n.php` | Most employee/manager pages | `arbeitszeitcheck-main.js` |
| `templates/common/time-entry-correction-l10n.php` | `templates/time-entries.php` | `time-entry-correction.js` (modal title, validation, dynamic break rows, submit/withdraw) |
| `templates/common/time-entry-form-l10n.php` | `templates/time-entries.php` (create/edit) | `time-entry-form.js` (form manager, validation, auto-breaks, submit) — **must use `TemplateL10n`** (see below) |
| `templates/common/time-entry-form-config.php` | `templates/time-entries.php` (create/edit) | `time-entry-form.js` (`breakIndex`, `submitUrl`, `maxDailyHours`, redirect) |
| `templates/common/time-entries-page-bootstrap.php` | `templates/time-entries.php` | List + form pages (`entries`, `apiUrl`, shared l10n; `autoBreakDuration30` / `45`) |
| `templates/common/manager-correction-l10n.php` | `manager-dashboard.php`, `manager-time-entries.php` | `manager-dashboard.js` (pending correction cards), `manager-time-entries.js` (direct “Correct” modal) |
| `templates/common/manager-employee-list-l10n.php` | Manager list pages | `manager-time-entries.js`, absences manager list — **`TemplateL10n`** |
| `templates/common/admin-overtime-payout-l10n.php` | Admin payout pages | Payout/audit JS — **`TemplateL10n`** |

Pattern in JS:

```javascript
function t(key, fallback) {
  const bundle = window.ArbeitszeitCheck?.l10n || {};
  const value = bundle[key];
  return value !== undefined && value !== '' ? value : (fallback || key);
}
```

Register new keys in the partial’s message map and add translations to both JSON files. Template-only strings need only `$l->t()` in PHP (with parameters when the string has placeholders).

#### Datepicker (`js/common/datepicker.js`)

- Mark inputs with class `datepicker-input` and placeholder `dd.mm.yyyy`; use `data-datepicker-defer` only when a modal clones markup before init (correction wizard).
- `data-datepicker-sync-month-with` links start/end fields (absence request form).
- Load `common/datepicker` on any page that renders `.datepicker-input` (e.g. `TimeEntryController::registerTimeEntryFormAssets`, `AbsenceController::registerAbsenceFormAssets`, `PageController::timeEntries`).
- Date + **Today** button rows use `form-input-wrapper--date`; the picker mounts inside `.azc-datepicker-host` so the calendar toggle does not overlap the Today control.
- Global `initAll()` runs on `DOMContentLoaded`; pages may call `ArbeitszeitCheckDatepicker.initInRoot(form)` after dynamic HTML.
- **Do not** set `data-datepicker-init` before calling `initializeDatepicker` — only `initializeDatepicker` may set that flag (otherwise the calendar never mounts).

#### `TemplateL10n` (required for JS export bundles)

`lib/Util/TemplateL10n.php` prevents **Internal Server Error** (`ValueError: The arguments array must contain N items, 0 given`) when a translated string is embedded in HTML via `json_encode(...)`.

**Cause:** `$l->t('Automatic %s break…')` returns a lazy `IL10NString`. Casting or `json_encode` forces `__toString()`, which runs `vsprintf` **without** arguments if none were passed.

**Rule:** Any message id containing `%s`, `%d`, `%1$s`, … that is exported to JavaScript must go through `TemplateL10n::translate($l, $messageId)` (or `mapFromMessageIds`). That supplies placeholder arguments so rendering succeeds, while leaving literals such as `%s` or `%1$s` in the output for **client-side** replacement.

```php
use OCA\ArbeitszeitCheck\Util\TemplateL10n;

$timeEntryFormL10n['autoBreakAddedCompliance'] = TemplateL10n::translate(
    $l,
    'Automatic %s break added for legal compliance'
);
// JS: t('autoBreakAddedCompliance').replace('%s', breakText)
```

**Client replacement patterns** (keep in sync with docs/tests):

| Placeholder | PHP export | JS consumer |
|-------------|------------|-------------|
| `%s` | `TemplateL10n::translate` → literal `%s` in JSON | `.replace('%s', value)` |
| `%1$s` | `TemplateL10n::translate` → literal `%1$s` | `formatBreakRowLabel(pattern, index)` replaces `%1$s` with `index + 1` |
| `%d` | `TemplateL10n::translate` → `0` in JSON (or explicit `[10]` when the final number is fixed server-side) | Use as fully translated string, or replace if you inject a literal `0` by design |

**Never** do this in l10n partials:

```php
// WRONG — crashes create/edit page at render time
'key' => $l->t('Automatic %s break added for legal compliance'),
```

**Checks:**

- `php scripts/check-l10n-placeholders.php` — en/de JSON arity
- `tests/Unit/Util/TemplateL10nTest.php`
- `tests/Unit/Templates/TimeEntryFormL10nTest.php` — regression guard for the time-entry form bundle

---

## Time entry create/edit form (manual entry)

**Routes:** `arbeitszeitcheck.page.timeEntries` with `mode=create` or `mode=edit` (e.g. `/apps/arbeitszeitcheck/time-entries/create`).

**Primary files:**

| Layer | Path |
|-------|------|
| Template | `templates/time-entries.php` (`#time-entry-form`, fieldsets, auto-break toggle) |
| JS | `js/time-entry-form.js` (`TimeEntryFormManager`) |
| CSS | `css/time-entries.css`, `css/time-entry-form-accessibility.css` |
| L10n | `templates/common/time-entry-form-l10n.php`, `time-entry-form-config.php`, `time-entries-page-bootstrap.php` |

**UX structure:** Page header → timezone/callout blocks → **Summary** (live working/break hours + compliance status) → **Date and time** (24h selects, “Today”) → **Breaks (optional)** (info callout, auto-break toggle, break matrix) → **Note** → actions. Uses shared `time-pair-matrix` layout and Nextcloud theme CSS variables so light/dark/high-contrast themes stay readable.

**Auto-break toggle (`#auto-break-enabled`, on by default):**

- When enabled, `time-entry-form.js` inserts or updates break rows to satisfy ArbZG §4 (30 min from 6 h work, 45 min from 9 h) before validation/submit.
- Auto rows are marked `data-auto-break="true"`, styled in CSS, and annotated with `autoBreakNote` from the l10n bundle.
- Notifications use `autoBreakAddedCompliance` with `%s` replaced by `autoBreakDuration30` / `autoBreakDuration45` from `time-entries-page-bootstrap.php`.
- Turning the toggle off removes auto rows and shows `autoBreakDisabled`.
- **Distinct from** the per-user setting **Automatic break calculation** (`auto_break_calculation` in `SettingsController` / clock-in flow via `TimeTrackingService`) — see `docs/Compliance-Implementation.en.md`.

**Break row indexing:** Server renders `data-break-index` from `0..n-1`; `time-entry-form-config.php` passes `breakIndex = count(existingBreaks)`. After load, `syncBreakIndexFromDom()` in JS sets the next index to `max(index)+1` so dynamically added rows never collide with PHP-rendered `breaks[n][start|end]` names.

**Accessibility (WCAG 2.1 AA target):**

- Skip links to form and app navigation; visible focus on toggle (`:focus-visible` on `.toggle-slider`).
- Auto-break and break-requirement blocks use `azc-semantic-panel` (`--success` / `--warning` / `--info`) from `css/common/semantic-surfaces.css`: body text always `--color-main-text`, accent via borders and solid icon wells, status via `azc-status-pill` (outline + dot, not filled success text). Panel state: `auto-break-panel--enabled` / `--disabled`. Legacy `azc-callout--success` tints in `app.css` are excluded when `.azc-semantic-panel` is present.
- Toggle uses `aria-describedby` (`auto-break-toggle-help`, `auto-break-toggle-state`); break requirement hint is `azc-semantic-panel--warning` with `hidden` until active.
- Summary and compliance text in `role="status"` / `aria-live="polite"` regions.
- Break row labels use visible icons plus `.sr-only` text; details in `css/time-entry-form-accessibility.css`.

**API:** Create `POST` `time_entry.apiStore`; edit `POST` `time_entry.apiUpdatePost` (URLs in `time-entry-form-config.php`). Times are normalized server-side (`AppLocalNaiveDateTimeNormalizer`) from the user’s display timezone to storage timezone.

**Tests:** `tests/Unit/Templates/TimeEntryFormL10nTest.php`; compliance E2E may disable `auto_break_calculation` for the test user when asserting strict §4 gates (`tests/e2e/compliance-gate-smoke.spec.js`).

---

## Time entry correction (employee UI)

**Entry points:** Time entries list → **Request correction** / **Withdraw**; template source `#time-entry-correction-source` is cloned into modal `#time-entry-correction-modal` on first open (`js/time-entry-correction.js`).

**Request API:** `POST /api/time-entries/{id}/request-correction`

| Field | Format | Notes |
|-------|--------|--------|
| `justification` | string | Required, 10–2000 characters (enforced client + server) |
| `date` | `yyyy-mm-dd` or `dd.mm.yyyy` | Work date |
| `startTime`, `endTime` | `HH:mm` | Wall clock on `date`; if `end ≤ start`, end is next calendar day |
| `breaks` | `[{start, end}]` optional | Each `HH:mm`; omitted or empty → keep existing breaks |

Legacy clients may still send ISO-8601 `startTime`/`endTime` instants.

**Withdraw API:** `POST /api/time-entries/{id}/cancel-correction` (no body).

**Related server messages** (must stay in `l10n/*.json`): `Direct edits are disabled…`, `A correction is pending approval…`, `At least one proposed change is required.`, `No pending correction to cancel.`, etc. — see `TimeEntryController::requestCorrection` / `cancelCorrection`.

**Manager approvals:** `ManagerController` pending time-entry cards + approve/reject endpoints; UI strings in `manager-correction-l10n.php`. **Manager direct edit:** `POST /api/manager/time-entries/{id}/correct` — see `docs/Compliance-Time-Entry-Workflows.de.md`.

**Tests:** `tests/Unit/Controller/TimeEntryControllerTest.php` (correction paths); workflow matrix in `tests/WORKFLOW_ROLE_MATRIX.md`.

**L10n:** Correction modal strings in `time-entry-correction-l10n.php` use plain `$l->t()` only where message ids have **no** printf placeholders. Strings with `{count}` / `{remaining}` are filled in JS; ids with `%` must follow the `TemplateL10n` rules if exported via `json_encode`.

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

Timezone-specific smoke (clock-in timer must not jump by the UTC offset; verifies `server_now` / `ArbeitszeitCheckTime` bootstrap):

```bash
npm run e2e -- tests/e2e/timezone-smoke.spec.js
```

PHPUnit coverage for the UTC→Berlin upgrade path:

- `tests/Unit/Migration/Version1015TimezoneMigrationTest.php` — conversion math and idempotency flag.
- `tests/Integration/TimezoneMigrationStateIntegrationTest.php` — post-upgrade config markers and `TimeZoneService` contract on a live instance.

### Audit-critical workflow checklist

`tests/WORKFLOW_AUDIT_CHECKLIST.md` is the short release checklist for workflows where regressions can affect legal/audit evidence: time tracking state transitions, manual entry corrections, absences and approvals, month closure, reports/exports/compliance, and public error surfaces. Keep it aligned with PHPUnit and Playwright coverage whenever these flows change.

`tests/WORKFLOW_ROLE_MATRIX.md` remains the broader route/role inventory. Use both documents together: the matrix defines who may reach each route, while the audit checklist defines the invariants that must remain green.

Important current invariants:

- Status polling endpoints must stay read-only. Background jobs or explicit mutation endpoints perform automatic break fallback / daily maximum enforcement.
- Report and export APIs accept strict date formats (`Y-m-d`, `Y-m`, ISO-8601 where documented) and reject ambiguous dates.
- Public or broad API surfaces should log internal details server-side but return generic, localized error messages to callers.
- Health responses must avoid version/fingerprint fields and expose only operational status.

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
