<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Support\Carbon;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;
use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end checks against the real Aladhan API.
 *
 * Excluded from the default suite because they need the network. Run with:
 *   vendor/bin/phpunit --group=integration
 */
#[Group('integration')]
class LiveApiTest extends TestCase
{
    public function test_it_fetches_a_mapped_city_by_coordinates(): void
    {
        $row = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Lahore');

        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $row->fajr);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $row->maghrib);
        $this->assertTrue($row->fajr < $row->dhuhr && $row->dhuhr < $row->maghrib);
        $this->assertEquals(31.5204, (float) $row->latitude);
        $this->assertSame('Asia/Karachi', $row->timezone);
        $this->assertNotEmpty($row->hijri_date);
    }

    public function test_it_resolves_an_unmapped_city_by_name(): void
    {
        $row = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Sahiwal');

        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $row->fajr);
        $this->assertSame('Asia/Karachi', $row->timezone);
        $this->assertNull($row->latitude, 'the API placeholder coordinates must not be stored');
    }

    public function test_a_city_in_another_timezone_keeps_its_own_timezone(): void
    {
        $row = PrayerTimes::forDate(Carbon::parse('2026-07-01'), 'Manchester');

        $this->assertSame('Europe/London', $row->timezone);
    }

    public function test_it_detects_ramadan_from_the_hijri_calendar(): void
    {
        // 1 Ramadan 1447 falls on 18 February 2026.
        $ramadan = PrayerTimes::forDate(Carbon::parse('2026-02-19'), 'Karachi');
        $notRamadan = PrayerTimes::forDate(Carbon::parse('2026-06-01'), 'Karachi');

        $this->assertTrue($ramadan->is_ramadan, "expected Ramadan, got {$ramadan->hijri_date}");
        $this->assertFalse($notRamadan->is_ramadan, "expected non-Ramadan, got {$notRamadan->hijri_date}");
    }
}
