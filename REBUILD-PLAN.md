# SMKO Files — Rebuild Plan

Goal: replace the developer-owned website at **files.smkoacademy.ir** with one you own, **without losing any files, folders, students or access**, and without students noticing anything except (possibly) a password reset.

---

## 1. What we already have

| Piece | Where | Status |
|---|---|---|
| Media files (~5,100) | Your ParsPack bucket `smko-files`, stored as `downloads/<file_id>.<ext>` | Yours ✅ |
| Folder tree (986 folders), file names, types, descriptions, text posts | `smko-export/` (exported 2026-11-18) | Saved ✅ |
| Students (291) and access (843 grants) | `smko-export/students.csv`, `access.csv` | Saved ✅ |
| Telegram bot @SAFilesbot | You own it; code in `bot-server/` | Reusable as-is ✅ |
| Domain smkoacademy.ir | Yours | ✅ |
| Website code + database | Developer's server | **Not needed** — we rebuild |

**Key rule for the whole rebuild: keep every ID exactly as it is.** File IDs are the file names in the bucket, and the bot caches Telegram copies by file ID. Same IDs = nothing moves, nothing breaks.

---

## 2. What the new site must do

### Students
- Log in with their phone/ID number + password.
- See only the folders they have access to. Access on a folder covers everything inside it, and can be given on a sub-folder alone (e.g. one student has *Adults / E4E1* but not *Adults*) — such students should see just that branch.
- Browse the folder tree in the same order as today.
- Open a file: preview / download (temporary link from the bucket), or **Get on Telegram**.
- Read text posts (the 1,116 TEXT items) directly on the page.

### Admin
- Create / rename / sort / delete folders.
- Upload files (straight to the bucket), edit titles and descriptions, add text posts.
- Add / edit / delete students; reset passwords.
- Grant / remove folder access (single or bulk, like today's Access List).
- Create temporary share links for a file (15 min … 7 days).

### Telegram
- The "Get on Telegram" button creates a one-time code and opens `t.me/SAFilesbot?start=<code>`.
- The site exposes the endpoint the bot already calls (see §5) — so the bot needs **no code changes**.

---

## 3. Technology

Same stack as today and as the bot, so any PHP/Laravel developer can maintain it:

- **Laravel 12** (PHP 8.3) + **MySQL 8**
- **Filament** for the admin panel (ready-made admin UI → much less custom code)
- Simple Blade + Tailwind pages for students
- ParsPack object storage via Laravel's S3 driver (new keys made by you)
- Hosting: one small Iranian VPS (2 vCPU / 4 GB is plenty), Docker, Caddy for HTTPS — the bot can live on the same server

---

## 4. Database

```
users            id, name, phone (login), telegram_id, password, is_admin,
                 must_change_password, created_at
folders          id, parent_id, name, sort_order
assets           id, folder_id, name, type (AUDIO|DOCUMENT|PHOTO|TEXT|VIDEO),
                 file_ext, size, description, text, sort_order, created_at
folder_user      folder_id, user_id                (access grants)
telegram_claims  token (32 chars), user_id, asset_id, expires_at, used_at
```

### Importing the export
A one-off import command reads `smko-export/smko-export.json` and inserts everything **with the original IDs**:

1. `folders` ← folders.csv (id, parent, name, order)
2. `assets` ← files.csv (id, folder, name, type, order, description, text, size, date)
3. `file_ext` ← filled by listing the bucket once (`downloads/290.mp3` → `mp3`), which also reports any file missing from the bucket
4. `users` ← students.csv (id, name, phone/ID), password = hash of `smko`, `must_change_password = true`
5. `folder_user` ← access.csv

Then raise the auto-increment counters above the highest imported ID.

---

## 5. Bot connection (must match exactly)

The existing bot calls:

```
POST https://files.smkoacademy.ir/internal/telegram/claim
Authorization: Bearer <APP_INTERNAL_TOKEN>
{ "token": "<32-char code>" }
```

New site responds:

| Case | Response |
|---|---|
| Valid, unused, not expired | `200 { "asset_id": 290, "hash": "<file version>", "filename": "Track 1.5.mp3", "download_url": "<temporary bucket link>" }` |
| Invalid / expired / used | `404` |

- Mark the code as used on first claim; codes expire after ~10 minutes.
- `hash` = anything that changes when the file is replaced (e.g. last-modified time). The bot uses it to know when its cached Telegram copy is stale.
- Set a strong `APP_INTERNAL_TOKEN` on **both** sides (it is currently missing from the bot's settings).

---

## 6. Passwords

Old passwords are only in the developer's database, so **every imported student starts with the password `smko`** (decided 2026-11-18).

- The import stores it hashed (bcrypt), like any other password — never in plain text.
- Every imported user gets `must_change_password = true`. On first login they go straight to a "choose your new password" page and can't open anything until they do.
- Admin can reset any student back to `smko` (which turns the flag on again).
- Login is rate-limited (e.g. 5 tries per minute per number) so the shared starting password can't be tried against every account quickly.
- Applies to the admin accounts too — change those first, on cutover evening.

---

## 7. Steps

| # | Step | Who | Est. |
|---|---|---|---|
| 1 | Get a VPS; create a subdomain for testing (e.g. `new.smkoacademy.ir`) | You | ½ day |
| 2 | Create **new** ParsPack access keys for the new site | You | 10 min |
| 3 | Build site: login, folder browsing, previews/downloads, text posts | Dev | 4–5 days |
| 4 | Build admin panel (Filament): folders, files, upload, students, access, share links | Dev | 3–4 days |
| 5 | Import command + bucket check; run import on test site | Dev | 1 day |
| 6 | Telegram claim endpoint + button; point a **test bot** at it | Dev | 1 day |
| 7 | You and 2–3 staff test everything on `new.smkoacademy.ir` | You | 2–3 days |
| 8 | Move bot to your own address (`bot.smkoacademy.ir`), re-enable its webhook security check, new bot token + your own TG_API_ID/HASH | Dev + You | ½ day |
| 9 | **Cutover** (below) | Both | 1 evening |

Roughly **2–3 weeks** for one developer.

### Cutover evening
1. Ask staff to stop uploading/editing on the old site.
2. Re-run the admin-panel export (a few minutes) and import it again, so nothing added since 2026-11-18 is lost.
3. Point `files.smkoacademy.ir` DNS to the new server.
4. Switch the bot's `APP_INTERNAL_BASE_URL` / webhook to the new setup.
5. Test: student login, a download, a Get-on-Telegram, an admin upload.
6. Tell students: log in with your usual number and the password `smko`, then choose your own password.

### After cutover
- In ParsPack, **delete the old access keys** the developer's site used.
- Change the old admin password; shut down anything still pointing at the old server.
- Keep `smko-export/` as a backup; set up automatic daily database backups on the new server.

---

## 8. Risks

| Risk | Mitigation |
|---|---|
| Some bucket files missing or misnamed | Bucket check in step 5 lists them before go-live |
| Changes on old site after export | Re-export on cutover evening |
| 362 IELTS / Examiner PPTX files have no description/size in the export | Names and types are saved; size comes from the bucket; descriptions can be re-added |
| Developer shuts the old site early | Export is already saved; bucket, bot and domain are yours |
| `binaries.ir` (bot address) not yours | Moved to your domain in step 8 |
