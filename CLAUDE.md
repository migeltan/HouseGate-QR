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
- **Cannot run PHP, tests, a browser or the app** (no `vendor/`, no `php`). Verify by applying each snippet to a scratch copy with an exact-match script (each "Replace this" must match once), brace-count PHP/CSS, and `node --check` extracted inline JS. Say "syntax-checked" vs "not run". He runs `php artisan test` and sends screenshots/console output. Static-analysis warnings in his editor (Intelephense) are usually harmless; avoid by-reference closures.
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

- Buildings (`buildings.code`): NW North Wing, SW South Wing, RVM, MB Main Building, SWA South Wing Annex, **NG = Gate 6 (General Pass)**, a multi-access pass pool, never a destination (`Building::selectable()`; renamed from "North Gate", code stays `NG`). Hidden **OTH = Others** row arrives in step 3c (section 4).
- Stack: Laravel (composer `^12.0`; README says 13: trust composer) / PHP ^8.3, Blade + inline JS (no SPA), `endroid/qr-code`, Vite 8 + Tailwind 4, plus Tailwind 3 CLI for `public/css/tailwind.css` (`npm run build:css`). Page CSS in `public/css/*.css`. MySQL via `.env`; tests use in-memory SQLite. Session/cache/queue = `database`; mail = `log` by default. Deployed and reachable across networks (`APP_URL` must match the public URL or photos break). App-wide service worker `public/app-sw.js` caches static assets only.
- Scanner: Honeywell keyboard-wedge (auto-submit), webcam, or manual token entry. Sidebar "Registry" = `passes.index`.
- Seeds: admin `HREP-2020-0012`, guard `HREP-2024-0451`, password `changeme123`; `CongressmanSeeder` from `database/data/congressmen.csv`.

## 4. Decisions / rules already made (do not re-ask)

**Auth/roles:** login by `hrep_id` + password + role (`admin`/`guard`). Guards also pick a building (`session assigned_building_id`, `EnsureBuildingSelected`); panels follow the chosen building, not the account. Guards: own building's single-building passes only (`authorizeGuardScope`). Admin-only: logs purge, users, directory, inventory, Gate 6 (multi-building) passes. Aliases: `building.selected`, `admin`.

**Pass lifecycle:** `available` -> `active` -> `expired` | `revoked`; unassign returns to `available`. Token `HOR-20TH-{CODE}-{NNNN}-{HMAC8}`; 250 cards per building. **Day pass** expires at the first 19:00; **long-term max 7 days** (`MAX_LONG_TERM_DAYS`). `expireIfDue()` is idempotent/row-locked (every scan, `passes:expire` 5 min, `passes:daily-sweep` 19:00). `unassign` wipes visitor data + photo files, closes the registration (`returned`); `revoke` keeps data. Counts use `VisitorPass::BUCKET_SQL` (alias `bucket`; a card on hold counts as `hold`).

**Scan codes:** `INVALID`, `UNASSIGNED`, `EXPIRED`, `REVOKED`, `UNAUTHORIZED`, `BLOCKED` (must scan OUT first), `AUTHORIZED` in/out. Occupancy >16 h resets. Authorization = `isAuthorizedFor(buildingId)` (single: `building_id`; multi: `pass_building` pivot).

**Registration:** 3-step modal. Card picked inside the transaction with `lockForUpdate`; `registerSingleBuilding` / `registerMultiBuilding` return `[$pass, $transferred]`. Photos: new capture wins; on transfer without new capture the old photos carry over. Logs show only photos that still exist. **Specific-card picker (redone, see status):** default row "Card: Auto-assign", button opens filter + chip grid; hidden `manualCardToggle` + `manualCardSelect` (`pass_id`) keep the old form contract.

**Card custody (built):** `visitor_passes.holder_building_id` (NULL = vault); scope `inStock()`; guards issue only in-stock cards at their own desk; `MIN_DESK_STOCK = 50` (warning). Transfer to a different building holds the old card at the issuing desk (`closeForTransfer`); surrendered ID stays at the original desk (`id_held_building_id`, `id_returned_at/by`). Panel **Away cards** (`App\Services\AwayCards`, 12 h overdue). New modals must be added to the `ids` list in `layouts/app.blade.php`.

**BUSINESS CHANGE: Gate 6 / Others (decided, in progress):**

- NG is **Gate 6**: display name only (keep code `NG`, tokens, pink). Registry card/modal title **"Gate 6 (General Pass)"**; everywhere else just **"Gate 6"**. The 500+ cards are handled with the existing generate/Desk cards features (no code step; he said so).
- A Gate 6 pass is valid at **any mix of the main five and/or Others**. Exactly 1 main building only = normal card; **Others ticked, or 2+ buildings = Gate 6**. Example: LSB + North Wing = one Gate 6 pass valid at NW and Others.
- **Others = one category**, not separate entities (LIRMD, LSB, People's Center, Bank, Procurement...). Implementation: one hidden building row `OTH` "Others" in the pivot (scanner auth unchanged); no card pool of its own. Registration: sixth tile "Others" (subtitle lists examples) + optional **"Which place?"** list (LIRMD, LSB, People's Center, Bank, Procurement, "Another place..." typed), saved for logs only, not for authorization. Relabel the old free-text "Or: Other (not visiting a congressman)" to **"Or: Office / person (not a congressman)"**.
- **Backend built (3a, inert until `OTH` exists):** `Building::OTHERS_CODE`; scope `selectable()` = excludes NG+OTH (congressmen lists), `withPool()` = excludes OTH (Registry cards, `PassesLive`); `register()` computes `$hasOthers`/`$isMulti` (Others or 2+ buildings = Gate 6; Others requires a non-empty `others_place`, which also satisfies "congressman or Other"; specific card blocked for Gate 6); place stored in `pass_registrations.others_place` and shown in `office_to_visit` as "Others: X". `updateBuildings` has a route but no UI and is untouched.
- Scanner/guard login gets an **"Others" station**; Gate 6 itself stays a non-station.
- Issuing Gate 6 stays **admin-only for now**. **Proposed, pending the office's approval:** guards may issue Gate 6 only when Others is the sole destination; guards never issue other buildings' cards.

## 5. Where things are and status

```
routes/web.php | routes/console.php | bootstrap/app.php
app/Http/Controllers/  PassController (~1100 lines) ScannerController LogController CongressmanController
                       AccountController PassInventoryController AuthController BuildingSelectController ...
app/Models/ VisitorPass Building PassRegistration ScanLog Congressman AdminLog User
app/Services/ PassGenerator PhotoResizer AwayCards PassesLive     app/console/commands/ (lowercase dir)
resources/views/ passes/ (index ~1570, tab1-instructions, tab2-buildings, step1-3, away-modal) logs/ scanner/ layouts/app
public/css/ glass-status.css (modals, inventory, away, scanner pill), registration-modal.css (card picker), sidebar.css | database/migrations (to 2026_10_15)
```

- Heavy files: `grep -n` first. `passes/index.blade.php` holds most inline JS. In-place responses: `passActionResponse`, `registerResponse`, `passPayload`. Live polling: `window.hgLive` (layout) + `PassesLive`.
- Tests: `ExampleTest` uses `RefreshDatabase` + `withoutVite()`. On SQLite the `unassign_reason` enum lacks `transferred`. `app/console/commands` lowercase breaks autoload on Linux hosting.

**Pushed (master at `e561a56`):** everything through custody phases A-C, race fix, 7-day cap, live updates, hold fields. **Done this conversation, applied locally (verify/push):** (1) Registry counts fix: `->pluck('n', 'bucket')` in `PassController@index`; (2) scanner Live Feed pill `#camStatePill`; (3) specific-card picker redesign; (4) **Step 1 rename to Gate 6** (migration `2026_10_16_000001`, strings, registry title "Gate 6 (General Pass)"); (5) **Step 3a backend** (migration `2026_10_16_000002` adds `others_place`; Building scopes; `register()` rules; `officeSummary($c,$other,$place)`). Not started: 3b, 3c, 4, 5.

**Verify (run in the clone):**

```bash
cd /home/claude/migeltan/housegate && git log --oneline -3
grep -c "pluck('n', 'bucket')" app/Http/Controllers/PassController.php     # counts fix (expect 2)
grep -c "camStatePill" resources/views/scanner/index.blade.php             # live pill
grep -c "pickManualCard" resources/views/passes/index.blade.php            # card picker
grep -rc "Gate 6" resources/views/passes/step3-destination.blade.php       # rename step 1
ls database/migrations | grep -E "rename_north_gate|others_place"          # both 2026_10_16 migrations
grep -c "OTHERS_CODE" app/Models/Building.php app/Http/Controllers/PassController.php   # 3a
```

Gotchas: fixed `/passes/...` routes above `/passes/{pass}`; scheduler/queue need `schedule:work`/`queue:work`; `overflow: hidden` modal containers can be scrolled by focusing a hidden `sr-only` input (fixed with `position: relative` + `overflow: clip`).

## 6. Next (user's queue, one at a time; say "go" before each)

1. ~~Rename NG to Gate 6~~ done. 2. ~~Gate 6 stock to 500~~ dropped (existing feature covers it).
2. **"Others" in registration:** ~~3a backend~~ done. **3b (next): Destination UI** in `step3-destination.blade.php` + inline JS in `passes/index.blade.php` (`updateBuildingSelection`, `formReady`, `missingFields`, submit payload): "Others" tile with subtitle, "Which place?" list (LIRMD, LSB, People's Center, Bank, Procurement, "Another place..." typed) required when Others is ticked and sent as `others_place`; Others counts as Gate 6 for the hint and hides the specific-card picker; relabel "Or: Other" to "Or: Office / person (not a congressman)"; congressman requirement satisfied by the place; tile only renders if `OTH` is in `$buildings`. **3c: create the `OTH` row** (migration + seeder; skip pass generation for it, name "Others", neutral colour; `template_image`/`qr_color_hex` NOT NULL? check) = switches everything on. Behaviour change last.
3. **"Others" scanner/guard-login station** (after 3c these lists already pick up `OTH` automatically via `code != 'NG'`: AuthController, BuildingSelectController, ScannerController, login view; verify guard desk behaviour) + log labels for Gate 6 / Others.
4. **Only if the office approves:** guard issues Gate 6 for Others-only (server rule in `register()`: guards may submit multi only when `OTH` is the sole building).

- Dropped: speed work (he expects the office to optimise at deployment). If revived: self-host `html5-qrcode` (currently from unpkg), hover prefetch, drop `database` session driver.

## 7. Known risks (state them, do not hide them)

- Seeder password `changeme123`; seeded tokens use a static `...-SEC2026` suffix; Gate 6 cards minted on demand by `registerMultiBuilding` (only when no free card exists) use a guessable `PASS-MULTI-NNNN` token; pre-generating Gate 6 cards with the generate feature avoids it.
- A card handed over but never scanned back stays "away" with its ID outstanding; the Away cards list and 12 h overdue flag are the only safeguard. Transfers made before C1 have no ID desk recorded.
- Photos are deleted from disk when a card is returned while the registration keeps the path; retain-for-audit would be a policy change: ask first.
- Holder locks (`lockForUpdate`) are a no-op on SQLite. No real test suite; changes are verified manually. Composer says Laravel 12, notes say 13.
- Mail is queued: nothing is sent unless a worker runs and mail is configured. Old log snapshots keep the old "North Gate" text.
- Many places special-case `code === 'NG'` (counts, inventory, selectable, congressmen, scanner). Adding `OTH` needs the same care in each; grep `'NG'` first.

## 8. Updating this file

Only when the conversation nears ~90% usage, he says it is ending, or he asks: update sections 4-7 only, deliver the full file once as a downloadable `.md` (keep it dense, under ~130 lines, drop stale detail), and remind him to commit it to the repo root and push to `master` so a fresh clone sees it.
