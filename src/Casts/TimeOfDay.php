<?php

namespace Mubbashir786\PrayerTimes\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps prayer times as plain "HH:MM" strings on the model.
 *
 * Without this, a freshly fetched row hands back "05:12" while the same row
 * read from the database hands back "05:12:00".
 */
class TimeOfDay implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : substr((string) $value, 0, 5);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        // Store full precision, whether we were handed "05:12" or "05:12:00".
        return substr($value, 0, 5) . ':00';
    }
}
