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

---

## Tech Stack

- **Laravel** (PHP ^8.3, `laravel/framework` ^13.17) — backend framework, routing, ORM
- **MySQL** — persistent storage (configured via `.env`; SQLite is also supported out of the box for local/dev use)
- **Blade** — server-rendered views (no separate SPA frontend)
- **Vite + Tailwind CSS 4** — asset bundling and styling
- **endroid/qr-code** — server-side QR code generation for printable passes
- **html5-qrcode** (JS) — webcam-based QR scanning in the browser
- **Pest / PHPUnit** — testing

---

## Key Features

- **Single-building passes** — 6 seeded buildings (North Wing, South Wing, RVM Building, North Gate, Main Building, South Wing Annex), 5 passes each, with a unique QR token per pass.
- **Multiple Access ("Multi-Building") passes** — a visitor can be issued one pass authorized across several buildings at once, tracked via a `pass_building` pivot table. Reassigning an unassigned Multi card reuses its existing QR token rather than minting a new one, since these are meant to be printed onto physical PVC cards.
- **In/Out occupancy tracking** — the system remembers which building a visitor is currently inside (`current_building_id`). Scanning IN at a building "checks in" the pass; the visitor must scan OUT of that building before they're allowed to scan IN anywhere else (`BLOCKED` result if they try).
- **Visitor photo capture** — a webcam snapshot can be captured at registration time and stored per-pass (`photo_path`), and is returned by the scanner endpoint for the guard to visually confirm identity.
- **ID type + reference tracking** — each registration records the visitor's ID type and reference number, not just their name.
- **Guard scanning terminal** — a live scanner page where a guard selects their building and scans a visitor's QR (webcam, USB scanner, or manual token paste) to get an instant AUTHORIZED/UNAUTHORIZED decision.
- **Pass registry** — view all passes, register a visitor to an available pass, edit authorized buildings on a Multi pass, unassign/return a pass to available stock, and view/print an individual pass's QR badge.
- **Audit log** — searchable, filterable (by result, building, keyword) history of every scan attempt, exportable to CSV, with admin tools to purge logs by date range or purge everything (guarded by a typed confirmation keyword).

---

## Folder Structure & What Each One Does

### `app/Http/Controllers/`

- **ScannerController** — powers the guard scanning terminal. Looks up the scanned QR token, checks pass status, building authorization, and current in/out state, then decides the result and writes it to the audit log.
- **PassController** — handles the pass registry: registering single-building and Multi-Building passes, editing a Multi pass's authorized buildings, unassigning a pass, capturing/storing the visitor photo, and rendering the printable QR badge view.
- **LogController** — handles viewing, searching/filtering, CSV-exporting, and purging (by date range or entirely) the scan-attempt audit trail.

### `app/Models/`

- **Building** — the seeded HOR buildings, each with a code, name, and color; plus the dedicated `MULTI` building used as the nominal/primary building for Multi-Building passes.
- **VisitorPass** — an individual pass: visitor info, ID type/reference, status, QR token, current building (for in/out tracking), photo path, and (for Multi passes) a many-to-many link to its authorized buildings.
- **ScanLog** — a permanent, immutable-by-design record of every scan attempt (a snapshot of the visitor/pass/building at scan time), its result, reason, and direction (in/out).

### `app/console/commands/`

- **GenerateMultiBuildingPasses** — `php artisan passes:seed-multi {count=5}`, a dev utility that generates sample Multi-Building passes (each randomly authorized for 2–3 buildings) for testing scanner validation logic.

### `database/migrations/`

Version-controlled schema changes, applied in order via `php artisan migrate`. Beyond the initial `buildings`, `visitor_passes`, and `scan_logs` tables, later migrations added: building design fields, Multi-Building pivot support, occupancy tracking columns (on both passes and logs), visitor photo storage, and ID type tracking.

### `database/seeders/`

`DatabaseSeeder.php` seeds the 6 real buildings (with colors and badge templates) and 5 available passes each with pre-formatted QR tokens (e.g. `HOR-20TH-NW-0001-SEC2026`). Safe to re-run — it updates existing rows rather than duplicating them (`updateOrCreate`).

### `resources/views/`

Blade templates, organized by feature:

- `layouts/app.blade.php` — shared page shell (header/nav) every page extends.
- `scanner/` — the guard's live scanning terminal.
- `passes/` — the pass registry grid and the individual printable QR badge page.
- `logs/` — the searchable/exportable audit log table.

### `public/images/passes/`

The badge template PNGs (one per building) used as the printed pass background, with the QR code and pass number overlaid programmatically.

### `public/css/theme-govt.css`

The custom government/LSB visual theme (colors, status indicators, etc.) used across the Blade views.

### `routes/web.php`

Maps URLs to controller actions — e.g. `/` and `/scan` → `ScannerController`, `/passes*` → `PassController`, `/logs*` → `LogController`. **Note:** none of these routes currently have auth/role middleware applied.

### `config/`, `.env`

Standard Laravel app configuration and local environment secrets (DB credentials, app URL, debug mode). `.env` is not committed; copy `.env.example` and adjust as needed.

### `storage/`

Laravel's working directory — application logs (`storage/logs/laravel.log`), cached views, and visitor photo uploads (`storage/app/public/visitor-photos/`).

---

## Core Flow (How a Scan Actually Works)

1. A visitor is registered under **Passes** — assigned to an available single-building pass, or issued a new Multi-Building pass authorized for several buildings — optionally capturing a photo.
2. A guard opens the **Scanner**, selects which building entrance they're stationed at.
3. The guard scans the visitor's QR code (webcam, USB scanner, or manual token entry).
4. The scan is checked against the pass's status, its authorized building(s), and where it's currently checked in:
    - Not found → **INVALID**
    - `expired` / `revoked` status → **EXPIRED** / **REVOKED**
    - Not authorized for this building → **UNAUTHORIZED**
    - Currently checked into a _different_ building → **BLOCKED** (must scan out first)
    - Otherwise → **AUTHORIZED**, and the visitor is checked **in** (if not currently inside anywhere) or checked **out** (if currently inside this building)
5. The result, reason, and (if available) the visitor's photo are shown instantly to the guard and written to `scan_logs`.
6. **Logs** shows the full searchable/filterable history, exportable to CSV, with optional purge tools.

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure DB_* in .env for MySQL, or leave DB_CONNECTION=sqlite for local/dev

php artisan migrate
php artisan db:seed
php artisan storage:link   # needed for visitor photo uploads to be publicly viewable

npm install
npm run build   # or `npm run dev` while developing

php artisan serve
```

## Common Commands

```bash
php artisan migrate               # apply database schema changes
php artisan db:seed               # populate/update buildings + passes
php artisan passes:seed-multi 5   # generate sample Multi-Building passes for testing
php artisan optimize:clear        # clear cached config/routes/views
php artisan tinker                # interactive shell to poke at the database directly
php artisan serve                 # run the app locally
```

---

## Status & Limitations

This is a prototype built for an internship program, not a production-ready system:

- **No authentication/authorization** is implemented on any route yet — anyone with network access to the app can register passes, scan, or purge logs.
- Visitor photos are stored unencrypted on local disk (`storage/app/public`).
- Intended to run on a trusted local network (e.g. within LSB's own infrastructure), supplementing — not replacing — existing guards and physical passes.
