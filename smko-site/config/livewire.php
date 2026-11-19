<?php

// Only the upload settings differ from Livewire's defaults: allow large media
// files (up to 2 GB, the Telegram bot's limit) and give slow uploads time.
return [
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:2097152'],
        'directory' => null,
        'middleware' => 'throttle:60,1',
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 60,
        'cleanup' => true,
    ],
];
