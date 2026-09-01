# Prayer Times & Ramadan Scheduler for Laravel

Fetches, caches, and serves daily prayer times (via the free [Aladhan API](https://aladhan.com/prayer-times-api)), with Ramadan-aware helpers (Suhoor cutoff, Iftar countdown), a Blade widget, and an event you can hook reminders into.

## Install

```bash
composer require mubbashir786/prayer-times-laravel
php artisan vendor:publish --tag=prayer-times-config
php artisan migrate
```

## Configure

Edit `config/prayer-times.php` or set env vars:

```env
PRAYER_TIMES_CITY=Islamabad
PRAYER_TIMES_LAT=33.6844
PRAYER_TIMES_LNG=73.0479
PRAYER_TIMES_TZ=Asia/Karachi
PRAYER_TIMES_METHOD=1
PRAYER_TIMES_ASR_SCHOOL=1
PRAYER_TIMES_REMINDERS_ENABLED=true
```

## Usage

```php
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;

$today = PrayerTimes::today();
$today->fajr;      // "05:12"
$today->maghrib;   // "18:47"
$today->is_ramadan; // true/false
$today->nextPrayer(); // ['name' => 'Asr', 'time' => '16:20']

PrayerTimes::suhoorCutoff();        // "05:02" (Fajr minus buffer)
PrayerTimes::minutesUntilIftar();   // 137
```

### Blade widget

```blade
<x-prayer-times::widget city="Islamabad" />
```

### Reminders

Enable reminders in config, then listen for the event anywhere in your app (e.g. `AppServiceProvider` or an `EventServiceProvider`):

```php
use Mubbashir786\PrayerTimes\Events\PrayerTimeApproaching;

Event::listen(PrayerTimeApproaching::class, function (PrayerTimeApproaching $event) {
    // e.g. notify all users, push to a queue, broadcast via Reverb, etc.
    // $event->prayerName, $event->prayerTime, $event->minutesRemaining
});
```

The package registers `prayer-times:check-reminders` on Laravel's scheduler automatically (runs every minute), as long as your app's scheduler is running (`php artisan schedule:work` locally, or a cron entry in production).

## Roadmap ideas

- Qibla direction helper (bearing calculation from lat/lng)
- Multi-city support with a `city` scope on the widget
- WhatsApp/SMS channel presets for reminders
- Ramadan calendar export (iCal) for Suhoor/Iftar times across the month

## License

MIT
