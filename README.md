# HouseGate — QR-Based Visitor Building Access Control & Monitoring System

**Department:** Legislative Security Bureau (LSB) | Perimeter Security Group
**Institution:** House of Representatives of the Philippines
**Author:** Migel H. Tan
**Program:** INSPIRE Internship Program (formerly SMART Internship Program)

---

## About This Project

This is a working software prototype built during an internship placement under the **Legislative Security Bureau (LSB)** at the **House of Representatives**. It demonstrates how a centralized, QR-based visitor access system could work in practice, replacing part of the manual process currently used to validate visitor entry across the HOR complex.

Today, visitor access relies on physical, color-coded ID cards assigned per building (e.g. a red "VISITOR 0001" card for North Wing), checked manually by security personnel against a physical logbook. That process has no automatic way of catching a visitor authorized for one building attempting to enter a different one.

This prototype implements a simple core mechanism: every visitor pass is tied to a unique **QR token**. Scanning it at a building's terminal checks that token against a central database and instantly returns a result — **AUTHORIZED**, **UNAUTHORIZED**, **BLOCKED**, **EXPIRED**, **REVOKED**, or **INVALID** — while logging every scan attempt, successful or not, to a centralized, searchable, exportable audit trail. It is meant to _supplement_ existing guards and physical passes, not replace them.

> **Note:** The UI is still a work in progress. This README documents the backend/data model and known issues as of the current codebase, which has moved well past the state described in earlier drafts of this document (no auth, "MULTI" building terminology, day-passes-only).

---

## Tech Stack

- **Laravel** (PHP ^8.3, `laravel/framework` ^13.17) — backend framework, routing, ORM
- **MySQL** — persistent storage (configured via `.env`; SQLite is also supported out of the box for local/dev use, and is what's currently checked in at `database/database.sqlite`)
- **Blade** — server-rendered views (no separate SPA frontend)
- **Vite + Tailwind CSS 4** — asset bundling and styling
- **endroid/qr-code** — server-side QR code generation for printable passes
- **html5-qrcode** (JS) — webcam-based QR scanning in the browser
- **Pest / PHPUnit** — testing

---

## Key Features

- **Authentication & roles** — session login (`AuthController`) gated on an `hrep_id` + password + selected role (`admin` or `guard`). Guards additionally lock in a single building for their session (`select-building` screen or inline on login); admins skip that step entirely. All routes except `/login` require auth, and the log-purge routes additionally require the `admin` middleware.
- **Single-building passes** — 6 seeded buildings (North Wing, South Wing, RVM Building, North Gate, Main Building, South Wing Annex), 5 passes each, with a unique QR token per pass.
- **North Gate Access ("Multi-Building") passes** — a visitor can be issued one pass authorized across several buildings at once, tracked via a `pass_building` pivot table. These passes are nominally homed under the **North Gate** building row purely for pass-number bookkeeping (North Gate itself was retired as a physical scan location — see Known Issues); the pivot is the real source of authorization truth. Reassigning an unassigned multi pass reuses its existing QR token rather than minting a new one, since these are meant to be printed onto physical PVC cards. Only admins can issue or edit these.
- **Day vs. long-term passes** (`pass_class`) — day passes auto-expire at a nightly 7:00 PM cutoff; long-term passes carry an `expected_return_date` capped at 30 **working days** (HOR's compressed Mon–Thu week) and auto-expire once that date passes. Both are enforced by the `passes:daily-sweep` scheduled command.
- **In/Out occupancy tracking** — the system remembers which building a visitor is currently inside (`current_building_id` / `checked_in_at`). Scanning IN at a building "checks in" the pass; the visitor must scan OUT of that building before they're allowed to scan IN anywhere else (`BLOCKED` result if they try). A pass checked in for 16+ hours (`STALE_OCCUPANCY_HOURS`) is treated as a fresh entry on its next scan rather than blocking forever.
- **Reminder emails** — if a visitor supplied an email, the daily sweep queues a "you're still checked in" reminder (`MissingEgressReminder`) and, for long-term passes nearing their return date, a "your pass is expiring soon" reminder (`PassExpiringSoon`).
- **Visitor photo + ID photo capture** — webcam snapshots for both the visitor and their ID can be captured at registration time and stored per-pass, returned by the scanner endpoint for the guard to visually confirm identity.
- **ID type + reference tracking, structured name/gender/contact fields** — registration records first/middle/last name, gender, contact number, ID type/reference, office to visit, and vehicle info, not just a single free-text name.
- **Guard scanning terminal** — a live scanner page where a guard (building locked server-side to their session) or admin (free choice) scans a visitor's QR (webcam, USB scanner, or manual token paste) to get an instant AUTHORIZED/UNAUTHORIZED/BLOCKED/EXPIRED/REVOKED/INVALID decision.
- **Pass registry** — view all passes (guards see only their own building's single-building passes), register a visitor to an available pass, edit authorized buildings on a North Gate Access pass, unassign/return a pass to available stock, and view/print an individual pass's QR badge.
- **Audit log** — two parallel, searchable/filterable trails: scan attempts (`scan_logs`, now includes which logged-in user performed the scan) and pass registrations (`pass_registrations`, an append-only history of who was ever assigned to a pass and when/why it was released). Both export to CSV. Admin-only tools purge scan logs by date range or purge everything (guarded by a typed confirmation keyword); registrations are intentionally never purgeable.

---

## Folder Structure & What Each One Does

### `app/Http/Controllers/`

- **AuthController** — login (HREP ID + password + role, with guard building selection), logout.
- **BuildingSelectController** — the "pick your building" screen shown to guards (not admins) after login if they haven't locked one in yet this session.
- **ScannerController** — powers the guard/admin scanning terminal. Looks up the scanned QR token, checks pass status, building authorization, and current in/out state, then decides the result and writes it to the audit log.
- **PassController** — handles the pass registry: registering single-building and North Gate Access passes, editing a multi pass's authorized buildings, unassigning a pass, capturing/storing the visitor + ID photos, and rendering the printable QR badge view. Also enforces guard-vs-admin scoping (a guard only ever sees/acts on their own building's passes).
- **LogController** — handles viewing, searching/filtering, CSV-exporting, and purging (by date range or entirely) the scan-log audit trail, plus the separate read-only pass-registration history.
- **Filler.php** — not a real controller; see Known Issues / file-structure notes below.

### `app/Http/Middleware/`

- **EnsureAdmin** — 403s any request from a non-admin; guards the log-purge routes.
- **EnsureBuildingSelected** — redirects a guard who hasn't picked a building yet to the selection screen; admins pass through untouched.

### `app/Models/`

- **Building** — the seeded HOR buildings, each with a code, name, color, and badge template/QR-color design fields.
- **VisitorPass** — an individual pass: visitor info, ID type/reference, status, `pass_class` (day/long_term), QR token, current building (for in/out tracking), photo paths, reminder-sent flags, and (for North Gate Access passes) a many-to-many link to its authorized buildings.
- **PassRegistration** — an append-only record of one visitor's assignment to a pass: who they were, when they were registered, and when/why they were later unassigned. A pass can have many of these over its lifetime; `VisitorPass::openRegistration()` finds the currently-active one.
- **ScanLog** — a permanent, immutable-by-design record of every scan attempt (a snapshot of the visitor/pass/building at scan time), its result, reason, direction (in/out), and now which authenticated user performed the scan.
- **User** — `admin` or `guard` role, plus an `hrep_id` reference field.

### `app/Console/Commands/`

- **DailyPassSweep** — `php artisan passes:daily-sweep`, scheduled nightly at 19:00 (`routes/console.php`). Auto-expires due day/long-term passes and queues the two reminder emails.
- **GenerateMultiBuildingPasses** — `php artisan passes:seed-multi {count=5}`, a dev utility that generates sample North Gate Access passes (each randomly authorized for 2–3 buildings) for testing scanner validation logic.

### `database/migrations/`

Version-controlled schema changes, applied in order via `php artisan migrate`. Beyond the original buildings/passes/scan-logs tables, later migrations added: building design fields, multi-building pivot support, occupancy tracking, visitor photo storage, ID type tracking, `pass_class`/long-term/reminder fields, the `pass_registrations` table, retiring the old dedicated "MULTI" building into North Gate, user roles/`hrep_id`, `scanned_by_user_id` on scan logs, and structured visitor detail fields (first/middle/last name, gender, contact, office, vehicle).

### `database/seeders/`

- **DatabaseSeeder** — seeds the 6 real buildings (with colors and badge templates) and 5 available passes each with pre-formatted QR tokens (e.g. `HOR-20TH-NW-0001-SEC2026`). Safe to re-run — it updates existing rows rather than duplicating them (`updateOrCreate`).
- **UserSeeder** — seeds the two demo accounts, `admin@lsb.local` / `guard@lsb.local` (password `changeme123` for both). **Not currently wired into `DatabaseSeeder::run()`** — see Known Issues.

### `resources/views/`

Blade templates, organized by feature:

- `layouts/app.blade.php` — shared page shell (header/nav) every page extends.
- `auth/` — login and building-selection screens.
- `scanner/` — the guard/admin's live scanning terminal.
- `passes/` — the pass registry grid and the individual printable QR badge page.
- `logs/` — the searchable/exportable scan-log and pass-registration tables.
- `emails/` — the two reminder email templates.

### `public/images/passes/` and `public/images/buildings/`

Badge template PNGs and building-picker artwork used across the registry and scanner UI.

### `public/css/theme-govt.css`, `public/css/registration-modal.css`

The custom government/LSB visual theme and the registration-modal-specific styling used across the Blade views.

### `routes/web.php`

Maps URLs to controller actions. `/login` is guest-only; everything else requires `auth`, and everything except login/logout/building-select additionally requires `building.selected`. Log-purge routes additionally require `admin`.

### `config/`, `.env`

Standard Laravel app configuration and local environment secrets (DB credentials, app URL, debug mode). `.env` is not committed; copy `.env.example` and adjust as needed.

### `storage/`

Laravel's working directory — application logs (`storage/logs/laravel.log`), cached views, and visitor/ID photo uploads (`storage/app/public/visitor-photos/`, `storage/app/public/id-photos/`).

---

## Core Flow (How a Scan Actually Works)

1. A visitor is registered under **Passes** — assigned to an available single-building pass, or (admin only) issued a North Gate Access pass authorized for several buildings — capturing a visitor photo and ID photo.
2. A guard opens the **Scanner** (building locked to their session) or an admin opens it and picks any building.
3. The guard/admin scans the visitor's QR code (webcam, USB scanner, or manual token entry).
4. The scan is checked against the pass's status, its authorized building(s), and where it's currently checked in:
    - Not found → **INVALID**
    - `expired` / `revoked` status → **EXPIRED** / **REVOKED**
    - Not authorized for this building → **UNAUTHORIZED**
    - Currently checked into a _different_ building → **BLOCKED** (must scan out first)
    - Otherwise → **AUTHORIZED**, and the visitor is checked **in** (if not currently inside anywhere) or checked **out** (if currently inside this building)
5. The result, reason, and (if available) the visitor's photo are shown instantly and written to `scan_logs`, tagged with the authenticated user who performed the scan.
6. **Logs** shows the full searchable/filterable history for both scans and registrations, exportable to CSV, with optional purge tools (admin only).

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure DB_* in .env for MySQL, or leave DB_CONNECTION=sqlite for local/dev

php artisan migrate
php artisan db:seed
php artisan db:seed --class=UserSeeder   # not yet auto-run by db:seed — see Known Issues
php artisan storage:link                 # needed for visitor/ID photo uploads to be publicly viewable

npm install
npm run build   # or `npm run dev` while developing

php artisan serve
```

## Common Commands

```bash
php artisan migrate               # apply database schema changes
php artisan db:seed               # populate/update buildings + passes
php artisan db:seed --class=UserSeeder  # seed the two demo login accounts
php artisan passes:seed-multi 5   # generate sample North Gate Access passes for testing
php artisan passes:daily-sweep    # manually run the expiry/reminder sweep (normally scheduled at 19:00)
php artisan optimize:clear        # clear cached config/routes/views
php artisan tinker                # interactive shell to poke at the database directly
php artisan serve                 # run the app locally
```

---

## Known Issues / Bugs

Carried over from the last review pass and re-verified against this codebase, plus new findings from this pass. None of these are fixed yet in the uploaded code.

**Still open from before:**
vvv
- **`app/console/commands` is lowercase, but the classes inside declare `namespace App\Console\Commands;`.** Laravel's default command auto-discovery scans the literal path `app/Console/Commands` and silently registers nothing if it's missing — it doesn't error. On any case-sensitive filesystem (every real Linux server, including this sandbox), `php artisan passes:daily-sweep` and `php artisan passes:seed-multi` will both fail as "Command not defined," and the `Schedule::command('passes:daily-sweep')` line in `routes/console.php` will fail at schedule-run time. This will have tested fine on Windows/macOS (case-insensitive filesystems) but breaks in production. Fix: rename the directory to `app/Console/Commands`.
- **`DatabaseSeeder::run()` never calls `UserSeeder`.** A fresh `php artisan db:seed` (the exact command this README's setup section tells you to run) seeds buildings and passes but creates zero login accounts — `admin@lsb.local` / `guard@lsb.local` only exist if you separately run `php artisan db:seed --class=UserSeeder`. Fix: add `$this->call([UserSeeder::class]);` to `DatabaseSeeder::run()`.
- **`Building::$fillable` is still missing `template_image` and `qr_color_hex`**, even though both columns exist and `DatabaseSeeder` tries to set them via `updateOrCreate()`. Laravel silently drops any field not in `$fillable` on mass assignment (no strict mode is enabled in `AppServiceProvider::boot()`), so every building's badge template image and QR color are likely never actually being saved — which would show up as a missing/blank background on the printable pass badge (`passes/show.blade.php` reads exactly those two columns). Fix: add both to `Building::$fillable`.

**New findings this pass:**

- **North Gate leaks into three UI pickers it was explicitly designed to be hidden from.** `PassController::index()` and `ScannerController::index()` both correctly filter it out (`Building::where('code', '!=', 'NG')`, with an explicit comment: _"North Gate is internal bookkeeping only — never shown as a selectable/visible building anywhere in the UI"_). But `AuthController::showLogin()`, `BuildingSelectController::show()`, and `LogController::index()` all still query the full building list with no exclusion. A guard could end up assigned to "North Gate" as their scanning building (which no longer functions as a real scan location — it exists solely as the nominal home for North Gate Access/multi-building passes), and admins can filter logs by it. Fix: add the same `where('code', '!=', 'NG')` filter to all three, or better, centralize it as a scope/accessor on `Building` so it can't be forgotten again.
- **`hrep_id` is used as the actual login credential, despite its own migration comment saying otherwise.** The migration that adds it says _"reference only, not used for auth"_, but `AuthController::login()` passes it straight into `Auth::attempt(['hrep_id' => ..., 'password' => ...])`. It also has no unique constraint and is nullable — two users sharing (or both lacking) an `hrep_id` would make `Auth::attempt` match unpredictably. Decide which it is: either add a unique index and treat it as the real username, or keep it reference-only and authenticate on `email` instead.
- **Day-pass auto-expiry doesn't clear occupancy state, which can strand a visitor "inside" a building with no way out.** `DailyPassSweep` step 1 sets `status = 'expired'` on active day passes at the 7pm cutoff but never touches `current_building_id`/`checked_in_at`. If a visitor is still checked in when their pass expires, `ScannerController::scan()` checks `status === 'expired'` _before_ it ever reaches the occupancy/direction logic — so that visitor can't scan out, and the system will keep showing them as inside the building until `hasStaleOccupancy()`'s 16-hour window quietly self-heals it on their next scan attempt (regardless of building). Fix: have the sweep also clear `current_building_id`/`checked_in_at` (and probably log a system-generated "auto scan-out" event) when it expires a checked-in pass.
- **Missing-egress reminders ignore `STALE_OCCUPANCY_HOURS` entirely.** `DailyPassSweep` step 3 emails `MissingEgressReminder` to _anyone_ with `current_building_id` set and an email on file, every night at 7pm — including a visitor who checked in five minutes before the sweep ran. The 16-hour staleness threshold that the rest of the system uses (`VisitorPass::hasStaleOccupancy()`, referenced in `ScannerController`) isn't applied here. Fix: only send the reminder when `checked_in_at` is older than `STALE_OCCUPANCY_HOURS` (or some other explicit threshold), not unconditionally on every sweep.
- **`PassController::unassign()` doesn't reset `egress_reminder_sent_on` / `expiry_reminder_sent_on`.** Every other visitor-specific field gets cleared when a pass is returned to available stock, but these two survive. If the same physical pass is reassigned to a new visitor the same day (or before the sweep's date comparison rolls over), the new visitor could silently miss a reminder they should get, because the flag from the previous visitor is still sitting there. Fix: add both to the reset array in `unassign()`.

**Minor / cleanup:**

- `app/Http/Controllers/Filler.php` is not valid PHP (no `<?php` tag, no class/namespace) — looks like stray placeholder content that ended up in `app/Http/Controllers/`. Composer's autoload classmap generation will just skip it silently since it declares no class, so it's harmless at runtime, but it should be deleted.
- `resources.zip` sits at the project root — looks like a leftover archive, not something that should be committed.

---

## A Note on File Structure

This is a lower priority than the bugs above, but a few things would make the repo easier to navigate as it grows:

- **`app/console/commands` → `app/Console/Commands`** — beyond fixing the bug above, matching Laravel's conventional casing means future contributors (and IDEs/static analysis tools) won't be confused about where commands live.
- **Split `resources/views/passes/`** — the registry, the registration form/modal, and the printable badge are three fairly different concerns currently living close together; as the UI work continues, consider `passes/index.blade.php`, `passes/registration-modal.blade.php` (partial), and `passes/badge.blade.php` (renamed from `show.blade.php` for clarity, since "show" reads more like a generic detail page than a printable badge).
- **`app/Mail/`** is fine as-is, but once there are more than two mail classes, grouping by purpose (`app/Mail/Reminders/`) may help.
- Consider moving the two CSS files in `public/css/` into `resources/css/` and importing them through Vite like `app.css` already is, rather than mixing Vite-managed and directly-served stylesheets — this also means they'd get versioned/cache-busted automatically in production.

---

## Status & Limitations

This is a prototype built for an internship program, not a production-ready system:

- Authentication now exists (see Key Features), but it's session-based with no password reset flow, no rate limiting on login attempts, and role/building assignment is fully trusted from the session — there's no re-verification against the database on each request beyond the middleware checks described above.
- Visitor and ID photos are stored unencrypted on local disk (`storage/app/public`).
- See **Known Issues** above for specific bugs that should be fixed before this goes anywhere near a real deployment.
- Intended to run on a trusted local network (e.g. within LSB's own infrastructure), supplementing — not replacing — existing guards and physical passes.
