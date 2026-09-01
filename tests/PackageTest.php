<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\View;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;
use Mubbashir786\PrayerTimes\Hijri\HijriCalendar;
use Mubbashir786\PrayerTimes\Models\PrayerTime;
use Mubbashir786\PrayerTimes\PrayerTimesManager;

class PackageTest extends TestCase
{
    public function test_the_facade_resolves_the_manager(): void
    {
        $this->assertInstanceOf(PrayerTimesManager::class, PrayerTimes::getFacadeRoot());
        $this->assertSame(app('prayer-times'), app('prayer-times'), 'the manager should be a singleton');
    }

    public function test_the_package_config_is_merged(): void
    {
        $this->assertSame('Islamabad', config('prayer-times.default_location.city'));
        $this->assertIsArray(config('prayer-times.cities'));
        $this->assertArrayHasKey('Lahore', config('prayer-times.cities'));
    }

    public function test_every_configured_city_has_usable_coordinates(): void
    {
        foreach (config('prayer-times.cities') as $name => $entry) {
            $this->assertArrayHasKey('latitude', $entry, "{$name} is missing a latitude");
            $this->assertArrayHasKey('longitude', $entry, "{$name} is missing a longitude");
            $this->assertTrue(abs($entry['latitude']) <= 90, "{$name} has an out-of-range latitude");
            $this->assertTrue(abs($entry['longitude']) <= 180, "{$name} has an out-of-range longitude");
            $this->assertContains($entry['timezone'], timezone_identifiers_list(), "{$name} has an unknown timezone");
        }
    }

    public function test_the_migration_creates_the_cache_table(): void
    {
        $this->assertTrue(\Schema::hasTable('prayer_times'));
        $this->assertTrue(\Schema::hasColumns('prayer_times', [
            'city', 'latitude', 'longitude', 'timezone', 'date',
            'hijri_day', 'hijri_month', 'hijri_year', 'hijri_adjustment',
            'fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha',
        ]));
    }

    public function test_the_reminder_command_is_registered(): void
    {
        $this->assertArrayHasKey('prayer-times:check-reminders', \Artisan::all());
    }

    public function test_the_reminder_command_is_scheduled_every_minute(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'prayer-times:check-reminders'));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
    }

    public function test_the_widget_view_is_registered_under_the_package_namespace(): void
    {
        $this->assertTrue(View::exists('prayer-times::components.widget'));
    }

    public function test_the_hijri_calendar_is_bound_as_a_singleton(): void
    {
        $this->assertInstanceOf(HijriCalendar::class, app(HijriCalendar::class));
        $this->assertSame(app(HijriCalendar::class), app(HijriCalendar::class));
    }

    public function test_every_shipped_locale_has_both_translation_files(): void
    {
        foreach (glob(__DIR__ . '/../resources/lang/*', GLOB_ONLYDIR) as $dir) {
            $locale = basename($dir);
            $this->assertFileExists("{$dir}/hijri.php", "{$locale} is missing hijri.php");
            $this->assertFileExists("{$dir}/prayers.php", "{$locale} is missing prayers.php");

            $prayers = require "{$dir}/prayers.php";
            foreach (['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha', 'ramadan_greeting', 'next'] as $key) {
                $this->assertArrayHasKey($key, $prayers, "{$locale} is missing the {$key} line");
                $this->assertNotSame('', trim($prayers[$key]), "{$locale}.{$key} is blank");
            }
        }
    }

    public function test_every_rtl_locale_is_one_we_ship(): void
    {
        $shipped = array_map('basename', glob(__DIR__ . '/../resources/lang/*', GLOB_ONLYDIR));

        foreach (['ar', 'ur', 'fa'] as $rtl) {
            $this->assertContains($rtl, $shipped);
            $this->assertContains($rtl, config('prayer-times.rtl_locales'));
        }
    }

    public function test_prayer_times_round_trip_as_hh_mm_strings(): void
    {
        $row = PrayerTime::create([
            'city' => 'Testville',
            'latitude' => 1.0,
            'longitude' => 2.0,
            'timezone' => 'Asia/Karachi',
            'date' => '2026-03-20',
            'hijri_day' => 1, 'hijri_month' => 9, 'hijri_year' => 1447,
            'fajr' => '05:12', 'sunrise' => '06:31', 'dhuhr' => '12:10',
            'asr' => '16:20', 'maghrib' => '18:47', 'isha' => '20:05',
        ]);

        // Same shape whether the model is fresh in memory or read back from the DB.
        $this->assertSame('05:12', $row->fajr);
        $this->assertSame('05:12', $row->fresh()->fajr);
        $this->assertSame('18:47', PrayerTime::first()->maghrib);
        $this->assertSame('05:12:00', $row->getRawOriginal('fajr'), 'the column keeps full time precision');
    }
}
