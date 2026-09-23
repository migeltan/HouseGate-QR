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

> **Note:** The UI is still a work in progress. This README documents the backend/data model as of the current codebase and has been re-verified line-by-line against the code (not just prior drafts) — a few claims in earlier versions of this document turned out to be stale; see the corrections called out below.

---

## Tech Stack

- **Laravel** (PHP ^8.3, `laravel/framework` ^13.17) — backend framework, routing, ORM
- **MySQL** — persistent storage (configured via `.env`); **SQLite** is also supported out of the box for local/dev use (`DB_CONNECTION=sqlite`), but the SQLite file itself is **not** committed — it's gitignored (`database/.gitignore`) like any standard Laravel project, so you create it yourself on first setup (`touch database/database.sqlite`, see Setup below)
- **Blade** — server-rendered views (no separate SPA frontend; interactive behavior — the live QR scanner, camera capture, AJAX result handling — is plain JS embedded directly in the relevant Blade views, e.g. `resources/views/scanner/index.blade.php`)
- **Vite + Tailwind CSS 4** — asset bundling and styling
- **endroid/qr-code** — server-side QR code generation for printable passes
- **html5-qrcode** (JS) — webcam-based QR scanning in the browser
- **Pest / PHPUnit** — testing (currently only the default Laravel scaffold tests remain — see Known Issues)

---

## Key Features

- **Authentication & roles** — session login (`AuthController`) gated on an `hrep_id` + password + selected role (`admin` or `guard`). Guards additionally lock in a single building for their session (`select-building` screen or inline on login); admins skip that step entirely. All routes except `/login` require auth, and the log-purge routes additionally require the `admin` middleware.
- **Single-building passes** — 5 seeded buildings and 1 multi-access (North Wing, South Wing, RVM Building, North Gate, Main Building, South Wing Annex), 5 passes each, with a unique QR token per pass.
- **North Gate Access ("Multi-Building") passes** — a visitor can be issued one pass authorized across several buildings at once, tracked via a `pass_building` pivot table. These passes are nominally homed under the **North Gate** building row purely for pass-number bookkeeping (North Gate itself was retired as a physical scan location — it's excluded from every scan-destination and registration-destination building list); the pivot is the real source of authorization truth. Reassigning an unassigned multi pass reuses its existing QR token rather than minting a new one, since these are meant to be printed onto physical PVC cards. Only admins can issue or edit these (server-side enforced, not just hidden in the UI).
- **Day vs. long-term passes** (`pass_class`) — day passes auto-expire at a nightly 7:00 PM cutoff; long-term passes carry an `expected_return_date` capped at 30 **working days** (HOR's compressed Mon–Thu week) and auto-expire once that date passes. Both are enforced by the `passes:daily-sweep` scheduled command.
- **In/Out occupancy tracking** — the system remembers which building a visitor is currently inside (`current_building_id` / `checked_in_at`). Scanning IN at a building "checks in" the pass; the visitor must scan OUT of that building before they're allowed to scan IN anywhere else (`BLOCKED` result if they try). A pass checked in for 16+ hours (`STALE_OCCUPANCY_HOURS`) is treated as a fresh entry on its next scan rather than blocking forever.
- **Pass revocation** — admins/guards can revoke an active pass (`PassController::revoke`), immediately setting its status to `revoked` so any further scan attempt returns `REVOKED`.
- **Scan-time verification photo capture** — in addition to the visitor/ID photos captured at registration, the scanner can capture and store a live webcam snapshot _at the moment of each scan_ (`ScanVerificationPhoto`, linked to both the `scan_log` row and the pass), giving guards a photo taken at the actual point of entry/exit, not just at registration.
- **Reminder emails** — if a visitor supplied an email, the daily sweep queues a "you're still checked in" reminder (`MissingEgressReminder`) and, for long-term passes nearing their return date, a "your pass is expiring soon" reminder (`PassExpiringSoon`). **Note:** these are queued, not sent synchronously — see Known Issues for what that means for local testing.
- **Visitor photo + ID photo capture** — webcam snapshots for both the visitor and their ID can be captured at registration time and stored per-pass, returned by the scanner endpoint for the guard to visually confirm identity.
- **ID type + reference tracking, structured name/gender/contact fields** — registration records first/middle/last name, gender, contact number, ID type/reference, destination (congressman(s) and/or another office), and vehicle info, not just a single free-text name.
- **Guard scanning terminal** — a live scanner page where a guard (building locked server-side to their session) or admin (free choice) scans a visitor's QR (webcam, USB scanner, or manual token paste) to get an instant AUTHORIZED/UNAUTHORIZED/BLOCKED/EXPIRED/REVOKED/INVALID decision.
- **Pass registry** — view all passes (guards see only their own building's single-building passes), register a visitor to an available pass, edit authorized buildings on a North Gate Access pass, unassign/return a pass to available stock, revoke a pass, and view/print an individual pass's QR badge.
- **Congressman directory & per-building destination picker** — the registration form's destination is chosen from a roster of House members (`congressmen` table, loaded from `database/data/congressmen.csv` by `CongressmanSeeder`, keyed on the directory's `member_id`) instead of typed as free text. Each member belongs to the building their office room is in (room prefixes `NW`, `SW`, `SWA`, `RVM`, `MB`), and the picker only lists members from the building(s) ticked for the pass — for a North Gate Access pass, the union of the ticked buildings, grouped by building. A visitor can be going to one or several congressmen, or to an **Other** free-text office if the destination isn't a member. The building rule is enforced server-side in `PassController::register` (not just by the UI filter), and guards inherit it through their locked building. Picks are stored on the pass's registration (`pass_registration_congressman` pivot), so unassigning a pass leaves the history intact, and removing a building from a North Gate Access pass automatically drops that building's congressmen. `office_to_visit` is still filled with a readable one-line summary for the pass info modal.
- **Who they're visiting, at scan time** — the scanner status panel lists each congressman and room the visitor is going to (e.g. `Aquino, Jose II S. — SWA-113`), plus any "Other" office, read live from the pass's open registration (`ScannerController::scan()` returns it as `visiting`).
- **Audit log** — two parallel, searchable/filterable trails: scan attempts (`scan_logs`, includes which logged-in user performed the scan) and pass registrations (`pass_registrations`, an append-only history of who was ever assigned to a pass and when/why it was released). Both export to CSV. Admin-only tools purge scan logs by date range or purge everything, guarded by a typed confirmation keyword (`PURGE`, case-insensitive, checked server-side); registrations are intentionally never purgeable.

---

## Folder Structure & What Each One Does

### `app/Http/Controllers/`

- **AuthController** — login (HREP ID + password + role, with guard building selection), logout.
- **BuildingSelectController** — the "pick your building" screen shown to guards (not admins) after login if they haven't locked one in yet this session.
- **ScannerController** — powers the guard/admin scanning terminal. Looks up the scanned QR token, checks pass status, building authorization, and current in/out state, decides the result, writes it to the audit log, and optionally stores a live verification photo. It also returns a `visiting` list (the congressmen and rooms on the pass's open registration, plus any "Other" office) for the status panel.
- **PassController** — handles the pass registry: registering single-building and North Gate Access passes, editing a multi pass's authorized buildings, unassigning or revoking a pass, capturing/storing the visitor + ID photos, and rendering the printable QR badge view. Also enforces guard-vs-admin scoping (a guard only ever sees/acts on their own building's passes). It also passes the congressman roster to the registration modal, validates that every chosen congressman belongs to a submitted building, and keeps `office_to_visit` in sync as a summary of the picks (dropping congressmen from removed buildings when a North Gate Access pass is edited).
- **LogController** — handles viewing, searching/filtering, CSV-exporting, and purging (by date range or entirely) the scan-log audit trail, plus the separate read-only pass-registration history.

### `app/Http/Middleware/`

- **EnsureAdmin** — 403s any request from a non-admin; guards the log-purge routes.
- **EnsureBuildingSelected** — redirects a guard who hasn't picked a building yet to the selection screen; admins pass through untouched.

### `app/Models/`

- **Building** — the seeded HOR buildings, each with a code, name, color, and badge template/QR-color design fields.
- **Congressman** — one House member from the directory: `member_id`, name, representation type/detail (district or party-list), the building their office is in, floor, room, and an `is_active` flag (members dropped from a newer roster CSV are deactivated, not deleted, so old registrations still resolve).
- **VisitorPass** — an individual pass: visitor info, ID type/reference, status, `pass_class` (day/long_term), QR token, current building (for in/out tracking), photo paths, reminder-sent flags, and (for North Gate Access passes) a many-to-many link to its authorized buildings.
- **PassRegistration** — an append-only record of one visitor's assignment to a pass: who they were, when they were registered, and when/why they were later unassigned. A pass can have many of these over its lifetime; `VisitorPass::openRegistration()` finds the currently-active one. Each registration also links to the congressmen the visitor is going to (`congressmen()`), plus an optional free-text `office_other`.
- **ScanLog** — a permanent, immutable-by-design record of every scan attempt (a snapshot of the visitor/pass/building at scan time), its result, reason, direction (in/out), and which authenticated user performed the scan.
- **ScanVerificationPhoto** — the optional live photo captured at the moment of a specific scan, linked to that `ScanLog` row and (if matched) the `VisitorPass`.
- **User** — `admin` or `guard` role, plus an `hrep_id` reference field. Password is hash-cast automatically (`'password' => 'hashed'`), so seeders/forms can pass plaintext and it's stored hashed.

### `app/console/commands/` _(lowercase folder names — see Known Issues #5)_

- **DailyPassSweep** — `php artisan passes:daily-sweep`, scheduled nightly at 19:00 (`routes/console.php`). Auto-expires due day/long-term passes and queues the two reminder emails.
- **GenerateMultiBuildingPasses** — `php artisan passes:seed-multi {count=5}`, a dev utility that generates sample North Gate Access passes (each randomly authorized for 2–3 buildings) for testing scanner validation logic.

### `database/migrations/`

Version-controlled schema changes, applied in order via `php artisan migrate`. Beyond the original buildings/passes/scan-logs tables, later migrations added: building design fields, multi-building pivot support, occupancy tracking, visitor photo storage, ID type tracking, `pass_class`/long-term/reminder fields, the `pass_registrations` table, retiring the old dedicated "MULTI" building into North Gate, user roles/`hrep_id`, `scanned_by_user_id` on scan logs, structured visitor detail fields (first/middle/last name, gender, contact, office, vehicle), the `scan_verification_photos` table, the `congressmen` roster table, and the `pass_registration_congressman` pivot (plus `office_other` on `pass_registrations`).

### `database/seeders/`

- **DatabaseSeeder** — calls `UserSeeder` first, then seeds the 6 real buildings (with colors and badge templates) and 5 available passes each with pre-formatted QR tokens (e.g. `HOR-20TH-NW-0001-SEC2026`). Safe to re-run — it updates existing rows rather than duplicating them (`updateOrCreate`). It finishes by calling `CongressmanSeeder`.
- **UserSeeder** — seeds the two demo accounts, `admin@lsb.local` / `guard@lsb.local` (password `changeme123` for both). **It is wired into `DatabaseSeeder::run()`** — a plain `php artisan db:seed` seeds both users and buildings/passes in one command. (Earlier drafts of this README said it wasn't wired in yet; that's since been fixed in the code and the Setup section below no longer lists it as a separate step.)
- **CongressmanSeeder** — loads `database/data/congressmen.csv` (columns: `member_id, name, rep_type, rep_detail, building_code, floor, room`) into the `congressmen` table, upserting by `member_id`, so re-running it after a room change updates rather than duplicates. Members missing from a newer CSV are marked inactive. It needs the buildings to exist first, which is why `DatabaseSeeder` calls it last. Run it alone with `php artisan db:seed --class=CongressmanSeeder`.

### `resources/views/`

Blade templates, organized by feature:

- `layouts/app.blade.php` / `layouts/guest.blade.php` — shared page shells.
- `auth/` — login and building-selection screens.
- `scanner/` — the guard/admin's live scanning terminal (the bulk of the app's client-side JS lives inline here, ~600 lines in `index.blade.php`).
- `passes/` — the pass registry grid and the individual printable QR badge page.
- `logs/` — the searchable/exportable scan-log and pass-registration tables.
- `emails/` — the two reminder email templates.

### `public/images/passes/` and `public/images/buildings/`

Badge template PNGs and building-picker artwork used across the registry and scanner UI. Currently ~20 MB total, uncompressed — see the companion optimization notes for ways to shrink this.

### `public/css/theme-govt.css`, `public/css/registration-modal.css`

The custom government/LSB visual theme and the registration-modal-specific styling used across the Blade views.

### `routes/web.php`

Maps URLs to controller actions. `/login` is guest-only; everything else requires `auth`, and everything except login/logout/building-select additionally requires `building.selected`. Log-purge routes additionally require `admin`.

### `config/`, `.env`

Standard Laravel app configuration and local environment secrets (DB credentials, app URL, debug mode, mail/queue drivers). `.env` is not committed; copy `.env.example` and adjust as needed.

### `storage/`

Laravel's working directory — application logs (`storage/logs/laravel.log`), cached views, and visitor/ID/verification photo uploads (`storage/app/public/visitor-photos/`, `storage/app/public/id-photos/`, `storage/app/public/verification-photos/`).

---

## Core Flow (How a Scan Actually Works)

1. A visitor is registered under **Passes** — assigned to an available single-building pass, or (admin only) issued a North Gate Access pass authorized for several buildings — choosing the congressman(s) (or an "Other" office) they're visiting from a list limited to the selected building(s), and capturing a visitor photo and ID photo.
2. A guard opens the **Scanner** (building locked to their session) or an admin opens it and picks any building.
3. The guard/admin scans the visitor's QR code (webcam, USB scanner, or manual token entry), optionally capturing a live verification photo of the visitor at that moment.
4. The scan is checked against the pass's status, its authorized building(s), and where it's currently checked in:
    - Not found → **INVALID**
    - `expired` / `revoked` status → **EXPIRED** / **REVOKED**
    - Not authorized for this building → **UNAUTHORIZED**
    - Currently checked into a _different_ building → **BLOCKED** (must scan out first)
    - Otherwise → **AUTHORIZED**, and the visitor is checked **in** (if not currently inside anywhere) or checked **out** (if currently inside this building)
5. The result, reason, and (if available) the visitor's photo are shown instantly and written to `scan_logs`, tagged with the authenticated user who performed the scan. The status panel also lists the congressman(s) and room(s) the visitor is going to (shown live, not stored in `scan_logs`).
6. **Logs** shows the full searchable/filterable history for both scans and registrations, exportable to CSV, with optional purge tools (admin only).

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure DB_* in .env for MySQL, or set DB_CONNECTION=sqlite and:
touch database/database.sqlite

php artisan migrate
php artisan db:seed              # seeds demo users, buildings/passes AND the congressman roster in one go
php artisan storage:link         # needed for visitor/ID/verification photo uploads to be publicly viewable

npm install
npm run build   # or `npm run dev` while developing

php artisan serve
```

> To actually receive the reminder emails locally (not just see them queued), also run a queue worker in a second terminal — see Known Issues, item 1.

## Common Commands

```bash
php artisan migrate               # apply database schema changes
php artisan db:seed               # populate/update demo users + buildings + passes + congressman roster
php artisan db:seed --class=CongressmanSeeder   # reload just the roster after editing database/data/congressmen.csv
php artisan passes:seed-multi 5   # generate sample North Gate Access passes for testing
php artisan passes:daily-sweep    # manually run the expiry/reminder sweep (normally scheduled at 19:00)
php artisan queue:work            # process queued reminder emails (needed for emails to actually go out)
php artisan optimize:clear        # clear cached config/routes/views
php artisan tinker                # interactive shell to poke at the database directly
php artisan serve                 # run the app locally
```

---

## Known Issues / Bugs

Re-verified against the current codebase this pass. Findings marked **(new)** weren't in earlier drafts of this document.

1. **Reminder emails — root cause identified.** The mail-sending _code_ is correct (`DailyPassSweep` calls `Mail::to(...)->queue(...)` for both reminder types, and both Mailables/views exist). But `.env.example` ships `QUEUE_CONNECTION=database`, which means reminders are inserted into the `jobs` table and then sit there forever unless something actually processes the queue (`php artisan queue:work`, or a supervisor-managed worker in production). No worker is configured anywhere in this repo. Separately, `.env.example` ships `MAIL_MAILER=log`, so even with a worker running, "sending" an email in local/dev just writes it to `storage/logs/laravel.log` instead of delivering anywhere — that's expected for local testing, not a bug, but worth knowing before assuming email is broken. **(new — root cause)**
2. UI on PURGE needs to be fixed.
3. UI on QR fallback mode still looks like it does not belong there.
4. UI on the header, improvements and also improve the admin/personnel information there.
5. **Folder casing: `app/console/commands/`.** The scheduled commands live in an all-lowercase path, but their namespace is `App\Console\Commands`. This works on Windows/macOS (case-insensitive) and will break autoloading on a case-sensitive Linux server. Rename both folders to `app/Console/Commands/` (on Windows use a two-step `git mv` through a temporary name, since git won't see a case-only rename).
6. **Two stray files at the repo root:** `laravel` (a 0-byte empty file) and `resources.zip` (a ~10 KB stale duplicate of `resources/`). Neither is referenced anywhere; both are safe to delete.
7. **Only scaffold tests exist.** `ExampleTest` now checks `/login`, but nothing covers scan decision logic, the expiry sweep, login/role scoping, or the congressman building lock.
8. **Visit destination — known gaps.** (a) The visit list is shown live at scan time but not snapshotted into `scan_logs`, so the Logs panel/CSV don't show it and a past scan loses it once the pass is reassigned. (b) Passes registered before this feature have no congressmen attached, so the scanner's "Visiting" line is blank for them. (c) The roster is CSV-only (no admin screen), and a few rows were assigned by hand: one member has no room, and three rooms are shared by two members each.

---

## Planned Features / To-Do

> Not implemented yet — this section documents intent and design options.

### Government ID scanning (auto-fill from the ID)

**Goal.** When a guard captures the visitor's ID photo at registration (already supported), the system reads the card and pre-fills the visitor's basic details — full name (first/middle/last), ID type, ID number, date of birth, sex, and where present the address and expiry date — so the guard confirms instead of typing. Target the government-issued types already offered in the ID Type field first: Driver's License, UMID, Passport, SSS ID, PhilHealth ID, and PhilSys (National ID).

**How it would work.**

1. The registration modal already produces the ID photo as a base64 image. After capture, the browser POSTs it to a new endpoint (e.g. `POST /passes/scan-id`, handled by an `IdScanController`) that returns the extracted fields as JSON, each with a confidence score.
2. The modal fills the matching inputs (`first_name`, `middle_name`, `last_name`, `gender`, `id_type`, `id_ref`) and highlights low-confidence fields. The guard reviews and edits; nothing is auto-submitted, and manual entry always stays available.




3. The original ID photo is stored exactly as today. Raw OCR text is not persisted beyond the extracted fields.

**Technology options** (decide before building):

| Option                           | What it is                                                                                                   | Pros                                           | Cons                                                                                     |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------ | ---------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Machine-readable codes           | Decode the passport MRZ, or the QR/barcode on IDs that carry one (e.g. PhilSys)                              | Far more reliable than OCR where a code exists | Not every ID has one; confirm what each code exposes and whether reading it is permitted |
| Local OCR                        | Tesseract (via a PHP wrapper) or PaddleOCR/EasyOCR in a small Python service, plus per-ID-type parsing rules | Images never leave the LSB network             | Needs tuning per ID layout; accuracy depends on photo quality                            |
| Cloud document/ID AI             | e.g. Google Document AI, AWS Textract (AnalyzeID), Azure Document Intelligence                               | Strong accuracy on supported document types    | ID images leave the local network; Philippine ID coverage may be limited or uneven       |
| Hosted vision-language model API | Send the image with a structured-output prompt                                                               | Handles any layout without per-ID rules        | Same off-network concern, per-call cost, and occasional misreads that must be validated  |

**Suggested path.** Local-first and hybrid: decode an MRZ/QR when the ID has one, otherwise run local OCR with per-ID-type parsers. Put it behind a small `IdExtractor` interface so a cloud or vision provider can be swapped in later if LSB approves.

**Considerations.**

- **Privacy/security:** government IDs are sensitive personal data, and this system is meant to run on a trusted local network. Prefer on-premise processing, and get LSB/data-protection sign-off (Data Privacy Act of 2012) before anything is processed off-network. ID photos are already stored unencrypted on local disk (see Status & Limitations), which matters more once data is extracted from them.
- **Not identity verification:** reading text off a card does not detect fake or tampered IDs. The guard still compares the ID and the visitor's face.
- **Accuracy:** benchmark on a small set of sample IDs per type (with consent or redacted) before choosing a tool; start with the 2–3 most commonly presented types.
- **Capture quality:** add a framing guide and lighting hint to the existing ID camera step, since OCR quality depends heavily on the photo.

### Other to-do items

- Save the visit list (congressmen + rooms) as a snapshot on `scan_logs`, so the Logs panel and CSV export show who was visited.
- Give admins a way to manage the congressman roster without editing the CSV and re-seeding.
- Add real tests: login/roles, scan decision logic, the expiry sweep, and the congressman building lock.

---

## A Note on File Structure

The current layout is a standard, single-app Laravel structure (no dedicated `frontend/`/`backend/` split — Blade renders server-side and the scanner's client logic lives inline in its view). That's reasonable for a prototype this size, but as it grows, a few things would help:

- Extract the scanner's inline `<script>` block into `resources/js/scanner.js` (or a small set of modules) so it's linted, versioned, and testable like the rest of the JS, instead of living inside a Blade file.
- Fix the `app/console/commands/` casing (see Known Issues #5).
- Delete the stray files (`laravel`, `resources.zip` — Known Issues #6).
- Add indexes to `visitor_passes.status` and `visitor_passes.current_building_id`, and a composite index on `scan_logs (result, created_at)`, since both are hit on nearly every request and will matter once the audit log grows.

A fuller breakdown of restructuring options (including an optional frontend/backend split and a TypeScript migration path) is covered in the companion planning document, since that's a bigger design decision than a README should carry.

---

## Status & Limitations

This is a prototype built for an internship program, not a production-ready system:

- Authentication now exists (see Key Features), but it's session-based with no password reset flow, no rate limiting on login attempts, and role/building assignment is fully trusted from the session — there's no re-verification against the database on each request beyond the middleware checks described above.
- Visitor, ID, and verification photos are stored unencrypted on local disk (`storage/app/public`).
- See **Known Issues** above for specific bugs and cleanup items that should be addressed before this goes anywhere near a real deployment.
- Intended to run on a trusted local network (e.g. within LSB's own infrastructure), supplementing — not replacing — existing guards and physical passes.
