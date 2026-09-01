<h1 align="center">🕌 Prayer Times & Ramadan Scheduler for Laravel</h1>

<p align="center">
  Daily prayer times for <strong>any city on earth</strong> — cached in your own database,
  Hijri-aware in 12 languages, timezone-correct, with a drop-in Blade widget and reminder events.
</p>

<p align="center">
  <a href="https://packagist.org/packages/mubbashir786/prayer-times-laravel"><img alt="Latest version" src="https://img.shields.io/packagist/v/mubbashir786/prayer-times-laravel.svg?style=flat-square"></a>
  <a href="https://packagist.org/packages/mubbashir786/prayer-times-laravel"><img alt="Downloads" src="https://img.shields.io/packagist/dt/mubbashir786/prayer-times-laravel.svg?style=flat-square"></a>
  <img alt="PHP version" src="https://img.shields.io/badge/php-%5E8.1-777bb4?style=flat-square">
  <img alt="Laravel version" src="https://img.shields.io/badge/laravel-10%20%7C%2011%20%7C%2012-ff2d20?style=flat-square">
  <img alt="Tests" src="https://img.shields.io/badge/tests-84%20passing-success?style=flat-square">
  <a href="LICENSE"><img alt="License" src="https://img.shields.io/badge/license-MIT-green?style=flat-square"></a>
</p>

---

```php
PrayerTimes::today('Lahore')->maghrib;       // "18:47"
PrayerTimes::today('Lahore')->hijri_date;    // "30 Ramadan 1447"  (Makkah says 1 Shawwal)
PrayerTimes::setLocale('ur')->hijriMonth(9); // "رمضان"
PrayerTimes::minutesUntilIftar('Dubai');     // 137
```

## Contents

- [Why this package](#why-this-package)
- [Install](#install)
- [Configure](#configure)
- [Quick start](#quick-start)
- [Picking a city](#picking-a-city)
- [Hijri calendar](#hijri-calendar)
- [Languages](#languages)
- [Regional Hijri differences](#regional-hijri-differences)
- [API reference](#api-reference)
- [Blade widget](#blade-widget)
- [Ramadan helpers](#ramadan-helpers)
- [Reminders](#reminders)
- [How caching works](#how-caching-works)
- [Testing](#testing)
- [Roadmap](#roadmap)

## Why this package

|  | |
|---|---|
| 🌍 **Any city** | Pass a name from the built-in map, a name the API resolves for you, or raw coordinates. |
| 🗄️ **Cached in your DB** | One row per city per day. The [Aladhan API](https://aladhan.com/prayer-times-api) is hit once, not on every page load. |
| 🕐 **Timezone-correct** | Each row remembers its own timezone, so "next prayer" is right for Makkah even when your app runs in UTC. |
| 🌙 **Ramadan-aware** | Hijri date and an `is_ramadan` flag on every row, plus Suhoor cutoff and Iftar countdown helpers. |
| 🌐 **12 languages** | Hijri months and prayer names in English, Urdu, Arabic, Turkish, Indonesian, Malay, Bengali, Persian, Hindi, French, Russian and Spanish. |
| 🌗 **Moon-sighting offsets** | Saudi Arabia says 1 Shawwal while Pakistan is still on 30 Ramadan — the package models that per country. |
| 🔔 **Reminder events** | A scheduled command fires an event N minutes before each prayer — wire it to mail, database, WhatsApp, Reverb, anything. |
| 🎨 **Blade widget** | `<x-prayer-times::widget city="Lahore" />` and you are done. |

## Install

```bash
composer require mubbashir786/prayer-times-laravel
```

```bash
php artisan vendor:publish --tag=prayer-times-config
```

```bash
php artisan migrate
```

Optional — publish the translations if you want to edit them or add a language:

```bash
php artisan vendor:publish --tag=prayer-times-lang
```

The service provider and the `PrayerTimes` facade are auto-discovered — no manual registration needed.

## Configure

Everything has a sensible default. Override what you need in `.env`:

```env
PRAYER_TIMES_CITY=Islamabad
PRAYER_TIMES_COUNTRY=Pakistan
PRAYER_TIMES_LAT=33.6844
PRAYER_TIMES_LNG=73.0479
PRAYER_TIMES_TZ=Asia/Karachi
PRAYER_TIMES_METHOD=1
PRAYER_TIMES_ASR_SCHOOL=1
PRAYER_TIMES_REMINDERS_ENABLED=true
PRAYER_TIMES_LOCALE=ur
PRAYER_TIMES_HIJRI_ADJUSTMENT=0
```

| Key | Meaning |
|---|---|
| `default_location` | Used whenever no city is passed. |
| `cities` | Your own name → coordinates map (see below). |
| `fallback_country` | Country sent with the API lookup for a city that is not in the map. |
| `calculation_method` | Aladhan method id — `1` Karachi, `2` ISNA, `3` MWL, `4` Umm al-Qura… |
| `asr_school` | `0` Shafi/Maliki/Hanbali, `1` Hanafi. |
| `cache_ttl_hours` | How long a cached day stays fresh. `0` = never expires. |
| `locale` | Language for months, prayer names and widget labels. `null` follows the app locale. |
| `rtl_locales` | Which locales the widget renders right-to-left. |
| `hijri.adjustment` | Global day offset applied to the Hijri date. |
| `hijri.adjustments` | Per-country day offsets, e.g. `'Pakistan' => -1`. |
| `reminders` | Enable/disable, lead time in minutes, notification channels. |
| `ramadan.suhoor_buffer_minutes` | Minutes subtracted from Fajr for the Suhoor cutoff. |

## Quick start

```php
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;

$today = PrayerTimes::today();

$today->fajr;          // "05:12"
$today->maghrib;       // "18:47"
$today->hijri_date;    // "1 Ramadan 1447" - in the current locale
$today->hijri->day;    // 1
$today->is_ramadan;    // true
$today->nextPrayer();  // ['name' => 'Asr', 'time' => '16:20']
$today->toPrayerArray(); // ['Fajr' => '05:12', 'Dhuhr' => '12:10', ...]
```

## Picking a city

Every method takes an optional city, and coordinates you can pass instead of — or on top of — it:

```php
PrayerTimes::today('Lahore');                    // from the config city map
PrayerTimes::today('sahiwal');                   // not in the map — resolved by the API
PrayerTimes::today('My Village', 31.10, 72.40);  // exact coordinates; the name is just a label
```

A city is resolved in this order:

```
1. explicit $lat / $lng ─────────────► /timings          any point on earth
2. config('prayer-times.cities') ────► /timings          no lookup needed, timezone known
3. anything else ────────────────────► /timingsByCity    name + fallback_country
4. no city passed ───────────────────► default_location
```

With **2** and **3** you get the same result; the difference is that the map avoids a name lookup and pins the timezone yourself. Add your own entries to `config/prayer-times.php`:

```php
'cities' => [
    'Sahiwal' => [
        'latitude' => 30.6682,
        'longitude' => 73.1114,
        'timezone' => 'Asia/Karachi',
        'country' => 'Pakistan',
    ],
],
```

Names are matched **case-insensitively**, and the map's spelling is what gets stored — so `today('lahore')` and `today('LAHORE')` share one cache row.

```php
PrayerTimes::cities();                 // names currently in the map
PrayerTimes::resolveLocation('dubai'); // ['city' => 'Dubai', 'latitude' => 25.2048, ...]
```

Ships with Islamabad, Karachi, Lahore, Rawalpindi, Faisalabad, Multan, Peshawar, Quetta, Makkah, Madinah, Dubai, London, Toronto and New York.

## Hijri calendar

Every row carries its Hijri date as a value object:

```php
$hijri = PrayerTimes::today('Lahore')->hijri;

$hijri->day;              // 30
$hijri->month;            // 9
$hijri->year;             // 1447
$hijri->monthName();      // "Ramadan"
$hijri->monthName('ur');  // "رمضان"
$hijri->isRamadan();      // true
$hijri->format();         // "30 Ramadan 1447"
$hijri->formatWithEra();  // "30 Ramadan 1447 AH"
$hijri->toDateString();   // "30-09-1447"
$hijri->toArray();        // day, month, year, month_name, adjustment, is_ramadan, formatted
```

`$row->hijri_date` is the same thing as a string, and `$row->is_ramadan` is derived from the
month — both respect the current locale and any [regional adjustment](#regional-hijri-differences).
There is a query scope too:

```php
PrayerTime::ramadan()->get();   // every cached row that falls in Ramadan
```

Or straight from the facade, without touching a row:

```php
PrayerTimes::hijri(Carbon::parse('2026-03-20'), 'Karachi');  // HijriDate
PrayerTimes::hijriMonths();          // [1 => 'Muharram', ..., 12 => 'Dhu al-Hijjah']
PrayerTimes::hijriMonth(9, 'tr');    // "Ramazan"
```

## Languages

Twelve languages ship with the package:

| | | | |
|---|---|---|---|
| `en` English | `ur` اردو | `ar` العربية | `tr` Türkçe |
| `id` Indonesia | `ms` Melayu | `bn` বাংলা | `fa` فارسی |
| `hi` हिन्दी | `fr` Français | `ru` Русский | `es` Español |

Set one globally for the rest of the request:

```php
PrayerTimes::setLocale('ur');

PrayerTimes::locale();               // "ur"
PrayerTimes::hijriMonth(9);          // "رمضان"
PrayerTimes::prayerName('Fajr');     // "فجر"
PrayerTimes::today()->hijri_date;    // "30 رمضان 1447"
PrayerTimes::locales();              // every locale available
```

Or pass one per call — an explicit locale always wins:

```php
PrayerTimes::hijriMonth(9, 'ar');            // "رَمَضان"
PrayerTimes::prayerName('Isha', 'ms');       // "Isyak"
PrayerTimes::today()->hijri->format('tr');   // "30 Ramazan 1447"
PrayerTimes::today()->toLocalizedPrayerArray('bn');  // ['ফজর' => '05:12', ...]
```

With no locale configured the package follows your application's locale, and falls back to
English for anything it cannot translate. `toPrayerArray()` and `nextPrayer()` always return the
canonical English names, so your logic never breaks when the display language changes.

To add a language or reword one, publish the files and edit them:

```bash
php artisan vendor:publish --tag=prayer-times-lang
```

That writes `lang/vendor/prayer-times/{locale}/hijri.php` and `prayers.php`. A new directory there
is picked up automatically — add it to `rtl_locales` in the config if it reads right-to-left.

> The translations are a solid starting point, but prayer-name conventions vary by region
> (Turkish `İmsak` vs `Sabah`, Indonesian `Subuh` vs `Fajar`). Have a native speaker check the
> languages you actually ship.

## Regional Hijri differences

The API reports the Saudi (HJCoSA) calendar. Because the moon is sighted locally, other countries
are often a day behind: **on 20 March 2026 Saudi Arabia reads 1 Shawwal — Eid — while Pakistan is
still on 30 Ramadan.** The package models that as a day offset per country:

```php
'hijri' => [
    'adjustment' => 0,             // global default
    'adjustments' => [             // per country, matched case-insensitively
        'Pakistan' => -1,
        'India' => -1,
        'Bangladesh' => -1,
    ],
],
```

```php
PrayerTimes::forDate($eid, 'Makkah')->hijri_date;  // "1 Shawwal 1447"
PrayerTimes::forDate($eid, 'Lahore')->hijri_date;  // "30 Ramadan 1447"
PrayerTimes::forDate($eid, 'Lahore')->is_ramadan;  // true - still fasting
```

A city in the `cities` map may override its own country:

```php
'Gilgit' => [
    'latitude' => 35.9208, 'longitude' => 74.3082,
    'timezone' => 'Asia/Karachi', 'country' => 'Pakistan',
    'hijri_adjustment' => 0,
],
```

How it works: the Gregorian date is shifted by the offset and converted by the API, so 29- and
30-day months are always handled correctly — no local Hijri arithmetic to get wrong. **Prayer times
are never affected**, only the Hijri date and the Ramadan flag. Each row remembers the offset it was
stored with, so changing the config refetches instead of serving another country's calendar.

> This is a fixed offset, not moon sighting. It matches the common case, but the real difference is
> decided month to month by each country's committee. If your app must be exact on Eid, drive the
> offset from your own data rather than leaving it at the default.

## API reference

| Method | Returns |
|---|---|
| `today(?string $city, ?float $lat, ?float $lng)` | `PrayerTime` for today |
| `forDate(?Carbon $date, ?string $city, ?float $lat, ?float $lng)` | `PrayerTime` for any date |
| `forRange(Carbon $from, Carbon $to, ?string $city, ?float $lat, ?float $lng)` | `array<'Y-m-d', PrayerTime>` |
| `suhoorCutoff(?Carbon $date, ?string $city, ?float $lat, ?float $lng)` | `"05:02"` — Fajr minus the buffer |
| `minutesUntilIftar(?string $city, ?float $lat, ?float $lng)` | `int` minutes, or `null` once Maghrib has passed |
| `cities()` | `array` of mapped city names |
| `resolveLocation(?string $city, ?float $lat, ?float $lng)` | the location a call would use, offset included |
| `hijri(?Carbon $date, ?string $city, ?float $lat, ?float $lng)` | `HijriDate` for that day |
| `setLocale(?string $locale)` / `locale()` | set or read the display language |
| `locales()` | every locale the package can render |
| `hijriMonths(?string $locale)` / `hijriMonth(int $month, ?string $locale)` | Hijri month names |
| `prayerName(string $prayer, ?string $locale)` | a translated prayer name |

On the `PrayerTime` model:

| Member | Returns |
|---|---|
| `fajr` `sunrise` `dhuhr` `asr` `maghrib` `isha` | `"HH:MM"` strings |
| `city` `latitude` `longitude` `timezone` `date` | where and when these times belong to |
| `hijri` | a `HijriDate` — day, month, year, month name, offset |
| `hijri_date` / `is_ramadan` | `"1 Ramadan 1447"` in the current locale / `bool` |
| `hijri_day` `hijri_month` `hijri_year` `hijri_adjustment` | the stored, queryable parts |
| `toPrayerArray()` | the five daily prayers as `name => time`, English keys |
| `toLocalizedPrayerArray(?string $locale)` | the same, keyed in any language |
| `nextPrayer()` | `['name' => …, 'time' => …]`, or `null` after Isha |
| `timezoneName()` | the row's timezone, falling back to the configured default |
| `scopeRamadan()` | `PrayerTime::ramadan()->get()` |

```php
use Illuminate\Support\Carbon;

PrayerTimes::forDate(Carbon::parse('2026-03-20'), 'Makkah');

// A whole Ramadan calendar for one city, keyed by Y-m-d:
PrayerTimes::forRange(Carbon::parse('2026-02-18'), Carbon::parse('2026-03-19'), 'Karachi');
```

## Blade widget

```blade
<x-prayer-times::widget />
<x-prayer-times::widget city="Lahore" />
<x-prayer-times::widget city="My Village" lat="31.10" lng="72.40" />
<x-prayer-times::widget city="Karachi" date="2026-03-20" />
<x-prayer-times::widget city="Lahore" locale="ur" />
```

It shows the city, the Hijri date, all five prayers with the next one in bold, and a Ramadan
banner during Ramadan. It renders in the current locale unless you pass one, and sets `dir="rtl"`
for right-to-left languages. To restyle it, publish the view and edit away:

```bash
php artisan vendor:publish --tag=prayer-times-views
```

## Ramadan helpers

```php
PrayerTimes::suhoorCutoff();               // "05:02" — Fajr minus suhoor_buffer_minutes
PrayerTimes::suhoorCutoff(null, 'Makkah'); // same, for another city
PrayerTimes::minutesUntilIftar('Dubai');   // 137, or null once Maghrib has passed
PrayerTimes::today()->is_ramadan;          // straight from the API's Hijri calendar
```

Both helpers count in the **city's own timezone**, so an app running in UTC still gets the right
answer for Makkah or Toronto.

## Reminders

Turn them on in config, then listen for the event anywhere in your app:

```php
use Mubbashir786\PrayerTimes\Events\PrayerTimeApproaching;

Event::listen(PrayerTimeApproaching::class, function (PrayerTimeApproaching $event) {
    // $event->prayerName, $event->prayerTime, $event->minutesRemaining
    Notification::send(User::all(), new PrayerReminder($event->prayerName, $event->prayerTime));
});
```

The package registers `prayer-times:check-reminders` on Laravel's scheduler (every minute), so all
you need is a running scheduler — `php artisan schedule:work` locally, or the usual cron entry in
production. You can also run it by hand for a specific place:

```bash
php artisan prayer-times:check-reminders --city=Lahore
```

A ready-made `PrayerReminder` notification is included, with mail and database representations.

## How caching works

One row per `(city, date)` in the `prayer_times` table. A day's times are fetched from the API
the first time they are asked for and served from the database after that. A row is refetched when:

- it is older than `cache_ttl_hours` (default: a week; `0` disables expiry),
- the coordinates it was stored against no longer match the ones the city resolves to — so a row
  written before you added a city to the map is corrected rather than served for the wrong place, or
- it was resolved by name and the city has since gained real coordinates in the map, or
- its Hijri offset no longer matches the one the country resolves to.

> **Note** — a row resolved by city *name* keeps `latitude` and `longitude` as `null`. The
> `/timingsByCity` response reports the same placeholder coordinates for every city on earth, so
> the package stores nothing rather than something wrong. The times and the timezone are real.
> Add the city to the `cities` map if you want the coordinates on the row.

## Testing

```bash
composer install
```

```bash
composer test
```

78 tests cover city resolution and the endpoint each path picks, caching and invalidation,
timezone handling, the Hijri calendar and its per-country offsets, all twelve languages, the
Ramadan helpers, the Blade widget and the reminder command — all against a mocked HTTP client, so
the suite never touches the network.

Six end-to-end tests run against the live Aladhan API, including the Saudi/Pakistan Eid split.
They are excluded by default and opt-in:

```bash
vendor/bin/phpunit --group=integration
```

## Roadmap

- Qibla direction helper (bearing calculation from lat/lng)
- Moon-sighting feeds, so the Hijri offset follows real announcements instead of a fixed number
- WhatsApp/SMS channel presets for reminders
- Ramadan calendar export (iCal) for Suhoor/Iftar times across the month

## License

MIT © [Mubbashir](https://github.com/mubbashir786). Prayer time data from the free
[Aladhan API](https://aladhan.com/prayer-times-api).
