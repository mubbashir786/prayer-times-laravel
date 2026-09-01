<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Support\Carbon;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;
use Mubbashir786\PrayerTimes\Models\PrayerTime;

class CitySelectionTest extends TestCase
{
    public function test_it_uses_the_default_location_when_no_city_is_given(): void
    {
        $this->fakeApi([$this->payload()]);

        $row = PrayerTimes::today();

        $this->assertSame('Islamabad', $row->city);
        $this->assertStringContainsString('/timings/', $this->apiPath());
        $this->assertSame('33.6844', $this->apiQuery()['latitude']);
        $this->assertSame('73.0479', $this->apiQuery()['longitude']);
        $this->assertSame('Asia/Karachi', $row->timezone);
    }

    public function test_it_resolves_a_mapped_city_case_insensitively(): void
    {
        $this->fakeApi([$this->payload()]);

        $row = PrayerTimes::today('lahore');

        // Canonical spelling from the config map is what gets stored, so the
        // cache key stays stable no matter how the caller typed it.
        $this->assertSame('Lahore', $row->city);
        $this->assertStringContainsString('/timings/', $this->apiPath());
        $this->assertSame('31.5204', $this->apiQuery()['latitude']);
        $this->assertSame('74.3587', $this->apiQuery()['longitude']);
        $this->assertArrayNotHasKey('city', $this->apiQuery());
    }

    public function test_it_stores_the_timezone_of_a_mapped_city(): void
    {
        $this->fakeApi([$this->payload()]);

        $row = PrayerTimes::today('London');

        $this->assertSame('Europe/London', $row->timezone);
        $this->assertSame('51.5072', $this->apiQuery()['latitude']);
    }

    public function test_an_unmapped_city_falls_back_to_the_city_endpoint(): void
    {
        $this->fakeApi([$this->payload()]);

        $row = PrayerTimes::today('Sahiwal');

        $this->assertStringContainsString('/timingsByCity/', $this->apiPath());
        $this->assertSame('Sahiwal', $this->apiQuery()['city']);
        $this->assertSame('Pakistan', $this->apiQuery()['country']);
        $this->assertArrayNotHasKey('latitude', $this->apiQuery());

        // The timezone is real and gets persisted; the coordinates in the
        // response are a placeholder Aladhan sends for every city, so the row
        // keeps them null rather than pointing at the middle of nowhere.
        $this->assertSame('Sahiwal', $row->city);
        $this->assertNull($row->latitude);
        $this->assertNull($row->longitude);
        $this->assertSame('Asia/Karachi', $row->timezone);
    }

    public function test_it_never_stores_the_api_placeholder_coordinates(): void
    {
        $this->fakeApi([$this->payload()]);

        PrayerTimes::today('Sahiwal');

        $row = PrayerTime::first();
        $this->assertNotEquals(8.8888888, (float) $row->latitude);
        $this->assertNotEquals(7.7777777, (float) $row->longitude);
        $this->assertNull($row->latitude);
    }

    public function test_a_name_resolved_row_is_refetched_once_the_city_gains_coordinates(): void
    {
        $this->fakeApi([$this->payload(), $this->payload(['timings' => ['Fajr' => '04:52 (PKT)']])]);

        $byName = PrayerTimes::today('Sahiwal');
        $this->assertNull($byName->latitude);

        // The developer adds the city to the map, so real coordinates exist now.
        config()->set('prayer-times.cities.Sahiwal', [
            'latitude' => 30.6682, 'longitude' => 73.1114,
            'timezone' => 'Asia/Karachi', 'country' => 'Pakistan',
        ]);

        $byCoords = PrayerTimes::today('Sahiwal');

        $this->assertSame(2, $this->apiCallCount());
        $this->assertStringContainsString('/timings/', $this->apiPath(1));
        $this->assertEquals(30.6682, (float) $byCoords->latitude);
        $this->assertSame('04:52', $byCoords->fajr);
        $this->assertSame(1, PrayerTime::count());
    }

    public function test_the_fallback_country_is_configurable(): void
    {
        config()->set('prayer-times.fallback_country', 'India');
        $this->fakeApi([$this->payload()]);

        PrayerTimes::today('Hyderabad');

        $this->assertSame('India', $this->apiQuery()['country']);
    }

    public function test_explicit_coordinates_are_used_for_an_unmapped_city(): void
    {
        $this->fakeApi([$this->payload(['meta' => ['timezone' => 'Asia/Karachi']])]);

        $row = PrayerTimes::today('My Village', 31.10, 72.40);

        $this->assertStringContainsString('/timings/', $this->apiPath());
        $this->assertSame('31.1', $this->apiQuery()['latitude']);
        $this->assertSame('72.4', $this->apiQuery()['longitude']);
        $this->assertSame('My Village', $row->city);
        $this->assertEquals(31.10, (float) $row->latitude);
    }

    public function test_explicit_coordinates_override_the_city_map(): void
    {
        $this->fakeApi([$this->payload()]);

        PrayerTimes::today('Lahore', 24.8607, 67.0011);

        $this->assertSame('24.8607', $this->apiQuery()['latitude']);
        $this->assertSame('67.0011', $this->apiQuery()['longitude']);
    }

    public function test_the_calculation_method_and_school_are_always_sent(): void
    {
        config()->set('prayer-times.calculation_method', 3);
        config()->set('prayer-times.asr_school', 0);
        $this->fakeApi([$this->payload()]);

        PrayerTimes::today('Karachi');

        $this->assertSame('3', $this->apiQuery()['method']);
        $this->assertSame('0', $this->apiQuery()['school']);
    }

    public function test_two_cities_are_cached_independently(): void
    {
        $this->fakeApi([
            $this->payload(['timings' => ['Fajr' => '05:12 (PKT)']]),
            $this->payload(['timings' => ['Fajr' => '05:01 (PKT)']]),
        ]);

        $lahore = PrayerTimes::today('Lahore');
        $karachi = PrayerTimes::today('Karachi');

        $this->assertSame(2, $this->apiCallCount());
        $this->assertSame('05:12', $lahore->fajr);
        $this->assertSame('05:01', $karachi->fajr);
        $this->assertSame(2, PrayerTime::count());
    }

    public function test_a_second_call_for_the_same_city_and_day_is_served_from_cache(): void
    {
        // Only one response is queued: a second HTTP call would blow up.
        $this->fakeApi([$this->payload()]);

        $first = PrayerTimes::today('Lahore');
        $second = PrayerTimes::today('lahore');

        $this->assertSame(1, $this->apiCallCount());
        $this->assertTrue($first->is($second));
    }

    public function test_a_row_cached_against_different_coordinates_is_refetched(): void
    {
        // Simulates a row written before the city had real coordinates.
        PrayerTime::create([
            'city' => 'Lahore',
            'latitude' => 33.6844,   // Islamabad's
            'longitude' => 73.0479,
            'timezone' => 'Asia/Karachi',
            'date' => Carbon::today()->toDateString(),
            'hijri_date' => '1 Ramadan 1447',
            'is_ramadan' => true,
            'fajr' => '05:20', 'sunrise' => '06:40', 'dhuhr' => '12:15',
            'asr' => '16:25', 'maghrib' => '18:50', 'isha' => '20:10',
        ]);

        $this->fakeApi([$this->payload()]);

        $row = PrayerTimes::today('Lahore');

        $this->assertSame(1, $this->apiCallCount());
        $this->assertEquals(31.5204, (float) $row->fresh()->latitude);
        $this->assertSame('05:12', $row->fresh()->fajr);
        $this->assertSame(1, PrayerTime::count(), 'the stale row should be updated, not duplicated');
    }

    public function test_a_cached_row_past_the_ttl_is_refetched(): void
    {
        config()->set('prayer-times.cache_ttl_hours', 1);
        $this->fakeApi([$this->payload(), $this->payload(['timings' => ['Fajr' => '05:09 (PKT)']])]);

        $first = PrayerTimes::today('Lahore');
        $first->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();

        $second = PrayerTimes::today('Lahore');

        $this->assertSame(2, $this->apiCallCount());
        $this->assertSame('05:09', $second->fajr);
    }

    public function test_a_ttl_of_zero_never_expires_the_cache(): void
    {
        config()->set('prayer-times.cache_ttl_hours', 0);
        $this->fakeApi([$this->payload()]);

        $first = PrayerTimes::today('Lahore');
        $first->forceFill(['updated_at' => now()->subYears(2)])->saveQuietly();

        PrayerTimes::today('Lahore');

        $this->assertSame(1, $this->apiCallCount());
    }

    public function test_it_lists_the_cities_in_the_config_map(): void
    {
        $this->fakeApi([]);

        $cities = PrayerTimes::cities();

        $this->assertContains('Lahore', $cities);
        $this->assertContains('Makkah', $cities);
        $this->assertNotContains('Sahiwal', $cities);
    }

    public function test_resolve_location_reports_the_chosen_location(): void
    {
        $this->fakeApi([]);

        $mapped = PrayerTimes::resolveLocation('karachi');
        $unmapped = PrayerTimes::resolveLocation('Sahiwal');
        $explicit = PrayerTimes::resolveLocation('Anywhere', 10.0, 20.0);
        $default = PrayerTimes::resolveLocation('   ');

        $this->assertSame(['Karachi', 24.8607, 'Asia/Karachi'], [$mapped['city'], $mapped['latitude'], $mapped['timezone']]);
        $this->assertNull($unmapped['latitude'], 'an unmapped city has no coordinates until the API answers');
        $this->assertSame('Pakistan', $unmapped['country']);
        $this->assertSame([10.0, 20.0], [$explicit['latitude'], $explicit['longitude']]);
        $this->assertSame('Islamabad', $default['city']);
    }
}
