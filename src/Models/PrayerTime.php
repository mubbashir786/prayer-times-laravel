<?php

namespace Mubbashir786\PrayerTimes\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerTime extends Model
{
    protected $fillable = [
        'city',
        'latitude',
        'longitude',
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
     * The next upcoming prayer name + time relative to now.
     */
    public function nextPrayer(): ?array
    {
        $now = now()->format('H:i:s');

        foreach ($this->toPrayerArray() as $name => $time) {
            if ($time > $now) {
                return ['name' => $name, 'time' => $time];
            }
        }

        // All of today's prayers have passed; caller should fetch tomorrow's Fajr.
        return null;
    }
}
