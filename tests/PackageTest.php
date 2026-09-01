<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\View;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;
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
            'city', 'latitude', 'longitude', 'timezone', 'date', 'hijri_date',
            'is_ramadan', 'fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha',
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

    public function test_prayer_times_round_trip_as_hh_mm_strings(): void
    {
        $row = PrayerTime::create([
            'city' => 'Testville',
            'latitude' => 1.0,
            'longitude' => 2.0,
            'timezone' => 'Asia/Karachi',
            'date' => '2026-03-20',
            'hijri_date' => '1 Ramadan 1447',
            'is_ramadan' => true,
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
