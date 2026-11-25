# HouseGate-QR: context for Claude

Read briefly, then continue from **Next**. Do not re-ask decided items or re-inspect files described here. Conserve tokens.

## Start here (every new conversation)

1. Sync the clone (section 2). Read-only: no installs, no downloads.
2. Run **Verify** (section 5) to see what has landed on `master`.
3. Reply with one short line: current state and what you will do next. Then work on **Next**.

Kickoff prompt: "Continue HouseGate QR. Clone master, read /CLAUDE.md, verify status, continue from Next."

## 1. How we work

- **Phases:** understand (no code) -> discuss (no code) -> implement only when he says "go"/"next". **One feature at a time, small steps** (a past conversation died on a big one-shot rewrite). Give exact snippets grouped by file in implementation order: `Replace this:` (existing code, verbatim) then `with this:`. New file / heavy rewrite: exact path, one-line reason, full contents. No unrelated refactors.
- **He applies changes himself** (Windows, Laravel Herd, `C:\Users\migel\Herd\HouseGate-QR\housegate`). Code goes in chat; no output files unless he asks (CLAUDE.md is the exception: downloadable `.md`).
- Match files by content (indentation is inconsistent). If a snippet can't match, ask him to paste the file and return it in full. If a change alters live behaviour before its supporting screen exists, put the behaviour change last.
- State manual steps explicitly (commands, env, migrations, assets, URLs, imports). App is **deployed** (Render): remind him to run `php artisan migrate --force` and `view:clear` after pulling.
- **Cannot run PHP, tests, a browser or the app.** Verify snippets on a scratch copy with an exact-match script (each "Replace this" must match once), brace-count PHP/CSS, `node --check` inline JS. Say "syntax-checked" vs "not run". He runs `php artisan test` and sends screenshots/output. Intelephense warnings are usually harmless; avoid by-reference closures.
- **Tokens:** `grep -n` / `sed -n` ranges only; never cat big files. Concise replies, at most one question per reply (prefer a stated default). Be direct; flag risks and guesses honestly.

## 2. Seeing the code

```bash
mkdir -p /home/claude/migeltan && cd /home/claude/migeltan
[ -d housegate ] || git clone --depth 1 https://github.com/migeltan/HouseGate-QR.git housegate
cd housegate && git fetch --depth 15 origin master && git reset --hard FETCH_HEAD && git log --oneline -5
```

- Laravel app is in the repo's `housegate/` subfolder (root has README, CLAUDE.md, docs/). Paths below are relative to `/home/claude/migeltan/housegate/housegate`.
- Read-only; never push. Shows only what is pushed to `master`; his local copy is often ahead (treat unpushed work as intentional). If he says he applied something not visible, ask him to push or paste it.
- Never run `composer install`, `npm install`, `pip install`.

## 3. Project

Visitor pass system for the House of Representatives, Office of the Sergeant at Arms, Legislative Security Bureau, Perimeter Security Group. Built by Migel H. Tan (INSPIRE internship). Colour-coded cards with QR tokens replace manual checking.

**Flow:** visitor registers at the North Screening Facility (or building desk if arrived by car, surrendering valid ID) -> admin/guard assigns building(s) -> card assigned -> scan = AUTHORIZED or denial (all audited) -> card returned -> **unassign** (back to `available`). Moving buildings = **transfer credentials**.

- Buildings (`buildings.code`): NW North Wing, SW South Wing, RVM, MB Main Building, SWA South Wing Annex, **NG = Gate 6 (General Pass)** (multi-access pool, never a destination), hidden **OTH = Others** (planned, step 3c).
- Stack: Laravel (composer `^12.0`; README says 13) / PHP ^8.3, Blade + inline JS, `endroid/qr-code`, Vite 8 + Tailwind 4, plus Tailwind 3 CLI for `public/css/tailwind.css` (`npm run build:css`). Page CSS in `public/css/*.css`. MySQL via `.env`; tests use in-memory SQLite. Session/cache/queue = `database`. Deployed on Render (blueprint); `APP_URL` must match the public URL or photos break. Service worker `public/app-sw.js` caches static assets only.
- Scanner: Honeywell keyboard-wedge, webcam, or manual token. Sidebar "Registry" = `passes.index`.
- Seeds: admin `HREP-2020-0012`, guard `HREP-2024-0451`, password `changeme123`; `CongressmanSeeder` from `database/data/congressmen.csv`.

## 4. Decisions / rules already made (do not re-ask)

**Auth/roles:** login by `hrep_id` + password + role (`admin`/`guard`). Guards also pick a building (`session assigned_building_id`, `EnsureBuildingSelected`); panels follow the chosen building. Guards: own building's single-building passes only (`authorizeGuardScope`). Admin-only: logs purge, users, directory, inventory, Gate 6 passes. Aliases: `building.selected`, `admin`.

**Pass lifecycle:** `available` -> `active` -> `expired` | `revoked`; unassign returns to `available`. Token `HOR-20TH-{CODE}-{NNNN}-{HMAC8}`; 250 cards per building. Day pass expires at first 19:00; long-term max 7 days (`MAX_LONG_TERM_DAYS`). `expireIfDue()` idempotent/row-locked (every scan, `passes:expire` 5 min, `passes:daily-sweep` 19:00). `unassign` wipes visitor data + photos, closes registration (`returned`); `revoke` keeps data. Counts use `VisitorPass::BUCKET_SQL` (alias `bucket`; hold counts as `hold`).

**Scan codes:** `INVALID`, `UNASSIGNED`, `EXPIRED`, `REVOKED`, `UNAUTHORIZED`, `BLOCKED` (scan OUT first), `AUTHORIZED` in/out. Occupancy >16 h resets. Auth = `isAuthorizedFor(buildingId)` (single: `building_id`; multi: `pass_building` pivot).

**Registration:** 3-step modal. Card picked inside the transaction with `lockForUpdate`; `registerSingleBuilding` / `registerMultiBuilding` return `[$pass, $transferred]`. New photo capture wins; on transfer without new capture old photos carry over. Specific-card picker: default "Card: Auto-assign", button opens filter + chip grid; hidden `manualCardToggle` + `manualCardSelect` (`pass_id`).

**Card custody (built):** `visitor_passes.holder_building_id` (NULL = vault); scope `inStock()`; guards issue only in-stock cards at their own desk; `MIN_DESK_STOCK = 50` warning. Transfer to a different building holds the old card at the issuing desk (`closeForTransfer`); surrendered ID stays at original desk (`id_held_building_id`, `id_returned_at/by`). Panel **Away cards** (`App\Services\AwayCards`, 12 h overdue). New modals must be added to the `ids` list in `layouts/app.blade.php`.

**Gate 6 / Others (decided, in progress):**

- NG is **Gate 6**: display name only (keep code `NG`, tokens, pink). Registry card/modal title "Gate 6 (General Pass)"; elsewhere just "Gate 6". The 500+ cards come from existing generate/Desk cards features (no code step).
- Gate 6 pass valid at any mix of the main five and/or Others. Exactly 1 main building = normal card; Others ticked or 2+ buildings = Gate 6.
- Others = one category (LIRMD, LSB, People's Center, Bank, Procurement...). One hidden building row `OTH` "Others" in the pivot (scanner auth unchanged), no card pool. Registration: sixth tile "Others" + optional "Which place?" list (LIRMD, LSB, People's Center, Bank, Procurement, "Another place..." typed), saved for logs only. Relabel free-text "Or: Other (not visiting a congressman)" to **"Or: Office / person (not a congressman)"**.
- **Backend 3a built (inert until `OTH` exists):** `Building::OTHERS_CODE`; scopes `selectable()` (excludes NG+OTH) and `withPool()` (excludes OTH); `register()` computes `$hasOthers`/`$isMulti` (Others requires non-empty `others_place`; specific card blocked for Gate 6); stored in `pass_registrations.others_place`, shown in `office_to_visit` as "Others: X". `updateBuildings` has a route, no UI, untouched.
- Scanner/guard login gets an "Others" station; Gate 6 itself stays a non-station.
- Gate 6 issuing stays admin-only. **Proposed, pending office approval:** guards may issue Gate 6 only when Others is the sole destination.

## 5. Where things are and status

```
routes/web.php | routes/console.php | bootstrap/app.php
app/Http/Controllers/  PassController (~1100 lines) ScannerController LogController CongressmanController
                       AccountController PassInventoryController AuthController BuildingSelectController
app/Models/ VisitorPass Building PassRegistration ScanLog Congressman AdminLog User
app/Services/ PassGenerator PhotoResizer AwayCards PassesLive     app/Console/Commands/
resources/views/ passes/ (index ~1570, tab1-instructions, tab2-buildings, step1-3, away-modal) logs/ scanner/ layouts/app
public/css/ glass-status.css, registration-modal.css, sidebar.css | database/migrations (to 2026_10_16)
```

- `passes/index.blade.php` holds most inline JS. In-place responses: `passActionResponse`, `registerResponse`, `passPayload`. Live polling: `window.hgLive` + `PassesLive`.
- Tests: `ExampleTest` uses `RefreshDatabase` + `withoutVite()`. On SQLite the `unassign_reason` enum lacks `transferred`. Folder names must match namespaces exactly (Linux case-sensitive); `ConsoleCommandsTest` guards `app/Console/Commands`.

**Status:** master was at `e561a56` (custody A-C, race fix, 7-day cap, live updates). Applied locally, verify they are pushed: registry counts fix, scanner `#camStatePill`, card picker redesign, Gate 6 rename (migration `2026_10_16_000001`), Step 3a backend (migration `2026_10_16_000002`). **Email via Brevo: in progress** (see Next). Not started: Gate 6 steps 3b, 3c, 4, 5.

**Verify:**

```bash
cd /home/claude/migeltan/housegate/housegate && git log --oneline -3
grep -c "pluck('n', 'bucket')" app/Http/Controllers/PassController.php   # expect 2
grep -c "camStatePill" resources/views/scanner/index.blade.php
grep -c "pickManualCard" resources/views/passes/index.blade.php
ls database/migrations | grep -E "rename_north_gate|others_place"
grep -c "OTHERS_CODE" app/Models/Building.php app/Http/Controllers/PassController.php
grep -rIl "BREVO" . --exclude-dir=vendor --exclude-dir=node_modules | head   # email work
```

Gotchas: fixed `/passes/...` routes go above `/passes/{pass}`; scheduler/queue need `schedule:work`/`queue:work`; `overflow: hidden` modals can be scrolled by focusing a hidden `sr-only` input (fixed with `position: relative` + `overflow: clip`).

## 6. Next (one at a time; he says "go" before each)

1. **Brevo email (in progress, his side):** Brevo account exists. Steps left: add + verify a sender (account menu top right -> Senders, Domains & Dedicated IPs -> Senders -> Add a sender, confirm the email); create an API key (SMTP & API -> API Keys tab, starts `xkeysib-`); on Render set `BREVO_API_KEY` and `MAIL_FROM_ADDRESS` (the verified sender) in the service Environment tab. Then confirm reminder emails send (queue worker must run). Check via Verify grep what code exists.
2. **Registry tab usability:** make the visitor pass Registry grid (1-3 steps) useful and explain its many features, e.g. clickable info/help.
3. **Local dials + trunk line** for the congressmen DB: cross-reference the attached CSV with the project's congressmen directory CSV (`database/data/`). Ask him to re-attach the CSV if not visible.
4. Gate 6 steps 3b, 3c (create `OTH` row, Others tile, "Which place?" list, relabel), 4, 5.
5. Pre-real-use list: (a) no self-service password reset or forced change on first login, admins reset; (b) starter cards have guessable tokens, generate real stock from Pass inventory; (c) camera needs HTTPS and `html5-qrcode` loads from unpkg CDN (USB scanner/manual work offline; consider self-hosting); (d) visitor identities and ID photos are stored: Data Privacy Act of 2012 review needed.

- Dropped: speed work (office will optimise at deployment).

## 7. Known risks (state them, do not hide them)

- Seeder password `changeme123`; seeded tokens use static `...-SEC2026`; Gate 6 cards minted on demand by `registerMultiBuilding` use guessable `PASS-MULTI-NNNN` (pre-generate Gate 6 cards to avoid).
- A card handed over but never scanned back stays "away" with its ID outstanding; Away cards list + 12 h flag are the only safeguard. Transfers before C1 have no ID desk recorded.
- Photos are deleted on card return while the registration keeps the path; retain-for-audit would be a policy change: ask first.
- `lockForUpdate` is a no-op on SQLite. No real test suite; manual verification. Composer says Laravel 12, README says 13.
- Mail is queued: nothing sends unless a worker runs and Brevo/mail config is set. Old log snapshots keep "North Gate" text.
- Many places special-case `code === 'NG'` (counts, inventory, selectable, congressmen, scanner). Adding `OTH` needs the same care; grep `'NG'` first.

## 8. Updating this file

Only when the conversation nears ~90% usage, he says it is ending, or he asks: update sections 4-7 only, deliver the full file once as a downloadable `.md` (dense, under ~130 lines, drop stale detail), and remind him to commit it to the repo root and push to `master`.
