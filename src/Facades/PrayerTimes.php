<?php

namespace Mubbashir786\PrayerTimes\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Mubbashir786\PrayerTimes\Models\PrayerTime today(?string $city = null, ?float $lat = null, ?float $lng = null)
 * @method static \Mubbashir786\PrayerTimes\Models\PrayerTime forDate(?\Carbon\Carbon $date = null, ?string $city = null, ?float $lat = null, ?float $lng = null)
 * @method static array forRange(\Carbon\Carbon $from, \Carbon\Carbon $to, ?string $city = null, ?float $lat = null, ?float $lng = null)
 * @method static array cities()
 * @method static \Mubbashir786\PrayerTimes\Hijri\HijriDate hijri(?\Carbon\Carbon $date = null, ?string $city = null, ?float $lat = null, ?float $lng = null)
 * @method static string locale()
 * @method static \Mubbashir786\PrayerTimes\PrayerTimesManager setLocale(?string $locale)
 * @method static array locales()
 * @method static array hijriMonths(?string $locale = null)
 * @method static string hijriMonth(int $month, ?string $locale = null)
 * @method static string prayerName(string $prayer, ?string $locale = null)
 * @method static array resolveLocation(?string $city = null, ?float $lat = null, ?float $lng = null)
 * @method static string suhoorCutoff(?\Carbon\Carbon $date = null, ?string $city = null, ?float $lat = null, ?float $lng = null)
 * @method static int|null minutesUntilIftar(?string $city = null, ?float $lat = null, ?float $lng = null)
 *
 * @see \Mubbashir786\PrayerTimes\PrayerTimesManager
 */
class PrayerTimes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'prayer-times';
    }
}
