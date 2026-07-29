<?php

return [
    'default' => env('BROADCAST_CONNECTION', 'log'),
    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => ['cluster' => env('PUSHER_APP_CLUSTER', 'ap2'), 'useTLS' => true],
        ],
        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
