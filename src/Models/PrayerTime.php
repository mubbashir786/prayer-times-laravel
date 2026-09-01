<?php

namespace Mubbashir786\PrayerTimes\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mubbashir786\PrayerTimes\Casts\TimeOfDay;
use Mubbashir786\PrayerTimes\Hijri\HijriCalendar;
use Mubbashir786\PrayerTimes\Hijri\HijriDate;

class PrayerTime extends Model
{
    protected $fillable = [
        'city',
        'latitude',
        'longitude',
        'timezone',
        'date',
        'hijri_day',
        'hijri_month',
        'hijri_year',
        'hijri_adjustment',
        'fajr',
        'sunrise',
        'dhuhr',
        'asr',
        'maghrib',
        'isha',
    ];

    protected $casts = [
        'date' => 'date',
        'hijri_day' => 'integer',
        'hijri_month' => 'integer',
        'hijri_year' => 'integer',
        'hijri_adjustment' => 'integer',
        'fajr' => TimeOfDay::class,
        'sunrise' => TimeOfDay::class,
        'dhuhr' => TimeOfDay::class,
        'asr' => TimeOfDay::class,
        'maghrib' => TimeOfDay::class,
        'isha' => TimeOfDay::class,
    ];

    /** Derived values worth keeping in toArray()/toJson() output. */
    protected $appends = ['hijri_date', 'is_ramadan'];

    /**
     * The Hijri date this row falls on, as a value object.
     */
    public function getHijriAttribute(): HijriDate
    {
        return new HijriDate(
            (int) $this->hijri_day,
            (int) $this->hijri_month,
            (int) $this->hijri_year,
            (int) $this->hijri_adjustment,
        );
    }

    /**
     * The Hijri date rendered in the current locale, e.g. "30 Ramadan 1447".
     */
    public function getHijriDateAttribute(): string
    {
        return $this->hijri->format();
    }

    /**
     * Whether this row falls in Ramadan, after any Hijri adjustment.
     */
    public function getIsRamadanAttribute(): bool
    {
        return $this->hijri_month === 9;
    }

    /**
     * Only rows that fall in Ramadan.
     */
    public function scopeRamadan(Builder $query): Builder
    {
        return $query->where('hijri_month', 9);
    }

    /**
     * The timezone these times are expressed in, falling back to the configured default.
     */
    public function timezoneName(): string
    {
        return $this->timezone ?: config('prayer-times.default_location.timezone', config('app.timezone'));
    }

    /**
     * Get all five daily prayers as a simple [name => time] array.
     *
     * Keys are the canonical English names - use toLocalizedPrayerArray() for display.
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
     * The same five prayers, keyed by their name in the given (or current) locale.
     */
    public function toLocalizedPrayerArray(?string $locale = null): array
    {
        $calendar = app(HijriCalendar::class);
        $prayers = [];

        foreach ($this->toPrayerArray() as $name => $time) {
            $prayers[$calendar->prayerName($name, $locale)] = $time;
        }

        return $prayers;
    }

    /**
     * The next upcoming prayer name + time, relative to now in the city's own timezone.
     *
     * The name is the canonical English one; pass it through
     * PrayerTimes::prayerName() to display it in another language.
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
