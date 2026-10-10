# Title: context for Claude

Read this briefly, then continue from **Next**. Do not re-ask decided items or re-inspect files this file already describes. Conserve tokens.

## Start here (every new conversation)

1. Sync the clone (section 2). Read-only: no installs, no downloads.
2. Run the **Verify** greps (section 5) to see what has landed.
3. Reply with one short line: current state and what you will do next. Then work on **Next**.

Kickoff prompt: "Continue HouseGate QR. Clone dev, read /CLAUDE.md, verify status, continue from Next."

## 1. How we work

- **One phase/feature at a time, in small steps** (a previous conversation died doing a big UI rewrite in one shot). Present exact snippets grouped by file in implementation order: `Replace this:` (existing code, verbatim) then `with this:`. New file or a heavy rewrite: exact path, one-line reason, full contents. No unrelated refactors.
- **The user applies changes himself** (Windows, `C:\Users\migel\Herd\HouseGate-QR`). No output files or present_files unless he asks (CLAUDE.md is the exception: send it as a downloadable `.md`). Code goes in chat.
- His files may be Prettier-formatted (double quotes, semicolons) or not, per file (`usePolling.js` has no semicolons): match by content. If a snippet cannot match, ask him to paste the file, then return it in full.
- State manual steps explicitly (env vars, commands, deletions). Wait for approval before the next phase.
- **Cannot run the test suite or a browser** (no `vendor/`, `node_modules/`). Before sending, verify what you can: PHP with `php -l`; frontend by applying every snippet to a scratch copy with an exact-match script (each "Replace this" must match once) and syntax-checking with the global TypeScript at `/home/claude/.npm-global/lib/node_modules/typescript` (`ts.transpileModule`, jsx preserve). Say "syntax-checked" vs "not run". The user runs `php artisan test` and sends screenshots.
- **Tokens:** inspect only relevant files (`grep`, `sed -n` ranges), concise replies, at most one question per reply (prefer a stated default).
- Be direct; flag risks honestly. A violation is a review flag, never proof.

## 2. Seeing the code

```bash
mkdir -p /home/claude/migeltan && cd /home/claude/migeltan
[ -d anticheat ] || git clone --depth 1 --branch dev https://github.com/migeltan/AntiCheat.git anticheat
cd anticheat && git fetch --depth 15 origin dev && git reset --hard FETCH_HEAD && git log --oneline -5
```

- Read-only reference; never push. It shows only what is **pushed to `dev`**; if he says he applied something not visible, ask him to push or paste it.
- Ignore `backend/CLAUDE.md` / `backend/AGENTS.md` (boilerplate). Never run `composer install`, `npm install`, `pip install`.

## 3. Project

Web-based

## 4. Decisions already made (do not re-ask)

## 5. Where things are and status

```

```

- `components/admin/AdminLayout.jsx` is an unused duplicate of `pages/Admin/AdminLayout.jsx` (ask before deleting). `services/formParser.js` is a stale stub: do not touch.

**Pushed to (6edd931):**

**Applied locally, may not be pushed (verify):**
**Verify (run in the clone):**

## 6. Next (user's queue, one at a time)

1. ....

## 7. Known risks (state them, do not hide them)

## 8. Updating this file

Only when the conversation nears ~90% usage or he says it is ending: update sections 4-7 only, deliver the full file once as a downloadable `.md` (keep it dense, under ~130 lines, drop stale detail), and remind him to commit it to the repo root and push to `master` so a fresh clone sees it.
