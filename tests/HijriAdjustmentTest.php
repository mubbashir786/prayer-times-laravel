<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Support\Carbon;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;
use Mubbashir786\PrayerTimes\Models\PrayerTime;

/**
 * The API reports the Saudi (HJCoSA) Hijri calendar. These cover shifting it so
 * a country that sights the moon a day later reads the right date.
 */
class HijriAdjustmentTest extends TestCase
{
    public function test_no_adjustment_uses_the_hijri_date_from_the_timings_response(): void
    {
        $this->fakeApi([$this->payload()]);

        $row = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Makkah');

        $this->assertSame(1, $this->apiCallCount(), 'no second call is needed');
        $this->assertSame('1 Ramadan 1447', $row->hijri_date);
        $this->assertSame(0, $row->hijri_adjustment);
    }

    public function test_a_negative_adjustment_puts_the_country_a_day_behind_saudi_arabia(): void
    {
        config()->set('prayer-times.hijri.adjustments', ['Pakistan' => -1]);

        // Saudi Arabia reads 1 Shawwal (Eid); Pakistan is still on 30 Ramadan.
        $this->fakeApi([
            $this->payload(['hijri' => ['day' => '1', 'month' => ['en' => 'Shawwal', 'number' => 10], 'year' => '1447']]),
            $this->gToHPayload(day: 30, month: 9, monthName: 'Ramadan'),
        ]);

        $row = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Lahore');

        // The Gregorian date is shifted and converted, so month lengths stay right.
        $this->assertStringContainsString('/gToH/19-03-2026', $this->apiPath(1));
        $this->assertSame('30 Ramadan 1447', $row->hijri_date);
        $this->assertSame(30, $row->hijri->day);
        $this->assertTrue($row->is_ramadan, 'Pakistan is still fasting on Saudi Eid');
        $this->assertSame(-1, $row->hijri_adjustment);
    }

    public function test_the_adjustment_never_touches_the_prayer_times(): void
    {
        config()->set('prayer-times.hijri.adjustments', ['Pakistan' => -1]);
        $this->fakeApi([
            $this->payload(['timings' => ['Fajr' => '05:12 (PKT)', 'Maghrib' => '18:47 (PKT)']]),
            $this->gToHPayload(),
        ]);

        $row = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Lahore');

        // Timings still come from the real date, only the Hijri date moved.
        $this->assertStringContainsString('/timings/20-03-2026', $this->apiPath(0));
        $this->assertSame('05:12', $row->fajr);
        $this->assertSame('18:47', $row->maghrib);
    }

    public function test_a_positive_adjustment_puts_the_country_a_day_ahead(): void
    {
        config()->set('prayer-times.hijri.adjustment', 1);
        $this->fakeApi([$this->payload(), $this->gToHPayload(day: 2, month: 9, monthName: 'Ramadan')]);

        $row = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Makkah');

        $this->assertStringContainsString('/gToH/21-03-2026', $this->apiPath(1));
        $this->assertSame('2 Ramadan 1447', $row->hijri_date);
        $this->assertSame(1, $row->hijri_adjustment);
    }

    public function test_the_adjustment_is_resolved_per_country(): void
    {
        config()->set('prayer-times.hijri.adjustments', ['Pakistan' => -1, 'Saudi Arabia' => 0]);

        $this->assertSame(-1, PrayerTimes::resolveLocation('Lahore')['hijri_adjustment']);
        $this->assertSame(0, PrayerTimes::resolveLocation('Makkah')['hijri_adjustment']);
        // An unmapped city inherits the fallback country.
        $this->assertSame(-1, PrayerTimes::resolveLocation('Sahiwal')['hijri_adjustment']);
    }

    public function test_country_matching_is_case_insensitive(): void
    {
        config()->set('prayer-times.hijri.adjustments', ['pakistan' => -1]);

        $this->assertSame(-1, PrayerTimes::resolveLocation('Karachi')['hijri_adjustment']);
    }

    public function test_a_city_entry_can_override_its_country(): void
    {
        config()->set('prayer-times.hijri.adjustments', ['Pakistan' => -1]);
        config()->set('prayer-times.cities.Gilgit', [
            'latitude' => 35.9208, 'longitude' => 74.3082,
            'timezone' => 'Asia/Karachi', 'country' => 'Pakistan',
            'hijri_adjustment' => 0,
        ]);

        $this->assertSame(0, PrayerTimes::resolveLocation('Gilgit')['hijri_adjustment']);
        $this->assertSame(-1, PrayerTimes::resolveLocation('Lahore')['hijri_adjustment']);
    }

    public function test_the_global_default_applies_when_no_country_matches(): void
    {
        config()->set('prayer-times.hijri.adjustment', -1);
        config()->set('prayer-times.hijri.adjustments', ['Turkey' => 0]);

        $this->assertSame(-1, PrayerTimes::resolveLocation('London')['hijri_adjustment']);
    }

    public function test_changing_the_adjustment_refetches_a_cached_row(): void
    {
        $this->fakeApi([
            $this->payload(['hijri' => ['day' => '1', 'month' => ['en' => 'Shawwal', 'number' => 10], 'year' => '1447']]),
            $this->payload(['hijri' => ['day' => '1', 'month' => ['en' => 'Shawwal', 'number' => 10], 'year' => '1447']]),
            $this->gToHPayload(day: 30, month: 9, monthName: 'Ramadan'),
        ]);

        $before = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Lahore');
        $this->assertSame('1 Shawwal 1447', $before->hijri_date);

        config()->set('prayer-times.hijri.adjustments', ['Pakistan' => -1]);
        $after = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Lahore');

        $this->assertSame('30 Ramadan 1447', $after->hijri_date);
        $this->assertSame(3, $this->apiCallCount());
        $this->assertSame(1, PrayerTime::count(), 'the row is corrected, not duplicated');
    }

    public function test_two_countries_on_the_same_day_can_disagree(): void
    {
        config()->set('prayer-times.hijri.adjustments', ['Pakistan' => -1]);
        $eid = ['hijri' => ['day' => '1', 'month' => ['en' => 'Shawwal', 'number' => 10], 'year' => '1447']];

        $this->fakeApi([
            $this->payload($eid),                                          // Makkah timings
            $this->payload($eid),                                          // Lahore timings
            $this->gToHPayload(day: 30, month: 9, monthName: 'Ramadan'),   // Lahore's shifted Hijri
        ]);

        $makkah = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Makkah');
        $lahore = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Lahore');

        $this->assertSame('1 Shawwal 1447', $makkah->hijri_date);
        $this->assertSame('30 Ramadan 1447', $lahore->hijri_date);
        $this->assertFalse($makkah->is_ramadan);
        $this->assertTrue($lahore->is_ramadan);
    }

    public function test_the_shipped_config_puts_pakistan_a_day_behind(): void
    {
        // TestCase clears these, so read the file the package actually ships.
        $shipped = require __DIR__ . '/../config/prayer-times.php';

        $this->assertSame(-1, $shipped['hijri']['adjustments']['Pakistan']);
        $this->assertSame(0, $shipped['hijri']['adjustment']);
    }
}
