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

    // Known cities, matched case-insensitively when a city name is passed to the
    // manager (e.g. PrayerTimes::today('lahore')). Coordinates here are used
    // directly, so no city lookup round-trip is needed.
    //
    // A city that is NOT listed here still works: the package falls back to the
    // Aladhan /timingsByCity endpoint using the name plus 'fallback_country'.
    // Add your own entries to avoid depending on that lookup.
    'cities' => [
        'Islamabad' => ['latitude' => 33.6844, 'longitude' => 73.0479, 'timezone' => 'Asia/Karachi', 'country' => 'Pakistan'],
        'Karachi' => ['latitude' => 24.8607, 'longitude' => 67.0011, 'timezone' => 'Asia/Karachi', 'country' => 'Pakistan'],
        'Lahore' => ['latitude' => 31.5204, 'longitude' => 74.3587, 'timezone' => 'Asia/Karachi', 'country' => 'Pakistan'],
        'Rawalpindi' => ['latitude' => 33.5651, 'longitude' => 73.0169, 'timezone' => 'Asia/Karachi', 'country' => 'Pakistan'],
        'Faisalabad' => ['latitude' => 31.4504, 'longitude' => 73.1350, 'timezone' => 'Asia/Karachi', 'country' => 'Pakistan'],
        'Multan' => ['latitude' => 30.1575, 'longitude' => 71.5249, 'timezone' => 'Asia/Karachi', 'country' => 'Pakistan'],
        'Peshawar' => ['latitude' => 34.0151, 'longitude' => 71.5249, 'timezone' => 'Asia/Karachi', 'country' => 'Pakistan'],
        'Quetta' => ['latitude' => 30.1798, 'longitude' => 66.9750, 'timezone' => 'Asia/Karachi', 'country' => 'Pakistan'],
        'Makkah' => ['latitude' => 21.3891, 'longitude' => 39.8579, 'timezone' => 'Asia/Riyadh', 'country' => 'Saudi Arabia'],
        'Madinah' => ['latitude' => 24.4686, 'longitude' => 39.6142, 'timezone' => 'Asia/Riyadh', 'country' => 'Saudi Arabia'],
        'Dubai' => ['latitude' => 25.2048, 'longitude' => 55.2708, 'timezone' => 'Asia/Dubai', 'country' => 'United Arab Emirates'],
        'London' => ['latitude' => 51.5072, 'longitude' => -0.1276, 'timezone' => 'Europe/London', 'country' => 'United Kingdom'],
        'Toronto' => ['latitude' => 43.6532, 'longitude' => -79.3832, 'timezone' => 'America/Toronto', 'country' => 'Canada'],
        'New York' => ['latitude' => 40.7128, 'longitude' => -74.0060, 'timezone' => 'America/New_York', 'country' => 'United States'],
    ],

    // Country sent to /timingsByCity for a city that is not in the map above.
    'fallback_country' => env('PRAYER_TIMES_COUNTRY', 'Pakistan'),

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
