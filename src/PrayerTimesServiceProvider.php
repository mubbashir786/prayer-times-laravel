<?php

namespace Mubbashir786\PrayerTimes;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Mubbashir786\PrayerTimes\Console\Commands\CheckPrayerReminders;
use Mubbashir786\PrayerTimes\Hijri\HijriCalendar;

class PrayerTimesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/prayer-times.php', 'prayer-times');

        $this->app->singleton(HijriCalendar::class, fn () => new HijriCalendar());

        $this->app->singleton('prayer-times', function () {
            return new PrayerTimesManager();
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'prayer-times');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'prayer-times');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/prayer-times.php' => config_path('prayer-times.php'),
            ], 'prayer-times-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/prayer-times'),
            ], 'prayer-times-views');

            $this->publishes([
                __DIR__ . '/../resources/lang' => lang_path('vendor/prayer-times'),
            ], 'prayer-times-lang');

            $this->commands([
                CheckPrayerReminders::class,
            ]);
        }

        // Anonymous Blade component, usable as: <x-prayer-times::widget city="Islamabad" />
        // (resolves automatically since the view is registered under the 'prayer-times' namespace)

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('prayer-times:check-reminders')->everyMinute();
        });
    }
}
