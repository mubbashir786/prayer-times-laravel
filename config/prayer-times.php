<?php

return [

    // Default city/coordinates used when none are passed explicitly.
    'default_location' => [
        'city' => env('PRAYER_TIMES_CITY', 'Islamabad'),
        'country' => env('PRAYER_TIMES_COUNTRY', 'Pakistan'),
        'latitude' => env('PRAYER_TIMES_LAT', 33.6844),
        'longitude' => env('PRAYER_TIMES_LNG', 73.0479),
        'timezone' => env('PRAYER_TIMES_TZ', 'Asia/Karachi'),
    ],

    // Aladhan calculation method ID.
    // 1 = University of Islamic Sciences, Karachi (common for Pakistan)
    // 2 = ISNA, 3 = MWL, 4 = Umm al-Qura, etc.
    'calculation_method' => env('PRAYER_TIMES_METHOD', 1),

    // Juristic school for Asr calculation: 0 = Shafi/Maliki/Hanbali, 1 = Hanafi
    'asr_school' => env('PRAYER_TIMES_ASR_SCHOOL', 1),

    // How long to cache a day's prayer times before re-fetching (in hours).
    'cache_ttl_hours' => 24 * 7, // cache a week at a time

    // Base URL for the Aladhan API.
    'api_base_url' => 'https://api.aladhan.com/v1',

    // Which prayers to track/notify for.
    'prayers' => ['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'],

    // Reminders: minutes before each prayer to send a notification.
    'reminders' => [
        'enabled' => env('PRAYER_TIMES_REMINDERS_ENABLED', false),
        'minutes_before' => 15,
        // Notification channels, e.g. ['mail', 'database', 'vonage']
        'channels' => ['database'],
    ],

    // Ramadan-specific features.
    'ramadan' => [
        // Minutes to add as a buffer before Fajr for Suhoor cutoff.
        'suhoor_buffer_minutes' => 10,
        // Whether to auto-detect Ramadan via the Hijri calendar from the API.
        'auto_detect' => true,
    ],

];
