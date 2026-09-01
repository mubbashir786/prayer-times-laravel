<?php

namespace Mubbashir786\PrayerTimes\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrayerReminder extends Notification
{
    use Queueable;

    public function __construct(
        protected string $prayerName,
        protected string $prayerTime
    ) {}

    public function via(object $notifiable): array
    {
        return config('prayer-times.reminders.channels', ['database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->prayerName} is coming up")
            ->line("{$this->prayerName} prayer time is at {$this->prayerTime} today.")
            ->line('This is your reminder to prepare.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'prayer' => $this->prayerName,
            'time' => $this->prayerTime,
            'message' => "{$this->prayerName} is at {$this->prayerTime}",
        ];
    }
}
