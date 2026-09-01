<?php

namespace Mubbashir786\PrayerTimes\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PrayerTimeApproaching
{
    use Dispatchable;

    public function __construct(
        public string $prayerName,
        public string $prayerTime,
        public int $minutesRemaining
    ) {}
}
