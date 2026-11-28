# Bot switch-over checklist (@SAFilesbot)

Changes in this copy of the bot:

- **Address:** `tel-bot-server.binaries.ir` → `bot.smkoacademy.ir` (set `BOT_DOMAIN` in `.env`; Caddy gets the HTTPS certificate automatically).
- **Telegram copies are kept forever** (`Cache::forever`); Redis now uses `volatile-lru`, so only entries with an expiry can be evicted.
- **Copies made for the old website are reused** after the switch (`php artisan telegram:adopt-legacy-cache`).
- **Webhook security check is back on** (the `X-Telegram-Bot-Api-Secret-Token` header must match `TELEGRAM_WEBHOOK_SECRET`).
- **New endpoint** `POST /api/internal/send` so the website can send messages (new-file alerts, announcements, monitoring alerts).

## Server

The live bot already runs on this server (82.115.21.124, `/root/bot-server`); its own Caddy holds
ports 80/443. During the move it answers on both addresses (`BOT_DOMAINS` in `.env`):
`bot.smkoacademy.ir, tel-bot-server.binaries.ir`. Once the webhook points at the new address and
everything works, set `BOT_DOMAINS=bot.smkoacademy.ir`.

Telegram's HTTPS API (api.telegram.org) is not reachable from this server; the bot talks to Telegram
through its local Bot API container (`bot-api`), which works.

## Steps

1. **DNS:** add an `A` record `bot.smkoacademy.ir` → the bot server's IP (DNS only, no CDN proxy). ✅ done
2. **Your own keys:**
   - @BotFather → `/revoke` → new token → `TELEGRAM_BOT_TOKEN` in `.env`. (The bot and its saved file copies stay the same; only the key changes.)
   - my.telegram.org → API development tools → your own `TG_API_ID` / `TG_API_HASH` (also in `docker-compose.yml` under `bot-api`).
   - A long random `APP_INTERNAL_TOKEN` — the same value goes in the website's `.env`.
   - A long random `TELEGRAM_WEBHOOK_SECRET`.
3. **Deploy:** copy this folder to the server, then `docker compose up -d --build`.
4. **Keep old copies** (once, before the new website goes live):
   ```bash
   docker compose exec bot-app php artisan telegram:adopt-legacy-cache --dry-run
   docker compose exec bot-app php artisan telegram:adopt-legacy-cache
   ```
5. **Register the webhook with the secret:**
   ```
   https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://bot.smkoacademy.ir/api/telegram/webhook&secret_token=<TELEGRAM_WEBHOOK_SECRET>
   ```
6. **Website `.env`:** `SMKO_TELEGRAM_RELAY_URL=https://bot.smkoacademy.ir` and the same `APP_INTERNAL_TOKEN`; `APP_INTERNAL_BASE_URL=https://files.smkoacademy.ir` in the bot's `.env` (already set).
7. **Test:** "Get on Telegram" on a file → the bot sends it; the admin dashboard shows "Telegram bot server: Reachable".
