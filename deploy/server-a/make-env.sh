#!/bin/sh
# Creates .env, files.env, media.env and db-init/ for the first start — only if they don't exist.
# Secrets (DB passwords, app keys, internal token) are generated here on the server.
# Bucket keys are read from stdin as KEY=VALUE lines (BUCKET_*), never passed as arguments.
set -e
cd "$(dirname "$0")"
umask 077

if [ -f .env ]; then echo ".env already exists — leaving all settings untouched."; exit 0; fi

rand() { openssl rand -hex 24; }
appkey() { echo "base64:$(openssl rand -base64 32)"; }

# Bucket settings from stdin
BUCKET=$(grep -E '^BUCKET_[A-Z_]+=' || true)
[ -n "$BUCKET" ] || { echo "No BUCKET_* settings received on stdin"; exit 1; }

DB_ROOT=$(rand); DB_FILES=$(rand); DB_MEDIA=$(rand); INTERNAL=$(rand)

cat > .env <<EOF
FILES_DOMAIN=new.smkoacademy.ir
MEDIA_DOMAIN=new-media.smkoacademy.ir
DB_ROOT_PASSWORD=$DB_ROOT
EOF

mkdir -p db-init
cat > db-init/01-databases.sql <<EOF
CREATE DATABASE IF NOT EXISTS smko_files CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS smko_media CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'smko_files'@'%' IDENTIFIED BY '$DB_FILES';
CREATE USER IF NOT EXISTS 'smko_media'@'%' IDENTIFIED BY '$DB_MEDIA';
GRANT ALL PRIVILEGES ON smko_files.* TO 'smko_files'@'%';
GRANT ALL PRIVILEGES ON smko_media.* TO 'smko_media'@'%';
FLUSH PRIVILEGES;
EOF

common() {
cat <<EOF
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=en
LOG_CHANNEL=stack
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
SESSION_DRIVER=database
SESSION_LIFETIME=720
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
SMKO_DISK=bucket
SMKO_BUCKET_FORCES_ATTACHMENT=true
TELEGRAM_BOT_USERNAME=SAFilesbot
# Filled in when the bot moves to bot.smkoacademy.ir:
SMKO_TELEGRAM_RELAY_URL=
APP_INTERNAL_TOKEN=$INTERNAL
$BUCKET
EOF
}

{ echo "APP_NAME=\"SMKO Files\""; echo "APP_KEY=$(appkey)"; echo "APP_URL=https://new.smkoacademy.ir"
  echo "DB_DATABASE=smko_files"; echo "DB_USERNAME=smko_files"; echo "DB_PASSWORD=$DB_FILES"
  echo "SMKO_PUBLIC_MODE=false"; common
  echo "SMKO_STORAGE_PREFIX=smko-files/downloads"; } > files.env

{ echo "APP_NAME=\"SMKO Media\""; echo "APP_KEY=$(appkey)"; echo "APP_URL=https://new-media.smkoacademy.ir"
  echo "DB_DATABASE=smko_media"; echo "DB_USERNAME=smko_media"; echo "DB_PASSWORD=$DB_MEDIA"
  echo "SMKO_PUBLIC_MODE=true"; common
  echo "SMKO_STORAGE_PREFIX=smko-media/downloads"; } > media.env

# The bucket prefix line from stdin must not override the per-site one above.
for f in files.env media.env; do
  awk -F= '!($1 in seen) || $1!="SMKO_STORAGE_PREFIX" {seen[$1]=1; print}' "$f" > "$f.tmp" && mv "$f.tmp" "$f"
done

chmod 600 .env files.env media.env db-init/01-databases.sql
echo "Settings created: .env, files.env, media.env, db-init/ (secrets generated on this server)."
