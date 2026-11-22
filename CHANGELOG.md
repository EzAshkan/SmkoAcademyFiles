# Changelog

All notable changes to SMKO Files (the student file library at files.smkoacademy.ir) and its Telegram bot.

## [Unreleased]

### Added
- **Public mode** (`SMKO_PUBLIC_MODE=true`): a second deployment of the same site where anyone can browse and download without an account; only the admin panel needs a login. Students, folder access, Telegram features and the student import are hidden. Views and downloads are counted anonymously; downloads are limited to 60 per minute per visitor.
- `smko:make-admin` to create the admin login on a deployment without student accounts.
- `smko:import-media` to load the export of media.smkoacademy.ir (87 folders, 2,822 files already in the bucket under `smko-media/`).

## [1.0.0] — 2026-11-19 — Rebuild

The site was rebuilt from scratch so SMKO owns and controls it. All files, folders, students and access were carried over from the previous system with the **same IDs**, so files stay where they are in the ParsPack bucket and the bot's saved Telegram copies keep working.

### Migration
- Exported the previous admin panel: 986 folders, 5,129 files, 291 students, 843 access grants (`smko:import`).
- Recovered the exact bucket location of every file. Older files live at `smko-files/downloads/{id}.{ext}`, newer ones at `smko-files/assets/{folder}/{random}.{ext}` (`smko:import-paths`, verified with `smko:verify-keys`: 3,792 of 3,792 found).
- Found 221 files that were already broken on the previous site (download returned 404) and listed them for re-upload.
- Imported students start with the password `smko` and must choose their own at first login.

### Student site
- Same look as before (SMKO logo, purple folder tiles, file table, preview popup), rebuilt in Laravel 12.
- Previews in the popup: audio, video (mp4, webm, mov, mkv), photos, **PDFs**, and text posts. Kurdish and Persian lines read right-to-left.
- Previous / next arrows and keyboard shortcuts in the preview; links like `/assets/290` open the preview directly; old `/dashboard?folder_id=` links still work.
- Search inside large folders; phone-friendly layout (preview becomes a bottom sheet).
- Downloads save with the real title (e.g. "Track 1.5.mp3") instead of the storage name.
- **New** badges and a **Recently added** section (files from the last 14 days until opened).
- Access end dates shown on folder tiles.
- Announcement banners.
- "Get on Telegram" works with the existing @SAFilesbot.
- Login rate limiting (5 tries per minute per number).

### Admin panel (Filament)
- Folders, files, students and access, with folder tree navigation and drag-to-reorder.
- **Background uploads**: a panel in the bottom-left; set each file's title and description, press Upload, and keep working while it uploads (in pieces, with progress, cancel and retry).
- **Duplicate detection**: files already in the bucket are recognised by fingerprint (MD5) and reused instead of uploaded again.
- **Copy / move** files and folders, one or many; copies share the stored file.
- **Expiring access**: optional end date per student per folder.
- **Student import** from Excel / CSV, with a template and a "check only" mode.
- **Announcements**: site banner and/or Telegram message, to everyone or chosen folders.
- **Statistics**: active students, views, downloads, Telegram requests, 30-day chart, most-used files, inactive students.
- **Monitoring**: database, bucket, bot server, background jobs and disk space checked every 5 minutes; admins get a Telegram message when something breaks or recovers.
- **Activity log** of admin changes.
- Temporary share links (15 minutes to 7 days).
- Telegram alerts for new files are **off by default** (tick the box per upload).

### Telegram bot
- Moved from `tel-bot-server.binaries.ir` to `bot.smkoacademy.ir` (configurable with `BOT_DOMAIN`).
- Saved Telegram copies of files are now kept **forever** (were 60 days and could be evicted); copies made for the previous site are adopted on switch-over (`telegram:adopt-legacy-cache`).
- Editing a file's title or description no longer causes a re-upload to Telegram; replacing the file does.
- New `POST /api/internal/send` endpoint so the website can send alerts and announcements (the website's server can't reach Telegram directly).
- Webhook secret check turned back on.
- Telegram app keys moved out of `docker-compose.yml` into `.env`.

### Not included in this repository
Settings files (`.env`), the database, the student export and other personal data are deliberately kept out of git.
