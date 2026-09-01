<?php

namespace Mubbashir786\PrayerTimes;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Mubbashir786\PrayerTimes\Hijri\HijriCalendar;
use Mubbashir786\PrayerTimes\Hijri\HijriDate;
use Mubbashir786\PrayerTimes\Models\PrayerTime;

class PrayerTimesManager
{
    protected Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client();
    }

    /**
     * Get prayer times for a given date/city, using the cache table first.
     *
     * The city may be any name: if it is listed in config('prayer-times.cities')
     * its coordinates are used, otherwise the name is resolved by the API.
     * Explicit $lat/$lng always win over both.
     */
    public function forDate(?Carbon $date = null, ?string $city = null, ?float $lat = null, ?float $lng = null): PrayerTime
    {
        $date ??= Carbon::today();
        $location = $this->resolveLocation($city, $lat, $lng);

        $cached = PrayerTime::where('city', $location['city'])
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($cached && $this->cacheIsUsable($cached, $location)) {
            return $cached;
        }

        $data = $this->fetchFromApi($date, $location);

        $attributes = [
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'timezone' => $data['timezone'],
            'hijri_day' => $data['hijri_day'],
            'hijri_month' => $data['hijri_month'],
            'hijri_year' => $data['hijri_year'],
            'hijri_adjustment' => $data['hijri_adjustment'],
            'fajr' => $data['fajr'],
            'sunrise' => $data['sunrise'],
            'dhuhr' => $data['dhuhr'],
            'asr' => $data['asr'],
            'maghrib' => $data['maghrib'],
            'isha' => $data['isha'],
        ];

        // Update the row we already found rather than using updateOrCreate: its
        // lookup would compare the raw date string against the cast column and
        // miss, then trip the (city, date) unique index on insert.
        if ($cached) {
            $cached->fill($attributes)->save();

            return $cached;
        }

        return PrayerTime::create($attributes + [
            'city' => $location['city'],
            'date' => $date->toDateString(),
        ]);
    }

    /**
     * Today's prayer times (convenience wrapper).
     */
    public function today(?string $city = null, ?float $lat = null, ?float $lng = null): PrayerTime
    {
        return $this->forDate(Carbon::today(), $city, $lat, $lng);
    }

    /**
     * Prayer times for a city across a date range, keyed by Y-m-d.
     *
     * @return array<string, PrayerTime>
     */
    public function forRange(Carbon $from, Carbon $to, ?string $city = null, ?float $lat = null, ?float $lng = null): array
    {
        $days = [];

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            $days[$date->toDateString()] = $this->forDate($date->copy(), $city, $lat, $lng);
        }

        return $days;
    }

    /**
     * City names known to the config map (the ones that need no API lookup).
     *
     * @return array<int, string>
     */
    public function cities(): array
    {
        return array_keys(config('prayer-times.cities', []));
    }

    /**
     * The Hijri date for a given day and city, as a value object.
     */
    public function hijri(?Carbon $date = null, ?string $city = null, ?float $lat = null, ?float $lng = null): HijriDate
    {
        return $this->forDate($date, $city, $lat, $lng)->hijri;
    }

    /**
     * The locale month names, prayer names and widget labels are rendered in.
     */
    public function locale(): string
    {
        return $this->calendar()->locale();
    }

    /**
     * Set that locale for the rest of the request, e.g. PrayerTimes::setLocale('ur').
     *
     * Pass null to go back to following the application locale.
     */
    public function setLocale(?string $locale): static
    {
        $this->calendar()->setLocale($locale);

        return $this;
    }

    /**
     * All twelve Hijri month names, keyed 1-12.
     *
     * @return array<int, string>
     */
    public function hijriMonths(?string $locale = null): array
    {
        return $this->calendar()->months($locale);
    }

    /**
     * A single Hijri month name by number, e.g. hijriMonth(9, 'ur') === 'رمضان'.
     */
    public function hijriMonth(int $month, ?string $locale = null): string
    {
        return $this->calendar()->monthName($month, $locale);
    }

    /**
     * Translate a prayer name, e.g. prayerName('Fajr', 'ar') === 'الفجر'.
     */
    public function prayerName(string $prayer, ?string $locale = null): string
    {
        return $this->calendar()->prayerName($prayer, $locale);
    }

    /**
     * Locales this package can render.
     *
     * @return array<int, string>
     */
    public function locales(): array
    {
        return $this->calendar()->availableLocales();
    }

    protected function calendar(): HijriCalendar
    {
        return app(HijriCalendar::class);
    }

    /**
     * Suhoor cutoff time (Fajr minus configured buffer), useful during Ramadan.
     */
    public function suhoorCutoff(?Carbon $date = null, ?string $city = null, ?float $lat = null, ?float $lng = null): string
    {
        $prayerTime = $this->forDate($date, $city, $lat, $lng);
        $buffer = config('prayer-times.ramadan.suhoor_buffer_minutes', 10);

        return Carbon::parse($prayerTime->fajr, $prayerTime->timezoneName())->subMinutes($buffer)->format('H:i');
    }

    /**
     * Minutes remaining until Iftar (Maghrib) today, in the city's own timezone.
     */
    public function minutesUntilIftar(?string $city = null, ?float $lat = null, ?float $lng = null): ?int
    {
        $prayerTime = $this->today($city, $lat, $lng);
        $maghrib = Carbon::parse($prayerTime->maghrib, $prayerTime->timezoneName());

        if ($maghrib->isPast()) {
            return null;
        }

        return (int) round(now()->diffInMinutes($maghrib));
    }

    /**
     * Work out which location a call refers to.
     *
     * Order of precedence: explicit coordinates > config city map > API city
     * lookup > the configured default location.
     *
     * @return array{city: string, country: string, latitude: ?float, longitude: ?float, timezone: ?string, hijri_adjustment: int}
     */
    public function resolveLocation(?string $city = null, ?float $lat = null, ?float $lng = null): array
    {
        $default = config('prayer-times.default_location');

        if ($city === null || trim($city) === '') {
            $location = [
                'city' => $default['city'],
                'country' => $default['country'],
                'latitude' => $lat ?? (float) $default['latitude'],
                'longitude' => $lng ?? (float) $default['longitude'],
                'timezone' => $default['timezone'],
            ];

            return $location + ['hijri_adjustment' => $this->hijriAdjustmentFor($location)];
        }

        $city = trim($city);
        $known = $this->lookupCity($city);

        $location = [
            // Use the config map's spelling when it matches, so the cache key is stable.
            'city' => $known['name'] ?? $city,
            'country' => $known['country'] ?? config('prayer-times.fallback_country', $default['country']),
            'latitude' => $lat ?? $known['latitude'] ?? null,
            'longitude' => $lng ?? $known['longitude'] ?? null,
            'timezone' => $known['timezone'] ?? null,
        ];

        return $location + [
            'hijri_adjustment' => $known['hijri_adjustment'] ?? $this->hijriAdjustmentFor($location),
        ];
    }

    /**
     * How many days to shift the Hijri date for this location.
     *
     * A city entry's own 'hijri_adjustment' wins, then the per-country map, then
     * the global default.
     */
    protected function hijriAdjustmentFor(array $location): int
    {
        $byCountry = config('prayer-times.hijri.adjustments', []);

        foreach ($byCountry as $country => $adjustment) {
            if (mb_strtolower($country) === mb_strtolower((string) $location['country'])) {
                return (int) $adjustment;
            }
        }

        return (int) config('prayer-times.hijri.adjustment', 0);
    }

    /**
     * Find a city in the config map, case-insensitively.
     *
     * @return array{name: string, latitude: float, longitude: float, timezone: ?string, country: ?string, hijri_adjustment: ?int}|null
     */
    protected function lookupCity(string $city): ?array
    {
        foreach (config('prayer-times.cities', []) as $name => $entry) {
            if (mb_strtolower($name) !== mb_strtolower($city)) {
                continue;
            }

            return [
                'name' => $name,
                'latitude' => (float) $entry['latitude'],
                'longitude' => (float) $entry['longitude'],
                'timezone' => $entry['timezone'] ?? null,
                'country' => $entry['country'] ?? null,
                'hijri_adjustment' => isset($entry['hijri_adjustment']) ? (int) $entry['hijri_adjustment'] : null,
            ];
        }

        return null;
    }

    /**
     * Whether a cached row can still be served for this location.
     *
     * Rows cached against different coordinates (e.g. written before a city was
     * added to the map) are refetched rather than returned for the wrong place.
     */
    protected function cacheIsUsable(PrayerTime $cached, array $location): bool
    {
        $ttlHours = (int) config('prayer-times.cache_ttl_hours', 24 * 7);

        if ($ttlHours > 0 && $cached->updated_at && $cached->updated_at->lt(now()->subHours($ttlHours))) {
            return false;
        }

        if ($cached->hijri_adjustment !== $location['hijri_adjustment']) {
            // The Hijri offset for this country changed in config.
            return false;
        }

        if ($location['latitude'] === null || $location['longitude'] === null) {
            // Resolved by name, so there is nothing to compare against.
            return true;
        }

        if ($cached->latitude === null || $cached->longitude === null) {
            // The row was stored by name and we now know real coordinates
            // (e.g. the city was just added to the config map).
            return false;
        }

        return abs((float) $cached->latitude - $location['latitude']) < 0.01
            && abs((float) $cached->longitude - $location['longitude']) < 0.01;
    }

    /**
     * Call the Aladhan API for a single day's timings.
     *
     * Uses /timings when coordinates are known, and /timingsByCity otherwise so
     * that any city name still resolves.
     */
    protected function fetchFromApi(Carbon $date, array $location): array
    {
        $day = $date->format('d-m-Y');
        $common = [
            'method' => config('prayer-times.calculation_method'),
            'school' => config('prayer-times.asr_school'),
        ];

        if ($location['latitude'] !== null && $location['longitude'] !== null) {
            $endpoint = '/timings/' . $day;
            $query = $common + [
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
            ];
        } else {
            $endpoint = '/timingsByCity/' . $day;
            $query = $common + [
                'city' => $location['city'],
                'country' => $location['country'],
            ];
        }

        $response = $this->http->get(config('prayer-times.api_base_url') . $endpoint, ['query' => $query]);
        $body = json_decode($response->getBody()->getContents(), true);

        return $this->parseResponse($body, $location, $this->fetchHijri($date, $location, $body));
    }

    /**
     * The Hijri date for this row.
     *
     * With no adjustment it is whatever the timings response reported (the Saudi
     * HJCoSA calendar). Otherwise the Gregorian date is shifted and converted by
     * the API, which keeps 29/30-day month boundaries correct - so a -1 country
     * reads 30 Ramadan on the day Saudi Arabia reads 1 Shawwal.
     */
    protected function fetchHijri(Carbon $date, array $location, array $body): array
    {
        $adjustment = (int) $location['hijri_adjustment'];

        if ($adjustment === 0) {
            return $body['data']['date']['hijri'];
        }

        $shifted = $date->copy()->addDays($adjustment);

        $response = $this->http->get(
            config('prayer-times.api_base_url') . '/gToH/' . $shifted->format('d-m-Y')
        );

        return json_decode($response->getBody()->getContents(), true)['data']['hijri'];
    }

    /**
     * Normalise an Aladhan payload into the columns we store.
     */
    protected function parseResponse(array $body, array $location, array $hijri): array
    {
        $timings = $body['data']['timings'];
        $meta = $body['data']['meta'] ?? [];
        $hijriDate = HijriDate::fromApi($hijri, (int) $location['hijri_adjustment']);

        return [
            'fajr' => $this->stripTimezone($timings['Fajr']),
            'sunrise' => $this->stripTimezone($timings['Sunrise']),
            'dhuhr' => $this->stripTimezone($timings['Dhuhr']),
            'asr' => $this->stripTimezone($timings['Asr']),
            'maghrib' => $this->stripTimezone($timings['Maghrib']),
            'isha' => $this->stripTimezone($timings['Isha']),
            'hijri_day' => $hijriDate->day,
            'hijri_month' => $hijriDate->month,
            'hijri_year' => $hijriDate->year,
            'hijri_adjustment' => $hijriDate->adjustment,
            // For a /timingsByCity call these come back from the API, which is
            // how an unlisted city still ends up with real coordinates stored.
            // Only the coordinates we resolved ourselves are stored. A
            // /timingsByCity response carries a fixed placeholder in
            // meta.latitude/longitude (8.8888888, 7.7777777) for every city, so
            // those are left null - the timings and the timezone are real.
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'timezone' => $location['timezone'] ?? ($meta['timezone'] ?? config('prayer-times.default_location.timezone')),
        ];
    }

    protected function stripTimezone(string $time): string
    {
        // Aladhan returns times like "05:12 (PKT)" - keep just "05:12".
        return trim(explode(' ', $time)[0]);
    }
}
