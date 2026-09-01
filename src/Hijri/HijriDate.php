<?php

namespace Mubbashir786\PrayerTimes\Hijri;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;

/**
 * One Hijri date, renderable in any of the package's locales.
 *
 * The Aladhan API reports the Saudi (HJCoSA) calendar. `$adjustment` records how
 * many days this date was shifted to match the country it belongs to - see
 * config('prayer-times.hijri').
 */
final class HijriDate implements Arrayable, JsonSerializable, Stringable
{
    public function __construct(
        public readonly int $day,
        public readonly int $month,
        public readonly int $year,
        public readonly int $adjustment = 0,
    ) {}

    /**
     * Build from the `hijri` block of an Aladhan response.
     */
    public static function fromApi(array $hijri, int $adjustment = 0): self
    {
        return new self(
            (int) $hijri['day'],
            (int) $hijri['month']['number'],
            (int) $hijri['year'],
            $adjustment,
        );
    }

    public function monthName(?string $locale = null): string
    {
        return $this->calendar()->monthName($this->month, $locale);
    }

    public function era(?string $locale = null): string
    {
        return $this->calendar()->era($locale);
    }

    public function isRamadan(): bool
    {
        return $this->month === 9;
    }

    /**
     * "30 Ramadan 1447" / "30 رمضان 1447" / "٣٠ رَمَضان ١٤٤٧"-style rendering.
     */
    public function format(?string $locale = null): string
    {
        return "{$this->day} {$this->monthName($locale)} {$this->year}";
    }

    /**
     * The same date with the era appended, e.g. "30 Ramadan 1447 AH".
     */
    public function formatWithEra(?string $locale = null): string
    {
        return $this->format($locale) . ' ' . $this->era($locale);
    }

    /**
     * Numeric form, e.g. "30-09-1447".
     */
    public function toDateString(): string
    {
        return sprintf('%02d-%02d-%04d', $this->day, $this->month, $this->year);
    }

    public function toArray(): array
    {
        return [
            'day' => $this->day,
            'month' => $this->month,
            'year' => $this->year,
            'month_name' => $this->monthName(),
            'adjustment' => $this->adjustment,
            'is_ramadan' => $this->isRamadan(),
            'formatted' => $this->format(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->format();
    }

    protected function calendar(): HijriCalendar
    {
        return app(HijriCalendar::class);
    }
}
