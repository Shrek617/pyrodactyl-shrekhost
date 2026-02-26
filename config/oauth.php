<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OAuth Providers
    |--------------------------------------------------------------------------
    |
    | Define OAuth providers with their UI settings. A provider is automatically
    | enabled when its client_secret is configured in config/services.php.
    |
    | To add a new provider:
    | 1. Install the Socialite provider package (composer require)
    | 2. Register the event listener in EventServiceProvider
    | 3. Add credentials to config/services.php
    | 4. Add UI config below
    | 5. Set env variables
    |
    | Types:
    |   'redirect' — standard OAuth redirect flow (GitHub, Google, Discord, etc.)
    |   'telegram' — Telegram Login Widget (popup via JS)
    |
    */

    'providers' => [
        'telegram' => [
            'label' => 'Telegram',
            'color' => '#2481cc',
            'hover_color' => '#1d6fa5',
            'icon' => 'telegram',
            'type' => 'telegram',
        ],

        'discord' => [
            'label' => 'Discord',
            'color' => '#5865F2',
            'hover_color' => '#4752c4',
            'icon' => 'discord',
            'type' => 'redirect',
        ],
    ],
];
