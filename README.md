# Vaccination Administration Dashboard

A Laravel 13 and Livewire 4 application for authorized vaccination programme staff. It supports child and guardian administration, configurable vaccine catalogue entries, versioned immunization schedules, and schedule approval records. Clinical decisions remain with authorized healthcare professionals.

## Requirements

- PHP 8.5 with `intl`, `mbstring`, `openssl`, `pdo_sqlite`, and `zip` extensions enabled
- Composer 2
- SQLite for the local development setup

## Local setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan app:create-administrator
php artisan serve
```

The administrator command prompts for the initial account details. Do not add a default password or demo administrator to the database.

## Vercel deployment

The repository includes a FrankenPHP `Dockerfile`, `Caddyfile`, `.dockerignore`, and `vercel.json` for Vercel's container runtime. Before deploying, configure a durable external PostgreSQL database and set the production `APP_KEY`, `APP_URL`, and `DB_*` variables in Vercel. The container includes the PostgreSQL PDO extension. Keep `APP_DEBUG=false` and use HTTPS.

Vercel containers have ephemeral filesystems. This app currently stores child photographs and SQLite backups through Laravel's local filesystem, so configure and verify private durable object storage before enabling photo uploads or production backups. Run migrations and seed the required roles in a separate release step; do not run migrations when a container starts. Create the first administrator through the existing `php artisan app:create-administrator` command using a secure production shell. Configure an external scheduler for reminder generation and backups before relying on those tasks.

The local SQLite database is intentionally excluded from Git and the Docker image. Start with a fresh production database and synthetic records only; never deploy local child or guardian records.

Run the feature and unit tests with:

```powershell
php artisan test --compact
```

## Current development status

- Phase 1: project inspection and architecture plan
- Phase 2: relational database schema and migrations
- Phase 3: authentication, users, roles, and permissions
- Phase 4: child, guardian, and vaccination-centre administration
- Centre-scoped child access for vaccinators and registration staff; administrators retain system-wide access
- Phase 5: vaccine catalogue and versioned schedule configuration
- Phase 6: vaccination records, dose administration, stock issue, and controlled correction
- Phase 7: due, upcoming, overdue, and unknown-history register
- Phase 8: appointments, follow-up history, and in-dashboard reminder queue
- Phase 9: batch inventory, stock movements, reorder alerts, expiry, and transfers
- Phase 10: dashboard summaries, operational reports, and CSV/Excel exports
- Phase 11: access review, sensitive-record access auditing, audit viewer, and scheduled SQLite backup
- Phase 12: full workflow testing and final review
- MVP development phases complete; environment-specific deployment setup remains

## Phase 5 behaviour

- Vaccine catalogue entries describe the vaccine and its target. Product presentations hold manufacturer, product, route, storage, vial, and dose-unit details.
- Vaccine identity fields cannot be changed after a schedule references the vaccine. Retain historical entries by deactivating them.
- Schedule drafts contain only values entered by authorized staff from an approved external source. The application does not provide a national or sample clinical schedule.
- Approval records the approving user, timestamp, effective period, and authority reference. Approval requires a user with the schedule approval permission, a confirmation, and at least one configured dose.
- Approved schedule versions and their dose entries are read-only. Create a new draft version to make changes; its dose entries are copied for review, and approval dates and references are cleared.
- Overlapping effective periods for approved versions with the same schedule name are rejected.
- Catalogue and schedule changes, including dose changes and approvals, are written to the audit log.

## Phase 6 behaviour

- Vaccinators with `vaccinations.record` can enter a completed dose from a child's profile. The entry records the vaccine, optional approved schedule dose, administration time, batch and product snapshots, centre, quantity, route, administering user, and notes.
- Schedule-backed entries are checked against the schedule version's approval and effective dates, vaccine and dose number, configured minimum age, and configured minimum interval. No clinical schedule is bundled with the application.
- Batch selection is limited to active stock. The batch must match the vaccine and centre, be in date on the administration date, and have sufficient stock. Dose entry and stock issue transaction are committed together; vial stock uses the configured doses-per-vial value.
- Duplicate same-day dose submissions are rejected. Stock is locked and checked during saving so concurrent requests cannot silently issue beyond available quantity.
- Administrators with `vaccinations.amend` correct a completed record by creating a linked replacement. The original record remains in history, the correction reason and before/after audit entries are retained, and stock is returned to the original batch and issued from the corrected batch in the same transaction.
- Vaccination record access requires both `children.view` and `vaccinations.view`. The built-in vaccinator can record doses but cannot amend them; amendment permission is administrator-only by default.
- Authorized vaccinators can record per-dose deferred, contraindicated, and not-eligible tracking directives, plus adjusted planned dates. Each directive requires a reason and is audited; these states do not replace professional clinical judgment.

## Phase 7 behaviour

- The due register evaluates required doses from schedules approved and effective on the current date. It calculates a target date from a configured recommended age and, when present, the recommended interval from the preceding recorded dose.
- Minimum intervals are not used as recommended due dates; they remain a lower-bound validation rule. When there is no configured target date, the register shows the case for history or schedule review instead of inventing a date.
- Children without any completed vaccination records are listed separately as unknown history. A dose shown as due or overdue is only an administrative follow-up indicator, not a decision about clinical eligibility.
- Filters cover tracking state, vaccine, centre, locality, age group, and due-date range. The page lists the most recent completed dose and the suggested administrative next action.
- Authorized users can assign an approved schedule version to a child with a required reason; assignment history is retained and audited. When one approved schedule is effective, it serves as the configured programme default. When multiple schedules are effective, an unassigned child's due status remains unknown until an authorized user assigns the applicable version.
- The first MVP implementation calculates the register from active children and their completed records at request time. Large installations may need a background refresh or indexed materialized register after real data volumes are known.

## Phase 8 behaviour

- Staff with appointment permission can book and reschedule visits, filter appointment lists by day or week, centre, vaccinator, and status, and record attended, missed, or cancelled outcomes. Cancelling requires a reason; duplicate child or vaccinator time slots are rejected. Marking a visit attended does not create a vaccination record.
- Follow-up outcomes are recorded separately with optional appointment and dose links, staff assignment, contact time, and next action date. Contact consent should be reviewed before making a call.
- Reminder rules support tasks before a dose due date, on the due date, and after a missed appointment. Templates are generic staff guidance; each reminder keeps a snapshot so template edits do not change historical tasks.
- Run `php artisan reminders:generate` to generate tasks manually. Laravel's scheduler runs it daily at 02:00; run `php artisan schedule:work` during local development or configure the host's scheduler in production.
- The queue always uses the in-dashboard channel. It attaches a guardian only when that guardian is the child's primary contact and consent is recorded as granted. If consent/contact details are missing, the task asks staff to verify them. No SMS, email, or external messaging provider is configured or called.
- Appointment, follow-up, rule, and reminder task changes are audited. Missed-appointment reminder rules become eligible after staff mark a booked visit as missed.

## Phase 9 behaviour

- Inventory officers can receive batches, issue stock, return unused quantities against a linked manual issue, transfer batches between centres, adjust stock, record wastage, and record disposal. Every balance change and its stock-ledger entry are committed together and audited.
- Returns cannot exceed the remaining amount of their source issue. Transfers preserve product, lot, and expiry; they create or add to a matching destination-centre batch and write paired transfer movements.
- Reorder thresholds are configured per product and centre, in the product's stock unit. Low-stock and out-of-stock alerts compare the threshold with active, unexpired balances. Near-expiry days are filterable; expired balances remain visible for disposal.
- Batches are sorted by FEFO. Expired, quarantined, and disposed stock cannot be routinely issued or transferred. Quarantined stock can be released only with a reason and before expiry; expired stock cannot be reactivated.
- Dose administration continues to issue stock automatically. Manual issue is a separate stock outflow; do not pre-issue doses that will also be recorded against the same batch. This MVP does not yet track stock balances inside individual vaccination sessions.
- Inventory routes require `inventory.manage`; built-in inventory officers do not have access to child vaccination histories.

## Phase 10 behaviour

- The dashboard shows active child totals, children with completed doses, appointment and reminder counts, incomplete history, and due, upcoming, overdue, and unknown-history tracking counts. Activity filters cover date range and centre; tracking counts also use vaccine, age group, and tracking-state filters.
- Dashboard filters run in a Livewire component, update without a full page reload, and persist as validated URL query parameters. The component rechecks dashboard permission on every Livewire request.
- Due indicators reuse the approved, effective schedule and the Phase 7 dose-status calculator. They remain administrative tracking indicators and do not determine clinical eligibility.
- Inventory alerts show low or out-of-stock reorder levels, near-expiry batches, and expired batches to users with inventory or report access. Recent child-level vaccination activity is shown only to users with `vaccinations.view`.
- Users with `reports.view` can view and export aggregated registrations, recorded-dose coverage, completed doses, due tracking, appointments, follow-ups, stock balances, stock movements, and wastage. Report exports are CSV or XLSX; rows do not contain child names, registration IDs, guardian details, or notes.
- Coverage rows are reported by centre, approved schedule version, vaccine, and dose. The denominator uses active children assigned to that schedule who have reached its configured recommended age; schedules without a recommended age are excluded from age-based coverage.
- The `settings.manage` screen configures duplicate-detection fields and review windows, plus the near-expiry alert period. Changes are stored in the database and audited.
- CSV and XLSX exports protect text cells against spreadsheet formula interpretation. XLSX generation requires the PHP `zip` extension, which is listed in the application requirements.

## Phase 11 behaviour

- The audit log screen requires `audit.view` (assigned to administrators by default), is paginated and filterable, and displays actor, action, affected record, timestamp, IP address, and recorded before/after values. Opening the audit screen is itself logged.
- Child lists and profiles, guardian lists and profiles, vaccination histories, vaccination entry forms, and amendment forms record access events without copying the viewed health or contact details into those access events. Password reset logs record only that a reset happened; they never contain a password or reset token.
- The `db:backup` command creates a timestamped SQLite snapshot with `VACUUM INTO`, verifies it with SQLite's integrity check, stores it on `BACKUP_DISK`, and prunes older generated snapshots based on `BACKUP_RETENTION_DAYS` (14 days by default). The scheduler runs it daily at 01:00.
- Local storage is the development default. Production operators must configure a private remote Laravel filesystem disk for `BACKUP_DISK`, enable encryption at rest at the storage provider, restrict access to the backup bucket, and run the scheduler. To restore, stop application writes, preserve a copy of the current database, replace the configured SQLite database file with a verified snapshot, run `php artisan migrate:status`, and resume the application. The automated backup test opens the snapshot and verifies its data and SQLite integrity.

## Phase 12 final review

- `php artisan test --compact`: 132 tests passed, 633 assertions.
- `php artisan migrate:status`: all thirteen migrations are applied.
- `composer audit --no-interaction`: no security vulnerability advisories found.
- Project-wide Laravel Pint formatting check passes.
- The MVP has no bundled or invented vaccination schedule. Before operational use, authorized clinical personnel must configure and approve the applicable schedule, and the deployment operator must configure remote backups, production secrets, HTTPS, and monitoring.

## Main permission groups

- `catalogue.view`, `catalogue.manage`
- `schedules.view`, `schedules.manage`, `schedules.approve`, `schedules.assign`
- `vaccinations.view`, `vaccinations.record`, `vaccinations.amend`, `vaccinations.clinical_authorize`

Built-in vaccinators can view the catalogue and schedules. Schedule management and approval are granted to administrators by default; custom roles can be configured by a role manager.

## Clinical configuration note

This application is an administrative tool. Schedule values, minimum intervals, and effective dates must come from the responsible health authority or authorized clinical personnel. A due or overdue status is an administrative tracking indicator, not a clinical decision.
