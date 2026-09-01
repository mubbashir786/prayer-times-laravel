<?php

namespace Mubbashir786\PrayerTimes;

use Carbon\Carbon;
use GuzzleHttp\Client;
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
     */
    public function forDate(?Carbon $date = null, ?string $city = null, ?float $lat = null, ?float $lng = null): PrayerTime
    {
        $date ??= Carbon::today();
        $location = config('prayer-times.default_location');
        $city ??= $location['city'];
        $lat ??= $location['latitude'];
        $lng ??= $location['longitude'];

        $cached = PrayerTime::where('city', $city)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($cached) {
            return $cached;
        }

        $data = $this->fetchFromApi($date, $lat, $lng);

        return PrayerTime::updateOrCreate(
            ['city' => $city, 'date' => $date->toDateString()],
            [
                'latitude' => $lat,
                'longitude' => $lng,
                'hijri_date' => $data['hijri_date'],
                'is_ramadan' => $data['is_ramadan'],
                'fajr' => $data['fajr'],
                'sunrise' => $data['sunrise'],
                'dhuhr' => $data['dhuhr'],
                'asr' => $data['asr'],
                'maghrib' => $data['maghrib'],
                'isha' => $data['isha'],
            ]
        );
    }

    /**
     * Today's prayer times (convenience wrapper).
     */
    public function today(?string $city = null): PrayerTime
    {
        return $this->forDate(Carbon::today(), $city);
    }

    /**
     * Suhoor cutoff time (Fajr minus configured buffer), useful during Ramadan.
     */
    public function suhoorCutoff(?Carbon $date = null, ?string $city = null): string
    {
        $prayerTime = $this->forDate($date, $city);
        $buffer = config('prayer-times.ramadan.suhoor_buffer_minutes', 10);

        return Carbon::parse($prayerTime->fajr)->subMinutes($buffer)->format('H:i');
    }

    /**
     * Minutes remaining until Iftar (Maghrib) today.
     */
    public function minutesUntilIftar(?string $city = null): ?int
    {
        $prayerTime = $this->today($city);
        $maghrib = Carbon::parse($prayerTime->maghrib);

        if ($maghrib->isPast()) {
            return null;
        }

        return now()->diffInMinutes($maghrib);
    }

    /**
     * Call the Aladhan API for a single day's timings.
     */
    protected function fetchFromApi(Carbon $date, float $lat, float $lng): array
    {
        $response = $this->http->get(config('prayer-times.api_base_url') . '/timings/' . $date->format('d-m-Y'), [
            'query' => [
                'latitude' => $lat,
                'longitude' => $lng,
                'method' => config('prayer-times.calculation_method'),
                'school' => config('prayer-times.asr_school'),
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);
        $timings = $body['data']['timings'];
        $hijri = $body['data']['date']['hijri'];

        return [
            'fajr' => $this->stripTimezone($timings['Fajr']),
            'sunrise' => $this->stripTimezone($timings['Sunrise']),
            'dhuhr' => $this->stripTimezone($timings['Dhuhr']),
            'asr' => $this->stripTimezone($timings['Asr']),
            'maghrib' => $this->stripTimezone($timings['Maghrib']),
            'isha' => $this->stripTimezone($timings['Isha']),
            'hijri_date' => $hijri['day'] . ' ' . $hijri['month']['en'] . ' ' . $hijri['year'],
            'is_ramadan' => $hijri['month']['number'] === 9,
        ];
    }

    protected function stripTimezone(string $time): string
    {
        // Aladhan returns times like "05:12 (PKT)" - keep just "05:12".
        return trim(explode(' ', $time)[0]);
    }
}
