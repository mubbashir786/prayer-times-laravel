<?php

namespace Mubbashir786\PrayerTimes\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Mubbashir786\PrayerTimes\Events\PrayerTimeApproaching;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;

class CheckPrayerReminders extends Command
{
    protected $signature = 'prayer-times:check-reminders {--city=}';

    protected $description = 'Check today\'s prayer times and dispatch reminder events for prayers approaching soon.';

    public function handle(): int
    {
        if (! config('prayer-times.reminders.enabled')) {
            $this->info('Prayer time reminders are disabled in config.');

            return self::SUCCESS;
        }

        $city = $this->option('city');
        $prayerTime = PrayerTimes::today($city);
        $minutesBefore = config('prayer-times.reminders.minutes_before', 15);

        foreach ($prayerTime->toPrayerArray() as $name => $time) {
            $prayerAt = Carbon::parse($time);
            $minutesRemaining = now()->diffInMinutes($prayerAt, false);

            // Fire once, inside a 1-minute window around the configured lead time.
            if ($minutesRemaining === $minutesBefore) {
                PrayerTimeApproaching::dispatch($name, $time, $minutesRemaining);
                $this->info("Dispatched reminder: {$name} at {$time} ({$minutesRemaining} min away)");
            }
        }

        return self::SUCCESS;
    }
}
