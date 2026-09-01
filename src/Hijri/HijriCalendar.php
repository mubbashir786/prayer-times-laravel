<?php

namespace Mubbashir786\PrayerTimes\Hijri;

/**
 * Hijri month names, prayer names and the locale they are rendered in.
 *
 * Translations live in resources/lang and can be published and extended:
 *   php artisan vendor:publish --tag=prayer-times-lang
 */
class HijriCalendar
{
    /**
     * The locale package strings are rendered in.
     *
     * Falls back to the application's own locale when none is configured.
     */
    public function locale(): string
    {
        return config('prayer-times.locale') ?: app()->getLocale();
    }

    /**
     * Set the locale for everything this package renders, for the rest of the request.
     *
     * Pass null to go back to following the application locale.
     */
    public function setLocale(?string $locale): static
    {
        config(['prayer-times.locale' => $locale]);

        return $this;
    }

    /**
     * All twelve Hijri month names, keyed 1-12.
     *
     * @return array<int, string>
     */
    public function months(?string $locale = null): array
    {
        return $this->lines('hijri.months', $locale);
    }

    /**
     * A single Hijri month name, by its number (1-12).
     */
    public function monthName(int $month, ?string $locale = null): string
    {
        return $this->months($locale)[$month] ?? (string) $month;
    }

    /**
     * The era suffix for the locale ("AH", "ھ", "هـ").
     */
    public function era(?string $locale = null): string
    {
        $era = $this->line('hijri.era', $locale);

        return is_string($era) ? $era : 'AH';
    }

    /**
     * The five prayers plus sunrise, keyed by their canonical English name.
     *
     * @return array<string, string>
     */
    public function prayers(?string $locale = null): array
    {
        $names = [];

        foreach (['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'] as $prayer) {
            $names[$prayer] = $this->prayerName($prayer, $locale);
        }

        return $names;
    }

    /**
     * Translate a prayer name, e.g. prayerName('Fajr', 'ur') === 'فجر'.
     */
    public function prayerName(string $prayer, ?string $locale = null): string
    {
        $line = $this->line('prayers.' . mb_strtolower($prayer), $locale);

        return is_string($line) ? $line : $prayer;
    }

    /**
     * Whether the locale reads right-to-left, for the widget's dir attribute.
     */
    public function isRtl(?string $locale = null): bool
    {
        $locale ??= $this->locale();

        return in_array($locale, config('prayer-times.rtl_locales', []), true);
    }

    /**
     * Locales this package can render, including any you published and added.
     *
     * @return array<int, string>
     */
    public function availableLocales(): array
    {
        $paths = [__DIR__ . '/../../resources/lang', lang_path('vendor/prayer-times')];
        $locales = [];

        foreach ($paths as $path) {
            foreach (glob($path . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $locales[] = basename($dir);
            }
        }

        sort($locales);

        return array_values(array_unique($locales));
    }

    /**
     * Read a translation group, falling back to English when the locale has none.
     *
     * @return array<int|string, string>
     */
    protected function lines(string $key, ?string $locale): array
    {
        $lines = $this->line($key, $locale);

        return is_array($lines) ? $lines : [];
    }

    /**
     * Read a single translation, falling back to English when it is missing.
     */
    protected function line(string $key, ?string $locale): mixed
    {
        $locale ??= $this->locale();
        $namespaced = "prayer-times::{$key}";

        $line = trans($namespaced, [], $locale);

        // Laravel hands back the key itself when nothing matched.
        if ($line === $namespaced) {
            $line = trans($namespaced, [], 'en');
        }

        return $line === $namespaced ? null : $line;
    }
}
