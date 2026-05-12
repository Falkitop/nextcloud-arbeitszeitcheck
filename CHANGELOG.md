# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Layered vacation entitlement — degraded-state trace flags & impact preview** (issue: hr/vacation-entitlement-hierarchy follow-up). The resolution trace now carries explicit `degraded_org_default_collision` (REQ-ENT-10), `partial_history` (REQ-ENT-13 / EC-11), `clamped` + `raw_*` values (EC-08), `rule_set_status_warning` (EC-05), and `degraded='model_lookup_failed'` (EC-04) markers so auditors can see misconfigurations and best-effort historical resolutions instead of silent fallback. The admin simulator surfaces these flags as labelled chips alongside the result; the employee explainer surfaces a redacted subset (`degraded`, `clamped`, `partial_history`) without leaking any internal IDs (REQ-SEC-05).
- **Impact preview endpoint** `GET /api/admin/vacation-layers/impact?scope={org,model,team}&targetId={int}` (REQ-UX-03). The vacation-layer dialog now shows "Up to N employees may be re-resolved by this change" inline before the admin clicks Save, with WCAG-compliant colour states that are never colour-only (icon + status text + ARIA live region).

### Changed

- **`LayeredVacationConflictException` for lock contention** (REQ-SEC-04 / EC-07). `LayeredVacationDefaultsService` now wraps Nextcloud's `OCP\Lock\LockedException` so the `AdminController` returns HTTP 409 with a translatable "another administrator is editing this layer" hint instead of a generic 500. The admin JS surfaces the message in the dialog feedback area rather than dismissing the form.

### Tests

- New: `LayeredVacationEntitlementEngineTest` adds 14 cases for the degraded-state flags and redacted-trace pass-through; `LayeredVacationDefaultsServiceTest` adds 6 cases for `previewImpact` (validation, missing-deps, model count, team-subtree aggregation); `AdminControllerTest` adds 4 cases for 409 mapping on save/delete and 200/400 mapping on the impact endpoint.
- All 534 unit tests pass.

## 1.3.0 - 2026-05-12

### Added

- **Layered vacation entitlement resolution** (issue: hr/vacation-entitlement-hierarchy). The annual vacation entitlement is now resolved through a deterministic, auditable precedence chain: L3 individual policy → L2 team/cohort policy → L1 working-time-model default → L0 organisation default → legacy safe default. Each layer can be configured manually, via the `model_based_simple` formula, or via an active tariff rule set. L3 assignments gain an `inherit_lower_layers` flag (new `inherit` mode) so HR can explicitly defer to the chain without deleting a row. L2 ties are broken deterministically by team depth → priority → smallest team ID. Every resolution emits a structured trace v1 envelope (`algorithm_version`, `as_of_date`, `matched_layer`, `layers_evaluated`, `winner`, `inputs_redacted`) that is persisted in entitlement snapshots for payroll audit (REQ-AUD-01).
- **Admin "Vacation entitlement" page** with WCAG 2.1 AA + responsive layout (`/admin/vacation-layers`): stepper-style precedence overview, separate cards for L0/L1/L2 with full history, native `<dialog>`-based create/edit drawer with inline validation, and a built-in simulator that resolves the entitlement for any employee on any date and displays the full per-layer trace.
- **Employee-facing "How is this calculated?" explainer** on the absences page. Surfaces a redacted, ID-free trace produced by `VacationEntitlementEngine::redactTraceForUser()` so colleagues' policy names and internal references never leak (REQ-SEC-05).
- **Concurrency safety** via `OCP\Lock\ILockingProvider` advisory locks scoped per layer/resource (REQ-SEC-04, EC-07) and per-write transactions through the `TTransactional` trait.
- **Audit-log entries** for every L0/L1/L2 create/delete (`org_vacation_default`, `model_vacation_default`, `team_vacation_policy` audit entities) with before/after JSON payloads (REQ-AUD-02).
- **Feature flag** `arbeitszeitcheck.layered_entitlements_enabled` (default ON). When disabled the engine deterministically routes through L3 → legacy fallback, preserving today's behaviour byte-for-byte.

### Fixed

- **GAP-01** — unified rounding of vacation entitlements across the engine, `VacationAllocationService`, `AbsenceService`, and `EntitlementSnapshotService`. The single canonical implementation `VacationEntitlementEngine::roundDays()` clamps to `[0, 366]` and rounds to 2 decimal places using `PHP_ROUND_HALF_UP`. Earlier paths mixed `(int)round(...)` with `round(value, 2)`, which could shift an employee's annual entitlement by ±1 day on `.5` boundaries.

### Schema

- New tables: `at_org_vacation_defaults` (L0), `at_model_vacation_defaults` (L1), `at_team_vacation_policies` (L2 with FK → `at_teams ON DELETE CASCADE`).
- `at_user_vacation_policies` (L3) gains `inherit_lower_layers BOOLEAN NOT NULL DEFAULT 0` — golden-file equivalent for every existing row.

### Documentation

- **Developer:** `docs/Developer-Documentation.en.md` — layered L0–L3 resolution, admin routes, audit/locking, production rollback via `layered_entitlements_enabled`, and the `Entity` / `QBMapper::insert` dirty-field pitfall for new layer entities.
- **Operators / end users:** `docs/User-Manual.en.md`, `docs/User-Manual.de.md` — admin **Vacation entitlement** page, emergency config rollback, employee-facing entitlement explainer; `docs/README.md` index updated.
- **Product spec (repo):** `pm/app-ideas/arbeitszeitcheck/vacation-entitlement-hierarchy.md` — adopted status, fact base aligned with shipped code, L2 tie-break text aligned with implementation, migration / backward-compatibility semantics for production upgrades.

### Tests

- New: `LayeredVacationEntitlementEngineTest` (cross-layer precedence, tie-breaking, simulation, trace envelope), `LayeredVacationDefaultsServiceTest` (validation, audit, lock, transactional behaviour), `LayeredVacationEntitlementSchemaTest` (migration schema + FK + idempotency).
- All 511 unit tests pass.

## 1.2.9 - 2026-05-12

### Fixed

- **Install-blocking foreign key crash on PostgreSQL** (issue #4): Migration `Version1014Date20260409120000` declared the `at_mcr_closure_fk` foreign key on `at_month_closure_revision.closure_id` by passing the raw, *unprefixed* string `'at_month_closure'` to `addForeignKeyConstraint()`. Doctrine then emitted SQL that referenced the literal `at_month_closure` relation instead of the prefixed `oc_at_month_closure`, which aborted the install on every PostgreSQL cluster with `SQLSTATE[42P01] / Undefined table: 7 / ERROR: relation »at_month_closure« does not exist`. The FK is now declared via `$schema->getTable('at_month_closure')` so the prefix is applied. MariaDB/MySQL were affected too, but silently — the FK was never created on those engines, leaving the month-closure audit trail without referential integrity.
- **Backfill of the missing month-closure FK on existing installs**: Added `Version1023Date20260512143000`. The migration first removes any orphan `at_month_closure_revision` rows whose `closure_id` does not point to an existing closure (these can only exist as a side-effect of the previously-missing FK on MariaDB) and then adds the FK with `ON DELETE CASCADE`. Fully idempotent — safe to re-run on healthy installs.
- **Locale-fragile "table does not exist" detection in migrations**: `Version1008`, `Version1009`, and `Version1015` previously detected a missing table on fresh installs by string-matching English driver error messages ("doesn't exist", "no such table", …). PostgreSQL localises these messages ("Relation … existiert nicht" on a German cluster), and the check leaked through, surfacing migration failures with a confusing translated database error. Replaced with explicit `IDBConnection::tableExists()` guards.
- **Locale-fragile runtime guards** in `SettingsController::index_api`, `AdminController::getTeams`, and `TeamResolverService`: replaced fragile error-message string matching with `OCP\DB\Exception::getReason() === REASON_DATABASE_OBJECT_NOT_FOUND` — the portable, locale-independent contract guaranteed by Nextcloud's DBAL wrapper. A small message-based fallback is kept only for non-DBAL paths (e.g. test doubles).

### Tests

- **Schema-level regression guard for the FK bug**: New `tests/Unit/Migration/MonthClosureForeignKeyTest` reproduces the migration's schema build against a real Doctrine `Schema` with the Nextcloud table prefix applied, then asserts that the FK references the *prefixed* table name and that re-running the migration is a no-op. This pins down the contract that originally regressed and catches any future migration that accidentally passes a raw, unprefixed table name to `addForeignKeyConstraint()`.

## 1.2.8 - 2026-04-30

### Security

- **CSRF protection on all state-changing endpoints**: Removed `#[NoCSRFRequired]` from POST/PUT/DELETE methods in `AbsenceController`, `TimeEntryController`, `TimeTrackingController`, `ComplianceController`, `SubstituteController`, `SettingsController`, `MonthClosureController`, `GdprController`, and `AdminController`. The frontend already submits `requesttoken` consistently via `ArbeitszeitCheckUtils.ajax`, so all mutating routes now reject cross-site requests by default. GET-only endpoints intentionally remain `#[NoCSRFRequired]` (CSRF is irrelevant for read-only GETs in Nextcloud's framework).
- **No raw exception leakage in JSON responses**: Hardened `AbsenceController::getSafeErrorMessage` so that exception messages are only forwarded when they are explicit business-rule `\Exception` instances; messages containing technical fingerprints (SQL fragments, file paths, stack traces, oversized payloads) are replaced with a generic localized error. Applied the hardened helper to `AbsenceController::store`/`update`. Replaced direct `getMessage()` leakage in `AdminController::getTeams`, `SettingsController::index_api`, and `PageController` page-render error paths with sanitized localized messages.
- **Correct HTTP status for authentication errors**: `SettingsController::update` now returns `HTTP 401 Unauthorized` (was `HTTP 400 Bad Request`) when the request is unauthenticated, matching what API clients and load balancers expect.

### Changed

- **Organization-scope monthly report downloads**: `reports.js` now forwards user IDs resolved during preview to the `report.team` endpoint and falls back to a clear "no organization members had time entries in the selected period" message instead of the misleading "preview first" hint when an organization-wide preview yields zero results.
- **Sanitized dashboard load errors**: `dashboard.js`/`dashboard.css` now surface a localized "Some dashboard data could not be loaded." live-region message instead of raw widget exceptions.
- **Resume-after-break clarity**: Clock-in copy and l10n unified around "Resume after break" instead of the legacy `clock_in_resume` placeholder.

### Accessibility (WCAG 2.1 AA)

- **Main landmark on every page**: 17 page templates now expose a single, properly labelled `<main id="app-content" role="main" aria-label="...">` landmark for assistive technologies (`dashboard`, `index`, `timeline`, `calendar`, `settings`, `personal-settings`, `reports`, `compliance-dashboard`, `compliance-reports`, `compliance-violations`, `working-time-models`, `admin-dashboard`, `admin-teams`, `admin-users`, `admin-holidays`, `manager-dashboard`, `manager-time-entries`, `manager-absences`, `manager-month-closures`).
- **Skip link / `<main>` consistency**: `time-entries`, `absences`, `admin-settings`, `admin-notifications`, `substitution-requests`, and `audit-log` previously had `id="app-content"` on a plain `<div>` while `role="main"` lived on a child wrapper, so the "Skip to main content" target landed on a non-landmark. All six now use `<main id="app-content" role="main">` directly. Removed redundant `role="banner"` from the `<header>` inside `audit-log`'s main region.
- **Accessible names on all data tables**: Added `aria-label`/`aria-labelledby` and screen-reader captions to the holiday list table and to the two notification-matrix tables that previously had no accessible name.
- **Live error announcement on dashboard**: Dashboard error section now lives inside an `aria-live` region so partial widget failures are announced without disrupting focus.
- **Manager dashboard team metrics now announced**: Stat numbers (Team Members / Active Today / Hours Today / Pending Absences) had `aria-hidden="true"` on the value spans, which silenced every metric for screen reader users. Each card now exposes a single, fully readable accessible name (e.g. "5 team members active today") via a `role="group"` wrapper while keeping the visual layout intact.
- **Alert vs live-region conflicts resolved**: Removed conflicting `aria-live="polite"` from `role="alert"` containers in `absences.php` (form error), `admin-settings.php` (global error banner), and three time-entry inline form errors. `role="alert"` already implies assertive announcements, so the previous `polite` override could delay critical validation feedback for assistive technology.
- **Page heading hierarchy normalized**: Every primary page template now exposes exactly one `<h1>` (dashboard, time-entries, absences, calendar, timeline, reports, settings, personal-settings, compliance-dashboard, compliance-reports, compliance-violations, working-time-models, admin-dashboard, admin-users, admin-holidays, admin-settings, admin-notifications, manager-dashboard). Previously most pages started at `<h2>`. Subordinate section headings in time-entries and absences were promoted to `h2`/`h3` so the ladder no longer skips levels. CSS rules for `.section-header h1` were added to inherit the existing `h2` styling.
- **Manager dashboard breadcrumb**: Added the standard "Dashboard › Manager Dashboard" breadcrumb to align with all other primary pages and improve orientation for keyboard and screen reader users.
- **Calendar loading state announced**: The "Loading calendar…" placeholder now uses `role="status"` with `aria-live="polite"` so the loading and ready transitions are announced. The decorative spinner is `aria-hidden`.
- **Focus indicators restored**: `outline: none` was used on the timeline filter checkboxes and the admin user-picker items, breaking keyboard focus visibility. Added `:focus-visible` outlines using the primary color for both, preserving hover styling.
- **Mobile touch targets**: `.btn--sm` was 36 × 36 px on mobile, below WCAG 2.5.5's 44 × 44 advisory. The mobile media query now enforces 44 × 44 px on small buttons; desktop sizing is unchanged.
- **Empty-state row for legacy `index.php` time-entries view**: Restored the missing empty-state row when no entries exist (parity with the other table views in the same template).
- **Reports access fallback navigation**: Replaced an inline `onclick` redirect in `reports.php`'s no-access empty state with a real `<a>` anchor so the dashboard fallback works without JavaScript and inherits standard link semantics.

### Removed

- **Stale Nextcloud personal-settings panel placeholder**: The old `personal-settings.php` panel rendered inside Nextcloud's user-settings shell with hardcoded vacation-days / working-hours fields and reminder checkboxes that were never wired to any backend. Replaced it with a clean, accurate panel pointing the user to the in-app personal settings page (where these preferences are actually persisted via `SettingsController::update`) plus a short GDPR data-rights note. Kept the legacy `index.php` "settings" branch (dead code, but still in the file) but pulled the previously hardcoded `1.0.1` version string from `IAppManager::getAppVersion('arbeitszeitcheck')`.

### Tests

- **AccessibilityTest hardened**: Replaced the "must contain `<button>`" assertion (which permitted pages without keyboard-reachable controls and false-flagged link-only panels) with two stricter checks: (1) explicitly forbid the `<div onclick=…>` anti-pattern and (2) require at least one `<button>` or `<a href>` per audited template. Total suite: 455 tests, 1 652 assertions, all green.

## 1.2.7 - 2026-04-27

### Added

- **Critical workflow audit checklist**: Added `tests/WORKFLOW_AUDIT_CHECKLIST.md` as a concise release checklist for time tracking, manual entry corrections, absences/approvals, month closure, reporting/compliance/export behavior, and public error-surface expectations.

### Changed

- **Time tracking mutation safety**: Clock/break mutations now use user-scoped locks and transactions; status polling remains read-only while automatic break fallback and daily maximum enforcement run through explicit mutation paths/background jobs.
- **API input and error hardening**: Report, export, compliance, manager, and time tracking endpoints now use stricter date/time parsing, safer validation responses, and generic public error messages for unexpected failures.
- **Month-closure enforcement**: Absence update/delete/cancel/shorten/approval/substitute flows now re-check month mutability before applying workflow mutations.

### Fixed

- **Health endpoint fingerprinting**: The public health response no longer exposes app or Nextcloud version fields.

## 1.2.6 - 2026-04-24

### Added

- **Absence approval forensics**: Added `approved_by_user_id` persistence on absence records (approve/reject/auto-approve), with schema migration and API summary output.

### Changed

- **Vacation entitlement snapshot integrity**: Added deterministic key-based upsert on `(user_id, period_key, as_of_date)` and migration-backed unique index enforcement.
- **Concurrency control in critical workflows**: Absence create/update/approve/reject/substitute flows now use user-scoped mutation locks plus transactional rechecks/row locks to prevent race-based overlap and over-approval inconsistencies.
- **Release safety**: Workflow/unit/integration tests were updated and executed against the hardened mutation paths.

### Fixed

- **Legacy snapshot repair path**: Upsert now handles historical malformed rows and concurrent unique-key conflicts safely by retrying as deterministic update.
- **Vacation balance write races**: `VacationYearBalanceMapper::upsert` now resolves concurrent unique-key collisions via re-read/update fallback.

## 1.2.5 - 2026-04-22

### Changed

- **Release packaging refresh**: Bumped app metadata to `1.2.5` and regenerated the signed release artifact set for App Store and GitHub publication.

## 1.2.4 - 2026-04-21

### Changed

- **Publishable release refresh**: Bumped app metadata to `1.2.4` and generated a new signed release artifact set (archive, checksums, and App Store signature) for App Store/GitHub publication.

## 1.2.3 - 2026-04-21

### Changed

- **Release packaging refresh**: Prepared a new signed App Store/GitHub release archive for the current code line using the Docker-based signing workflow.

## 1.2.2 - 2026-04-21

### Fixed

- **Localized decimal inputs in admin settings**: Daily working-hour inputs now reliably accept comma-decimals like `7,74` and preserve two-decimal precision.
- **Legacy hours API payload parsing**: Time-entry endpoints now parse optional decimal hour fields consistently for both comma and dot separators, preventing silent truncation in backward-compatible request formats.

### Changed

- **Input precision hints**: Updated settings input steps/help text to align with two-decimal hour values used in 38.7-hour week scenarios.

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
