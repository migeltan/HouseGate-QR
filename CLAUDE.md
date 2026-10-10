# HouseGate-QR: context for Claude

Read this briefly, then continue from **Next**. Do not re-ask decided items or re-inspect files this file already describes. Conserve tokens.

## Start here (every new conversation)

1. Sync the clone (section 2). Read-only: no installs, no downloads.
2. Run the **Verify** commands (section 5) to see what has landed on `master`.
3. Reply with one short line: current state and what you will do next. Then work on **Next**.

Kickoff prompt: "Continue HouseGate QR. Clone master, read /CLAUDE.md, verify status, continue from Next."

## 1. How we work

- **Phases:** understand (no code) -> discuss (no code) -> implement only when he explicitly says so. **One feature at a time, in small steps** (a previous conversation died on a big one-shot rewrite). Present exact snippets grouped by file in implementation order: `Replace this:` (existing code, verbatim) then `with this:`. New file or heavy rewrite: exact path, one-line reason, full contents. No unrelated refactors.
- **The user applies changes himself** (Windows, Laravel Herd, `C:\Users\migel\Herd\HouseGate-QR`). No output files or present_files unless he asks (CLAUDE.md is the exception: send it as a downloadable `.md`). Code goes in chat.
- Match files by content (indentation is inconsistent, some blocks are oddly spaced). If a snippet cannot match, ask him to paste the file, then return it in full.
- State manual steps explicitly (commands, env, migrations, asset paths, URLs, imports). Wait for approval before the next step.
- **Cannot run tests, a browser or the app** (no `vendor/`, `node_modules/`). Verify what you can: `php -l` on PHP; for Blade/JS, apply each snippet to a scratch copy with an exact-match script (each "Replace this" must match once). Say "syntax-checked" vs "not run". He runs the app, `php artisan test`, and sends screenshots. Only scaffold tests exist.
- **Tokens:** inspect only relevant files (`grep -n`, `sed -n` ranges). Never cat whole big files (see section 5 sizes). Concise replies, at most one question per reply (prefer a stated default).
- Be direct; flag risks honestly.

## 2. Seeing the code

```bash
mkdir -p /home/claude/migeltan && cd /home/claude/migeltan
[ -d housegate ] || git clone --depth 1 https://github.com/migeltan/HouseGate-QR.git housegate
cd housegate && git fetch --depth 15 origin master && git reset --hard FETCH_HEAD && git log --oneline -5
```

- Read-only reference; never push. It shows only what is **pushed to `master`**; his local copy may be ahead (treat unpushed work as intentional, never "fix" it back). If he says he applied something not visible, ask him to push or paste it.
- Never run `composer install`, `npm install`, `pip install`. Ignore `resources.zip` (repo-root archive) and `README.md` prose beyond what is below.

## 3. Project

Visitor pass system for the House of Representatives, Office of the Sergeant at Arms, Legislative Security Bureau (LSB), Perimeter Security Group. Built by Migel H. Tan during the INSPIRE internship. Physical colour-coded cards, each with a QR token, replace manual checking.

**Business flow (loop):** visitor arrives at the North Screening Facility -> North Gate admin/guard registers them, asks questions, records ID -> assigns the building(s) per agenda (tour, congressman visit, ...) -> a card is assigned -> visitor enters a building; scanning gives AUTHORIZED or a denial depending on the card -> system audits every scan -> visitor returns the card -> admin/guard **unassigns** it (back to `available`) -> repeat.

- Buildings (`buildings.code`): NW North Wing (red), SW South Wing (orange), RVM (green), MB Main Building (blue), SWA South Wing Annex (yellow), **NG North Gate = the multi-access pass** (pink, never selectable as a destination: `Building::selectable()`).
- Stack: Laravel (composer says `^12.0`, README says 13: trust composer) / PHP ^8.3, Blade (server-rendered, plain JS inline in views, no SPA), `endroid/qr-code`, Vite 8 + Tailwind 4 for `resources/css|js`, **plus** a Tailwind 3 CLI build for `public/css/tailwind.css` (`npm run build:css`). Page CSS lives in `public/css/*.css`. DB: MySQL via `.env` (SQLite also works). Session, cache, queue drivers = `database`. Mail = `log` by default.
- Scanner hardware: Honeywell 2D scanner, keyboard-wedge, auto-submits; webcam scanning and manual token entry also exist.
- Seeds: `UserSeeder` admin `HREP-2020-0012`, guard `HREP-2024-0451`, both password `changeme123`; `DatabaseSeeder` buildings + passes; `CongressmanSeeder` from `database/data/congressmen.csv`.

## 4. Decisions / rules already made (do not re-ask)

**Auth/roles:** login by `hrep_id` + password + chosen role (`admin` or `guard`; role must match the account). Guards also pick a building at login (`session assigned_building_id`, enforced server-side by `EnsureBuildingSelected`; admins skip it). Deactivated users (`is_active`) are logged out immediately. Route middleware aliases: `building.selected`, `admin`.

- Guards: only their own building's single-building passes; cannot issue multi-building passes (`authorizeGuardScope`, register forces `building_ids`). Admin-only: logs purge, users, directory edits, pass inventory, North Gate passes and `updateBuildings`.

**Pass lifecycle** (`visitor_passes.status`): `available` -> `active` -> `expired` | `revoked`; unassign returns to `available`.

- Token: `HOR-20TH-{CODE}-{NNNN}-{HMAC8}` (`PassGenerator::token`, key = APP_KEY). 250 passes per building (`PER_BUILDING`).
- **Day pass** expires at the first 19:00 after issue (`DAY_PASS_CUTOFF`). **Long-term** needs `expected_return_date`, valid through end of that day, max 30 **working days** counted Mon-Thu only (`WORKING_WEEKDAYS`).
- `expireIfDue()` is idempotent and row-locked; called by the scanner on every scan (authoritative) and by `passes:expire` (every 5 min) and `passes:daily-sweep` (19:00). Expiry closes the open registration with `unassign_reason=auto_expired`; day passes also clear occupancy.
- `unassign` wipes visitor data + photos from the pass, detaches buildings, closes the open registration (`returned`); history stays in `pass_registrations`. `revoke` only flips status and clears presence (data kept for audit).

**Scan result codes** (`ScannerController::scan`, logged to `scan_logs` with snapshots): `INVALID`, `UNASSIGNED` (card `available`), `EXPIRED`, `REVOKED`, `UNAUTHORIZED` (building mismatch), `BLOCKED` (still inside another building: must scan OUT first), `AUTHORIZED` with `direction` in/out (toggles `current_building_id`/`checked_in_at`/`last_egress_at`). Stale occupancy >16 h is reset and treated as a fresh entry.

**Registration** (`PassController::register`, 3-step modal: camera -> information -> destination): purpose from fixed list (`Official Business`, `Financial/Medical Assistance`, `Visit`, `Others`+text); destination = congressmen (must belong to the chosen building(s)) and/or `office_other`; at least one required. Duplicate rule: exact ID match with an active pass blocks; fuzzy match needs `confirm_different_person`. **Transfer** (visitor swaps cards): `transfer_qr_token` of the old active card, identity must match, old registration closes with `transferred`, new one links via `transferred_from_registration_id`.

- Audit: `scan_logs` (+ `scan_verification_photos`), `pass_registrations` (append-only history, `buildings_snapshot`, `unassign_reason`), `admin_logs` via `AdminLog::record(action, subject, details)`.
- Reminders (need a visitor email): missing-egress daily, long-term expiring within 3 days once per return date; mail is **queued**.

## 5. Where things are and status

```
routes/web.php, routes/console.php (schedule), bootstrap/app.php (middleware aliases)
app/Http/Controllers/  PassController 951 lines | ScannerController | LogController | CongressmanController
                       AccountController | PassInventoryController | AuthController | BuildingSelectController
                       DirectoryServiceWorkerController | WhatsNewController
app/Models/  VisitorPass (expiry/occupancy logic) Building PassRegistration ScanLog ScanVerificationPhoto Congressman AdminLog User
app/Services/ PassGenerator, PhotoResizer      app/console/commands/ (lowercase dir) ExpirePasses DailyPassSweep GeneratePasses ...
resources/views/ passes/ (index 1573 lines, tab1-3, step1-3 modal steps, _pass-row, print-*, show)
  scanner/index 822 | account/edit 680 | congressmen/index 668 | layouts/app 419 | logs/ (index, tables, row-modal)
public/css/ (page CSS) | database/migrations (2026_08_22 ... 2026_10_09) | tests: scaffold only
```

- Heavy files: always `grep -n` first, then `sed -n 'a,bp'`; `passes/index.blade.php` holds most inline JS (modals, AJAX register/unassign/transfer).
- JSON/AJAX responses: `passActionResponse`, `registerResponse`, `passPayload` in PassController (used for no-refresh updates).
- `app/console/commands` is lowercase: fine on Windows, would break class autoload on Linux (case-sensitive) deployment.
- What-s New modal: `config/whatsnew.php` (`version_label` October 2026), dismissed via `/whats-new/dismiss`.

**Recently done (per his notes, Oct 7-9 2026):** modals system, inventory modal, QR-only/paper print options, skeleton loading, building passes modal restyle plan, Register Visitor modal beautify, congressman picker rework, pass transfer UX + submit lock, admin logs, user (de)activation.

**Verify (run in the clone):**

```bash
cd /home/claude/migeltan/housegate && git log --oneline -3
php -l app/Http/Controllers/PassController.php
grep -n "route('passes.unassign')\|passes/{pass}/unassign" -r resources routes | head -3
grep -c "expireIfDue" app/Http/Controllers/ScannerController.php
ls database/migrations | tail -3
```

Gotchas: fixed `/passes/...` routes must stay above `/passes/{pass}`; scheduler and queue only run with `php artisan schedule:work` / `queue:work` (or cron) locally.

## 6. Next (user's queue, one at a time)

0. **He will add 2 current system problems in the next message(s).** Start there; understand first, no code until he says so.
1. Unassign without a full page refresh.
2. Registration submit without reload (errors keep the form data).
3. Reduce the passes page weight (`passes/index.blade.php`, 1573 lines + rows for every building).
4. Pass expiry "not working" (no expired passes weeks after registering, no notifications). Hypothesis to check first, not a conclusion: scheduler/queue not running locally (`schedule:work`, `queue:work`), mail driver `log`, and that scanner path already expires on scan.
5. Later: building passes modal (View QR overlay, View Info modal), pass tracking across buildings.

## 7. Known risks (state them, do not hide them)

- Seeder default password `changeme123` for both accounts; `DatabaseSeeder` tokens use a static `...-SEC2026` suffix (guessable) unlike `PassGenerator` HMAC tokens.
- Verification/visitor/ID photos are stored on the public disk; unassign deletes the files but scan photos remain.
- No real test suite; changes are verified only by manual runs.
- Composer says Laravel 12, README/other notes say 13: do not assume framework-version-specific APIs.
- Mail is queued: nothing is sent unless a queue worker runs and mail is configured.
- Day-pass expiry is time-based (19:00), not tied to a scan-out; a visitor still inside is cleared by the sweep.

## 8. Updating this file

Only when the conversation nears ~90% usage or he says it is ending: update sections 4-7 only, deliver the full file once as a downloadable `.md` (keep it dense, under ~130 lines, drop stale detail), and remind him to commit it to the repo root and push to `master` so a fresh clone sees it.
