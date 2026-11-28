# SMKO Files

Replacement for the old **files.smkoacademy.ir** site. Laravel 12 + Filament 4 admin panel, media in the ParsPack bucket, Telegram delivery through the existing **@SAFilesbot** server (`../bot-server`, unchanged).

See `../REBUILD-PLAN.md` for the full plan.

## What's in it

| Part | Where |
|---|---|
| Student login (phone + password), forced password change, rate limiting | `app/Http/Controllers/AuthController.php` |
| Folder browsing, downloads, previews, text posts, "Get on Telegram" | `app/Http/Controllers/LibraryController.php`, `resources/views/library/` |
| Access rule (a grant covers a folder and everything inside) | `app/Support/FolderTree.php` |
| Endpoint the bot calls: `POST /internal/telegram/claim` | `app/Http/Controllers/TelegramClaimController.php` |
| Admin panel at `/admin` (folders, files, students, access, temp links) | `app/Filament/` |
| Import from the old site export | `php artisan smko:import` |
| Match files to bucket objects (`downloads/{id}.{ext}`) | `php artisan smko:sync-bucket` |
| Tests | `tests/Feature/SmkoTest.php` |

Every ID (file, folder, student) is the same as on the old site, so the bucket and the bot's cached Telegram copies keep working.

## Run locally (Windows, this PC)

PHP is in `F:\work\tools\php` (portable, not installed system-wide).

```bash
cd F:/work/files/smko-site
F:/work/tools/php/php.exe artisan serve --port=8010
```

Open http://127.0.0.1:8010 (port 8000 is used by another program on this PC). Local test accounts (`database/seeders/LocalTestAccountsSeeder.php`): `9000001` (admin) and `9000002` (student), password `smko`. Never run that seeder on the live server.

Run the tests:

```bash
F:/work/tools/php/php.exe artisan test
```

## Connect the bucket

In `.env` fill in **new** ParsPack keys (create them in the ParsPack panel; don't reuse the old site's keys):

```
BUCKET_ENDPOINT=https://cXXXXXX.parspack.net
BUCKET_NAME=...
BUCKET_ACCESS_KEY_ID=...
BUCKET_SECRET_ACCESS_KEY=...
SMKO_STORAGE_PREFIX=downloads
```

The old site's links looked like `https://c575538.parspack.net/c575538/smko-files/downloads/290.mp3`. Depending on how ParsPack names the bucket, either `BUCKET_NAME=c575538` with `SMKO_STORAGE_PREFIX=smko-files/downloads`, or `BUCKET_NAME=smko-files` with `SMKO_STORAGE_PREFIX=downloads`. Then:

```bash
php artisan smko:sync-bucket --report=bucket-report.csv
```

It fills in each file's extension and size and lists anything missing.

## Deploy (live server)

1. Server: PHP 8.3 (extensions: pdo_mysql, mbstring, intl, gd, zip, curl, openssl), MySQL 8, nginx or Caddy, Composer, Node 20+.
2. Copy this folder, then:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   cp .env.example .env && php artisan key:generate
   ```
3. In `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://files.smkoacademy.ir`, MySQL settings (`DB_CONNECTION=mysql` …), bucket keys, and a long random `APP_INTERNAL_TOKEN`.
4. `php artisan migrate --force`
5. Import: `php artisan smko:import /path/to/smko-export.json --admin=1` then `php artisan smko:sync-bucket`
6. `php artisan optimize`
7. PHP upload limits for big videos (php.ini): `upload_max_filesize = 2G`, `post_max_size = 2G`, `max_execution_time = 600`; nginx `client_max_body_size 2G;`

## Bot settings (in `../bot-server/.env`)

```
APP_INTERNAL_BASE_URL=https://files.smkoacademy.ir
APP_INTERNAL_TOKEN=<same value as this site's APP_INTERNAL_TOKEN>
```

No code changes needed. Recommended while you're there: re-enable the webhook check in `bot-app/routes/api.php`, move the bot to your own domain, and issue a new bot token in @BotFather.

## Passwords

Imported students start with `smko` and must choose their own at first login. Admins can reset anyone back to `smko` from **Students → Reset password**.

## Features added (Nov 2026)

| Feature | Where |
|---|---|
| "New" badges + "Recently added" (files from the last `SMKO_NEW_DAYS` days, until the student opens them) | `app/Support/NewFiles.php` |
| Telegram alerts about new files (one message per student, only files they can open) | `php artisan smko:notify-new-files` (scheduled) |
| Statistics: views, downloads, Telegram requests, logins, most-used files, inactive students | Admin dashboard, Files and Students lists |
| Expiring access (end date per student per folder) | Folder → Access tab; Students → bulk "Give access" |
| Background uploads with progress panel (bottom-left, keeps running while you move around the admin) | Folder → Files → **Upload files** |
| Re-uploads of an identical file reuse the stored one (MD5 fingerprint) | `AssetFiles::findByContent`, `php artisan smko:sync-hashes` |
| Copy / move files and folders, one or many (copies share the stored file) | Row menu (⋯) and bulk actions |
| Student import from Excel/CSV (+ template download) | Students → **Import from Excel** |
| Announcements: site banner and/or Telegram message, for everyone or chosen folders | Admin → Announcements |
| Monitoring: database, bucket, bot, queue, disk; Telegram alert to admins on change | Dashboard "System status", `php artisan smko:health` |
| Activity log of admin changes | Admin → Activity log |

### Extra server setup these need

```bash
# 1. Scheduler (new-file alerts, health checks, cleanup) — crontab -e
* * * * * cd /var/www/smko-site && php artisan schedule:run >> /dev/null 2>&1

# 2. Queue worker (announcements) — keep running with systemd or supervisor
php artisan queue:work --tries=3 --max-time=3600

# 3. Once after import: record file fingerprints for duplicate detection
php artisan smko:sync-hashes
```

`.env`:

```
SMKO_TELEGRAM_RELAY_URL=https://bot.smkoacademy.ir   # the bot server
APP_INTERNAL_TOKEN=<same long random value as in the bot's .env>
SMKO_NEW_DAYS=14
```

The bot server needs its updated code (`../bot-server/bot-app`: new `/api/internal/send` endpoint, webhook secret check turned back on). After deploying it, register the webhook **with** the secret:
`https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://bot.smkoacademy.ir/api/telegram/webhook&secret_token=<TELEGRAM_WEBHOOK_SECRET>`

## Public mode (second deployment, e.g. media.smkoacademy.ir)

Same code, one setting: `SMKO_PUBLIC_MODE=true` in `.env`.

- Anyone can browse, preview and download — no student accounts, no login.
- The **admin panel still needs a login** (otherwise anyone could upload or delete).
- Hidden: Students, folder Access, Telegram ("Get on Telegram", new-file alerts, Telegram announcements), Excel student import.
- Kept: folders, files, background uploads, duplicate detection, copy/move, banners (for everyone), statistics (anonymous), monitoring, activity log.
- Downloads are limited to 60 per minute per visitor to protect the server.

Setup on its own database:

```bash
php artisan migrate --force
php artisan smko:make-admin 0912xxxxxxx "Your name"      # asks for the password (hidden)
php artisan smko:import-media /path/to/media-export.json  # folders + files from media.smkoacademy.ir
php artisan smko:sync-hashes                               # fingerprints for duplicate detection
```

`media-export.json` was read from the public pages of media.smkoacademy.ir: 87 folders and 2,822 files, all already in the bucket under `smko-media/` (nothing is copied). Files whose object isn't in the bucket are skipped (`--include-missing` imports them anyway, shown as unavailable).
