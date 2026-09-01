<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Support\Carbon;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;
use Mubbashir786\PrayerTimes\Hijri\HijriDate;

class HijriCalendarTest extends TestCase
{
    public function test_it_exposes_the_hijri_date_as_a_value_object(): void
    {
        $this->fakeApi([$this->payload()]);

        $hijri = PrayerTimes::today('Lahore')->hijri;

        $this->assertInstanceOf(HijriDate::class, $hijri);
        $this->assertSame([1, 9, 1447], [$hijri->day, $hijri->month, $hijri->year]);
        $this->assertTrue($hijri->isRamadan());
        $this->assertSame('Ramadan', $hijri->monthName());
        $this->assertSame('1 Ramadan 1447', $hijri->format());
        $this->assertSame('1 Ramadan 1447 AH', $hijri->formatWithEra());
        $this->assertSame('01-09-1447', $hijri->toDateString());
        $this->assertSame('1 Ramadan 1447', (string) $hijri);
    }

    public function test_it_translates_month_names(): void
    {
        $this->assertSame('Ramadan', PrayerTimes::hijriMonth(9, 'en'));
        $this->assertSame('رمضان', PrayerTimes::hijriMonth(9, 'ur'));
        $this->assertSame('رَمَضان', PrayerTimes::hijriMonth(9, 'ar'));
        $this->assertSame('Ramazan', PrayerTimes::hijriMonth(9, 'tr'));
        $this->assertSame('রমজান', PrayerTimes::hijriMonth(9, 'bn'));
    }

    public function test_it_returns_all_twelve_months_for_every_shipped_locale(): void
    {
        foreach (PrayerTimes::locales() as $locale) {
            $months = PrayerTimes::hijriMonths($locale);

            $this->assertCount(12, $months, "{$locale} should have twelve months");
            $this->assertSame(range(1, 12), array_keys($months), "{$locale} months should be keyed 1-12");
            $this->assertSame([], array_filter($months, fn ($m) => trim($m) === ''), "{$locale} has a blank month");
        }
    }

    public function test_it_ships_the_major_languages(): void
    {
        $this->assertSame(
            ['ar', 'bn', 'en', 'es', 'fa', 'fr', 'hi', 'id', 'ms', 'ru', 'tr', 'ur'],
            PrayerTimes::locales()
        );
    }

    public function test_it_translates_prayer_names(): void
    {
        $this->assertSame('Fajr', PrayerTimes::prayerName('Fajr', 'en'));
        $this->assertSame('فجر', PrayerTimes::prayerName('Fajr', 'ur'));
        $this->assertSame('الفجر', PrayerTimes::prayerName('Fajr', 'ar'));
        $this->assertSame('İmsak', PrayerTimes::prayerName('Fajr', 'tr'));
        $this->assertSame('Isyak', PrayerTimes::prayerName('Isha', 'ms'));
    }

    public function test_the_locale_can_be_set_globally(): void
    {
        $this->fakeApi([$this->payload()]);

        $this->assertSame('en', PrayerTimes::locale());
        $row = PrayerTimes::today('Lahore');
        $this->assertSame('1 Ramadan 1447', $row->hijri_date);

        PrayerTimes::setLocale('ur');

        $this->assertSame('ur', PrayerTimes::locale());
        $this->assertSame('1 رمضان 1447', $row->hijri_date);
        $this->assertSame('رمضان', PrayerTimes::hijriMonth(9));

        // An explicit locale still wins over the global one.
        $this->assertSame('Ramadan', PrayerTimes::hijriMonth(9, 'en'));
    }

    public function test_setting_the_locale_to_null_follows_the_app_locale(): void
    {
        app()->setLocale('ar');
        PrayerTimes::setLocale(null);

        $this->assertSame('ar', PrayerTimes::locale());
        $this->assertSame('رَمَضان', PrayerTimes::hijriMonth(9));
    }

    public function test_an_unknown_locale_falls_back_to_english(): void
    {
        $this->assertSame('Ramadan', PrayerTimes::hijriMonth(9, 'xx'));
        $this->assertSame('Fajr', PrayerTimes::prayerName('Fajr', 'xx'));
        $this->assertCount(12, PrayerTimes::hijriMonths('xx'));
    }

    public function test_it_localizes_the_prayer_array(): void
    {
        $this->fakeApi([$this->payload()]);

        $row = PrayerTimes::today('Lahore');

        // The canonical array stays English, so logic never breaks.
        $this->assertSame(['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'], array_keys($row->toPrayerArray()));
        $this->assertSame(
            ['فجر', 'ظہر', 'عصر', 'مغرب', 'عشاء'],
            array_keys($row->toLocalizedPrayerArray('ur'))
        );
    }

    public function test_the_model_serialises_the_hijri_date(): void
    {
        $this->fakeApi([$this->payload()]);

        $array = PrayerTimes::today('Lahore')->toArray();

        $this->assertSame('1 Ramadan 1447', $array['hijri_date']);
        $this->assertTrue($array['is_ramadan']);
        $this->assertSame(9, $array['hijri_month']);
    }

    public function test_the_hijri_date_serialises_to_json(): void
    {
        $hijri = new HijriDate(30, 9, 1447, -1);

        $this->assertSame([
            'day' => 30,
            'month' => 9,
            'year' => 1447,
            'month_name' => 'Ramadan',
            'adjustment' => -1,
            'is_ramadan' => true,
            'formatted' => '30 Ramadan 1447',
        ], $hijri->toArray());
        $this->assertJson(json_encode($hijri));
    }

    public function test_the_ramadan_scope_filters_by_hijri_month(): void
    {
        $this->fakeApi([
            $this->payload(),
            $this->payload(['hijri' => ['day' => '3', 'month' => ['en' => 'Shawwal', 'number' => 10], 'year' => '1447']]),
        ]);

        PrayerTimes::forDate(Carbon::parse('2026-03-18'), 'Lahore');
        PrayerTimes::forDate(Carbon::parse('2026-03-21'), 'Lahore');

        $this->assertSame(1, \Mubbashir786\PrayerTimes\Models\PrayerTime::ramadan()->count());
    }
}
