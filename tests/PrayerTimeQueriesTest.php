<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Support\Carbon;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;
use Mubbashir786\PrayerTimes\Models\PrayerTime;

class PrayerTimeQueriesTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_fetches_an_arbitrary_date(): void
    {
        $this->fakeApi([$this->payload()]);

        $row = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Makkah');

        $this->assertStringContainsString('/timings/20-03-2026', $this->apiPath());
        $this->assertSame('2026-03-20', $row->date->toDateString());
        $this->assertSame('Makkah', $row->city);
        $this->assertSame('Asia/Riyadh', $row->timezone);
    }

    public function test_it_records_the_hijri_date_and_ramadan_flag(): void
    {
        $this->fakeApi([
            $this->payload(),
            $this->payload(['hijri' => ['day' => '3', 'month' => ['en' => 'Shawwal', 'number' => 10], 'year' => '1447']]),
        ]);

        $ramadan = PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Lahore');
        $shawwal = PrayerTimes::forDate(Carbon::parse('2026-03-21'), 'Lahore');

        $this->assertSame('1 Ramadan 1447', $ramadan->hijri_date);
        $this->assertSame([1, 9, 1447], [$ramadan->hijri->day, $ramadan->hijri->month, $ramadan->hijri->year]);
        $this->assertTrue($ramadan->is_ramadan);
        $this->assertSame('3 Shawwal 1447', $shawwal->hijri_date);
        $this->assertFalse($shawwal->is_ramadan);
    }

    public function test_for_range_returns_one_row_per_day_keyed_by_date(): void
    {
        $this->fakeApi([$this->payload(), $this->payload(), $this->payload()]);

        $days = PrayerTimes::forRange(Carbon::parse('2026-03-20'), Carbon::parse('2026-03-22'), 'Karachi');

        $this->assertSame(['2026-03-20', '2026-03-21', '2026-03-22'], array_keys($days));
        $this->assertContainsOnlyInstancesOf(PrayerTime::class, $days);
        $this->assertSame(3, $this->apiCallCount());
        $this->assertStringContainsString('/timings/22-03-2026', $this->apiPath(2));
    }

    public function test_for_range_reuses_cached_days(): void
    {
        $this->fakeApi([$this->payload(), $this->payload()]);

        PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Karachi');
        $days = PrayerTimes::forRange(Carbon::parse('2026-03-20'), Carbon::parse('2026-03-21'), 'Karachi');

        $this->assertCount(2, $days);
        $this->assertSame(2, $this->apiCallCount(), 'the already-cached day should not be refetched');
    }

    public function test_suhoor_cutoff_subtracts_the_configured_buffer(): void
    {
        config()->set('prayer-times.ramadan.suhoor_buffer_minutes', 10);
        $this->fakeApi([$this->payload(['timings' => ['Fajr' => '05:12 (PKT)']])]);

        $this->assertSame('05:02', PrayerTimes::suhoorCutoff(Carbon::parse('2026-03-20'), 'Lahore'));
    }

    public function test_suhoor_cutoff_accepts_coordinates(): void
    {
        config()->set('prayer-times.ramadan.suhoor_buffer_minutes', 15);
        $this->fakeApi([$this->payload(['timings' => ['Fajr' => '04:05 (PKT)']])]);

        $cutoff = PrayerTimes::suhoorCutoff(Carbon::parse('2026-03-20'), 'My Village', 31.10, 72.40);

        $this->assertSame('03:50', $cutoff);
        $this->assertSame('31.1', $this->apiQuery()['latitude']);
    }

    public function test_minutes_until_iftar_counts_down_in_the_city_timezone(): void
    {
        // 12:00 UTC is 17:00 in Karachi, so Maghrib at 18:47 PKT is 107 minutes away.
        Carbon::setTestNow('2026-03-20 12:00:00');
        $this->fakeApi([$this->payload(['timings' => ['Maghrib' => '18:47 (PKT)']])]);

        $this->assertSame(107, PrayerTimes::minutesUntilIftar('Lahore'));
    }

    public function test_minutes_until_iftar_is_null_once_maghrib_has_passed(): void
    {
        // 16:00 UTC is 21:00 in Karachi - Maghrib is long gone.
        Carbon::setTestNow('2026-03-20 16:00:00');
        $this->fakeApi([$this->payload(['timings' => ['Maghrib' => '18:47 (PKT)']])]);

        $this->assertNull(PrayerTimes::minutesUntilIftar('Lahore'));
    }

    public function test_next_prayer_is_relative_to_the_city_timezone_not_the_app_timezone(): void
    {
        // 12:00 UTC on 1 July is 13:00 in London (BST). Naively comparing against
        // the app's UTC clock would wrongly say Dhuhr (12:10) is still ahead.
        Carbon::setTestNow('2026-07-01 12:00:00');
        $this->fakeApi([$this->payload([
            'timings' => ['Dhuhr' => '12:10 (BST)', 'Asr' => '16:20 (BST)'],
        ])]);

        $next = PrayerTimes::today('London')->nextPrayer();

        $this->assertSame('Asr', $next['name']);
        $this->assertSame('16:20', $next['time']);
    }

    public function test_next_prayer_is_null_after_isha(): void
    {
        Carbon::setTestNow('2026-03-20 16:00:00'); // 21:00 PKT, after Isha at 20:05
        $this->fakeApi([$this->payload()]);

        $this->assertNull(PrayerTimes::today('Lahore')->nextPrayer());
    }

    public function test_timezone_name_falls_back_to_the_default_when_missing(): void
    {
        $row = new PrayerTime(['timezone' => null]);

        $this->assertSame('Asia/Karachi', $row->timezoneName());
    }

    public function test_to_prayer_array_returns_the_five_daily_prayers(): void
    {
        $this->fakeApi([$this->payload()]);

        $prayers = PrayerTimes::today('Lahore')->toPrayerArray();

        $this->assertSame(['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'], array_keys($prayers));
        $this->assertSame('16:20', $prayers['Asr']);
    }

    public function test_it_strips_the_timezone_suffix_from_api_times(): void
    {
        $this->fakeApi([$this->payload(['timings' => ['Isha' => '20:05 (PKT)']])]);

        $this->assertSame('20:05', PrayerTimes::today('Lahore')->isha);
    }

    public function test_it_also_accepts_times_without_a_timezone_suffix(): void
    {
        // The live API omits the suffix unless it is asked for one.
        $this->fakeApi([$this->payload(['timings' => ['Fajr' => '04:46', 'Isha' => '19:35']])]);

        $row = PrayerTimes::today('Lahore');

        $this->assertSame('04:46', $row->fajr);
        $this->assertSame('19:35', $row->isha);
    }
}
