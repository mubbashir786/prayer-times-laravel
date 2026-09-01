<?php

namespace Mubbashir786\PrayerTimes\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Mubbashir786\PrayerTimes\Models\PrayerTime today(?string $city = null)
 * @method static \Mubbashir786\PrayerTimes\Models\PrayerTime forDate(?\Carbon\Carbon $date = null, ?string $city = null, ?float $lat = null, ?float $lng = null)
 * @method static string suhoorCutoff(?\Carbon\Carbon $date = null, ?string $city = null)
 * @method static int|null minutesUntilIftar(?string $city = null)
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
