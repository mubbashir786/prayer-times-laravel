<?php

namespace Mubbashir786\PrayerTimes\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Mubbashir786\PrayerTimes\Events\PrayerTimeApproaching;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;

class CheckPrayerReminders extends Command
{
    protected $signature = 'prayer-times:check-reminders {--city=} {--lat=} {--lng=}';

    protected $description = 'Check today\'s prayer times and dispatch reminder events for prayers approaching soon.';

    public function handle(): int
    {
        if (! config('prayer-times.reminders.enabled')) {
            $this->info('Prayer time reminders are disabled in config.');

            return self::SUCCESS;
        }

        $city = $this->option('city');
        $lat = $this->option('lat') !== null ? (float) $this->option('lat') : null;
        $lng = $this->option('lng') !== null ? (float) $this->option('lng') : null;
        $prayerTime = PrayerTimes::today($city, $lat, $lng);
        $minutesBefore = config('prayer-times.reminders.minutes_before', 15);

        foreach ($prayerTime->toPrayerArray() as $name => $time) {
            $prayerAt = Carbon::parse($time, $prayerTime->timezoneName());
            // Carbon 3 returns a float here, so round before the strict comparison below.
            $minutesRemaining = (int) round(now()->diffInMinutes($prayerAt, false));

            // Fire once, inside a 1-minute window around the configured lead time.
            if ($minutesRemaining === $minutesBefore) {
                PrayerTimeApproaching::dispatch($name, $time, $minutesRemaining);
                $this->info("Dispatched reminder: {$name} at {$time} ({$minutesRemaining} min away)");
            }
        }

        return self::SUCCESS;
    }
}
