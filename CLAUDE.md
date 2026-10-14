# HouseGate-QR: context for Claude

Read this briefly, then continue from **Next**. Do not re-ask decided items or re-inspect files this file already describes. Conserve tokens.

## Start here (every new conversation)

1. Sync the clone (section 2). Read-only: no installs, no downloads.
2. Run the **Verify** commands (section 5) to see what has landed on `master`.
3. Reply with one short line: current state and what you will do next. Then work on **Next**.

Kickoff prompt: "Continue HouseGate QR. Clone master, read /CLAUDE.md, verify status, continue from Next."

## 1. How we work

- **Phases:** understand (no code) -> discuss (no code) -> implement only when he explicitly says so ("go", "next"). **One feature at a time, in small steps** (a previous conversation died on a big one-shot rewrite). Present exact snippets grouped by file in implementation order: `Replace this:` (existing code, verbatim) then `with this:`. New file or heavy rewrite: exact path, one-line reason, full contents. No unrelated refactors.
- **The user applies changes himself** (Windows, Laravel Herd, `C:\Users\migel\Herd\HouseGate-QR`). No output files or present_files unless he asks (CLAUDE.md is the exception: downloadable `.md`). Code goes in chat.
- Match files by content (indentation is inconsistent). If a snippet cannot match, ask him to paste the file, then return it in full. If a change can alter live behaviour before its supporting screen exists, order the steps so the behaviour change comes last.
- State manual steps explicitly (commands, env, migrations, asset paths, URLs, imports). The app is **deployed**: remind him to run `php artisan migrate --force` and `view:clear` there after pulling.
- **Cannot run PHP, tests, a browser or the app** (no `vendor/`, no `php`). Verify by applying each snippet to a scratch copy with an exact-match script (each "Replace this" must match once), brace-count PHP, and `node --check` extracted inline JS. Say "syntax-checked" vs "not run". He runs `php artisan test` and sends screenshots/console output. Static-analysis warnings in his editor (Intelephense) are usually harmless; avoid by-reference closures.
- **Tokens:** `grep -n`, `sed -n` ranges only; never cat big files (sizes in section 5). Concise replies, at most one question per reply (prefer a stated default).
- Be direct; flag risks and unverified guesses honestly.

## 2. Seeing the code

```bash
mkdir -p /home/claude/migeltan && cd /home/claude/migeltan
[ -d housegate ] || git clone --depth 1 https://github.com/migeltan/HouseGate-QR.git housegate
cd housegate && git fetch --depth 15 origin master && git reset --hard FETCH_HEAD && git log --oneline -5
```

- Read-only reference; never push. It shows only what is **pushed to `master`**; his local copy is often ahead (treat unpushed work as intentional). If he says he applied something not visible, ask him to push or paste it.
- Never run `composer install`, `npm install`, `pip install`. Ignore `resources.zip` and README prose beyond what is below.

## 3. Project

Visitor pass system for the House of Representatives, Office of the Sergeant at Arms, Legislative Security Bureau, Perimeter Security Group. Built by Migel H. Tan (INSPIRE internship). Colour-coded cards with a QR token replace manual checking.

**Flow (loop):** visitor registers at the North Screening Facility (or, if they arrived by car, at the building desk, surrendering a valid ID) -> admin/guard assigns building(s) per agenda -> card assigned -> scan = AUTHORIZED or denial -> every scan audited -> visitor returns the card -> **unassign** (back to `available`) -> repeat. Moving to another building = **transfer credentials** (see custody).

- Buildings (`buildings.code`): NW North Wing, SW South Wing, RVM, MB Main Building, SWA South Wing Annex, **NG North Gate = multi-access pass** (never a destination: `Building::selectable()`).
- Stack: Laravel (composer `^12.0`; README says 13: trust composer) / PHP ^8.3, Blade + inline JS (no SPA), `endroid/qr-code`, Vite 8 + Tailwind 4, plus Tailwind 3 CLI for `public/css/tailwind.css` (`npm run build:css`). Page CSS in `public/css/*.css`. MySQL via `.env`; tests use in-memory SQLite. Session/cache/queue = `database`; mail = `log` by default. Deployed and reachable across networks (README has tunnel/deploy notes; `APP_URL` must match the public URL or photos break).
- Scanner: Honeywell keyboard-wedge (auto-submit), webcam, or manual token entry.
- Seeds: admin `HREP-2020-0012`, guard `HREP-2024-0451`, password `changeme123`; `CongressmanSeeder` from `database/data/congressmen.csv`.

## 4. Decisions / rules already made (do not re-ask)

**Auth/roles:** login by `hrep_id` + password + role (`admin`/`guard`). Guards also pick a building (`session assigned_building_id`, `EnsureBuildingSelected`); panels follow the chosen building, not the account. Guards: own building's single-building passes only (`authorizeGuardScope`). Admin-only: logs purge, users, directory, inventory, North Gate passes. Aliases: `building.selected`, `admin`.

**Pass lifecycle:** `available` -> `active` -> `expired` | `revoked`; unassign returns to `available`. Token `HOR-20TH-{CODE}-{NNNN}-{HMAC8}`; 250 cards per building.

- **Day pass** expires at the first 19:00 (`DAY_PASS_CUTOFF`). **Long-term: max 7 calendar days** from today (`VisitorPass::MAX_LONG_TERM_DAYS`, validated server + client).
- `expireIfDue()` is idempotent/row-locked: on every scan, `passes:expire` (5 min), `passes:daily-sweep` (19:00). `unassign` wipes visitor data + photo files, closes the registration (`returned`); history stays in `pass_registrations`. `revoke` keeps data.

**Scan codes:** `INVALID`, `UNASSIGNED`, `EXPIRED`, `REVOKED`, `UNAUTHORIZED`, `BLOCKED` (must scan OUT first), `AUTHORIZED` in/out. Occupancy >16 h resets.

**Registration:** 3-step modal. Card is picked **inside the transaction with `lockForUpdate`** (race fixed): `registerSingleBuilding` / `registerMultiBuilding` return `[$pass, $transferred]`. Duplicate ID rules unchanged. Photos: new capture wins; on a transfer with no new capture the visitor keeps the old card's photos (files not deleted). Logs show only photos that still exist ("Removed" otherwise).

**Card custody (built, phases A-C):**

- `visitor_passes.holder_building_id` = desk physically holding the card (NULL = vault). Scope `inStock()` = blank + holder == home building. Guards only issue in-stock cards at their own desk; empty -> block with message; admins may fall back to vault cards. `VisitorPass::MIN_DESK_STOCK = 50` (warning only). Admin screen: Manage Cards > **Desk cards** (`PassInventoryController::stock`, plain-language UI, confirms before taking cards back).
- **Transfer** to a different building: old card is blanked but **held away** at the desk that issued the new card (`closeForTransfer(old, oldReg, new)` returns holder name); same-building transfer returns to stock. Surrendered ID stays at the original desk: `pass_registrations.id_held_building_id` (inherited across transfers), `id_returned_at/by`.
- Panel **Away cards** (`App\Services\AwayCards`, `passes/away-modal.blade.php`): home desk sees "Card received" (`passes.receive`) then "ID handed back" (`passes.id-returned`, only after the card is back); the holding desk sees reminders; overdue after 12 h; unassign shows "Hand back to its own desk: ...". Admin log keys: `pass.transferred_away`, `pass.card_received`, `pass.id_returned`, `pass.stock_updated`.
- New modals must be added to the `ids` list in the shared script in `layouts/app.blade.php` (lifts to `<body>`, animation, scroll lock, Esc).

## 5. Where things are and status

```
routes/web.php | routes/console.php | bootstrap/app.php
app/Http/Controllers/  PassController (~1100 lines) ScannerController LogController CongressmanController
                       AccountController PassInventoryController AuthController BuildingSelectController ...
app/Models/ VisitorPass Building PassRegistration ScanLog Congressman AdminLog User
app/Services/ PassGenerator PhotoResizer AwayCards     app/console/commands/ (lowercase dir)
resources/views/ passes/ (index ~1570, tab1-instructions, tab2-buildings, step1-3, away-modal) logs/ scanner/ layouts/app
public/css/ glass-status.css (modals, inventory, away), registration-modal.css, sidebar.css | database/migrations (to 2026_10_12)
```

- Heavy files: `grep -n` first. `passes/index.blade.php` holds most inline JS. In-place responses: `passActionResponse`, `registerResponse`, `passPayload`.
- Tests: `ExampleTest` uses `RefreshDatabase` + `withoutVite()`. On SQLite the `unassign_reason` enum lacks `transferred` (widened only on MySQL): matters for future transfer tests.
- `app/console/commands` lowercase breaks autoload on Linux hosting.

**Pushed (as of last sync):** through B1/B2 (holder column, Desk cards). **Applied locally, may not be pushed (verify):** race fix, C1 (id custody migration `2026_10_12_000001`), photo carry-over, C2 (Away cards), `awayModal` in the layout ids list, 7-day cap, admin-log loading bar fix, register modal scroll fix.

**Verify (run in the clone):**

```bash
cd /home/claude/migeltan/housegate && git log --oneline -3
grep -c "holder_building_id" app/Http/Controllers/PassController.php      # B1
grep -c "id_held_building_id" app/Http/Controllers/PassController.php     # C1
grep -c "heldAwayChain" app/Http/Controllers/PassController.php           # C2
ls app/Services/AwayCards.php resources/views/passes/away-modal.blade.php
grep -c "awayModal" resources/views/layouts/app.blade.php
grep -c "MAX_LONG_TERM_DAYS" app/Models/VisitorPass.php
```

Gotchas: fixed `/passes/...` routes above `/passes/{pass}`; scheduler/queue need `schedule:work`/`queue:work`; `overflow: hidden` modal containers can be scrolled by focusing a hidden `sr-only` input (fixed with `position: relative` + `overflow: clip`).

## 6. Next (user's queue, one at a time)

1. He will state the problems from here.

## 7. Known risks (state them, do not hide them)

- Seeder password `changeme123`; seeded tokens use a static `...-SEC2026` suffix; North Gate cards minted by `registerMultiBuilding` use a guessable `PASS-MULTI-NNNN` token (unlike HMAC tokens).
- A card handed over but never scanned back stays "away" with its ID outstanding; the Away cards list and 12 h overdue flag are the only safeguard. Transfers made before C1 have no ID desk recorded and do not appear in the panel.
- Photos are deleted from disk when a card is returned (README says so) while the registration keeps the path; the log hides missing files. Retain-for-audit would be a policy change: ask first.
- Holder locks (`lockForUpdate`) serialise registrations briefly in the same building; they are a no-op on SQLite.
- No real test suite; changes are verified manually. Composer says Laravel 12, notes say 13.
- Mail is queued: nothing is sent unless a worker runs and mail is configured. Day-pass expiry is time-based (19:00).

## 8. Updating this file

Only when the conversation nears ~90% usage or he says it is ending: update sections 4-7 only, deliver the full file once as a downloadable `.md` (keep it dense, under ~130 lines, drop stale detail), and remind him to commit it to the repo root and push to `master` so a fresh clone sees it.
