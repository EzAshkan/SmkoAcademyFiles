<?php

return [
    // Filesystem disk holding the media (ParsPack object storage, see config/filesystems.php).
    'disk' => env('SMKO_DISK', 'bucket'),

    // Folder inside the bucket where files live as {id}.{ext}.
    // Old site: .../smko-files/downloads/290.mp3
    'prefix' => trim(env('SMKO_STORAGE_PREFIX', 'downloads'), '/'),

    // ParsPack currently adds "Content-Disposition: attachment" to every response from the bucket.
    // While that's on, links can't set a file name (two different headers → Chrome refuses).
    // Turn the setting off in ParsPack, then set this to false: downloads get proper names
    // ("Track 1.5.mp3") and PDFs preview straight from the bucket.
    'bucket_forces_attachment' => (bool) env('SMKO_BUCKET_FORCES_ATTACHMENT', true),

    // While the bucket forces "attachment", downloads up to this size pass through the site so
    // they get proper names. Bigger files go straight to the bucket (named like 290.mp4).
    // Largest file today: under 100 MB.
    'proxy_download_max_mb' => (int) env('SMKO_PROXY_DOWNLOAD_MAX_MB', 150),

    // Files added in the last N days get a "New" badge and show under "Recently added".
    'new_days' => (int) env('SMKO_NEW_DAYS', 14),

    // Minutes a student's download / preview link stays valid.
    'download_minutes' => (int) env('SMKO_DOWNLOAD_MINUTES', 45),

    'telegram' => [
        'bot_username' => env('TELEGRAM_BOT_USERNAME', 'SAFilesbot'),
        // Bot server address; the site sends Telegram messages through it (e.g. https://bot.smkoacademy.ir).
        'relay_url' => env('SMKO_TELEGRAM_RELAY_URL'),
        // Shared secret the bot sends as "Authorization: Bearer ..." (bot's APP_INTERNAL_TOKEN).
        'internal_token' => env('APP_INTERNAL_TOKEN'),
        // Minutes a "Get on Telegram" code stays valid.
        'claim_minutes' => (int) env('TELEGRAM_CLAIM_MINUTES', 10),
        // How long the bot gets to download the file from the bucket.
        'bot_download_minutes' => (int) env('TELEGRAM_BOT_DOWNLOAD_MINUTES', 120),
    ],
];
