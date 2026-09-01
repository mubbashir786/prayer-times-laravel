<?php

namespace Mubbashir786\PrayerTimes\Models;

use Illuminate\Database\Eloquent\Model;
use Mubbashir786\PrayerTimes\Casts\TimeOfDay;

class PrayerTime extends Model
{
    protected $fillable = [
        'city',
        'latitude',
        'longitude',
        'timezone',
        'date',
        'hijri_date',
        'is_ramadan',
        'fajr',
        'sunrise',
        'dhuhr',
        'asr',
        'maghrib',
        'isha',
    ];

    protected $casts = [
        'date' => 'date',
        'is_ramadan' => 'boolean',
        'fajr' => TimeOfDay::class,
        'sunrise' => TimeOfDay::class,
        'dhuhr' => TimeOfDay::class,
        'asr' => TimeOfDay::class,
        'maghrib' => TimeOfDay::class,
        'isha' => TimeOfDay::class,
    ];

    /**
     * Get all five daily prayers as a simple [name => time] array.
     */
    public function toPrayerArray(): array
    {
        return [
            'Fajr' => $this->fajr,
            'Dhuhr' => $this->dhuhr,
            'Asr' => $this->asr,
            'Maghrib' => $this->maghrib,
            'Isha' => $this->isha,
        ];
    }

    /**
     * The timezone these times are expressed in, falling back to the configured default.
     */
    public function timezoneName(): string
    {
        return $this->timezone ?: config('prayer-times.default_location.timezone', config('app.timezone'));
    }

    /**
     * The next upcoming prayer name + time, relative to now in the city's own timezone.
     */
    public function nextPrayer(): ?array
    {
        $now = now($this->timezoneName())->format('H:i');

        foreach ($this->toPrayerArray() as $name => $time) {
            if ($time > $now) {
                return ['name' => $name, 'time' => $time];
            }
        }

        // All of today's prayers have passed; caller should fetch tomorrow's Fajr.
        return null;
    }
}
