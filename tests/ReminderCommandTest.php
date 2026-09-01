<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Mubbashir786\PrayerTimes\Events\PrayerTimeApproaching;

class ReminderCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_does_nothing_when_reminders_are_disabled(): void
    {
        config()->set('prayer-times.reminders.enabled', false);
        Event::fake();
        $this->fakeApi([]);

        $this->artisan('prayer-times:check-reminders')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        Event::assertNotDispatched(PrayerTimeApproaching::class);
        $this->assertSame(0, $this->apiCallCount());
    }

    public function test_it_dispatches_an_event_at_the_configured_lead_time(): void
    {
        config()->set('prayer-times.reminders.enabled', true);
        config()->set('prayer-times.reminders.minutes_before', 15);
        // 13:32 UTC is 18:32 in Karachi - exactly 15 minutes before Maghrib.
        Carbon::setTestNow('2026-03-20 13:32:00');
        Event::fake();
        $this->fakeApi([$this->payload()]);

        $this->artisan('prayer-times:check-reminders', ['--city' => 'Lahore'])->assertSuccessful();

        Event::assertDispatched(PrayerTimeApproaching::class, function (PrayerTimeApproaching $event) {
            return $event->prayerName === 'Maghrib'
                && $event->prayerTime === '18:47'
                && $event->minutesRemaining === 15;
        });
        Event::assertDispatchedTimes(PrayerTimeApproaching::class, 1);
    }

    public function test_it_stays_quiet_outside_the_lead_time_window(): void
    {
        config()->set('prayer-times.reminders.enabled', true);
        config()->set('prayer-times.reminders.minutes_before', 15);
        Carbon::setTestNow('2026-03-20 13:20:00'); // 27 minutes before Maghrib
        Event::fake();
        $this->fakeApi([$this->payload()]);

        $this->artisan('prayer-times:check-reminders', ['--city' => 'Lahore'])->assertSuccessful();

        Event::assertNotDispatched(PrayerTimeApproaching::class);
    }

    public function test_it_accepts_explicit_coordinates(): void
    {
        config()->set('prayer-times.reminders.enabled', true);
        Carbon::setTestNow('2026-03-20 13:32:00');
        Event::fake();
        $this->fakeApi([$this->payload()]);

        $this->artisan('prayer-times:check-reminders', [
            '--city' => 'My Village', '--lat' => '31.10', '--lng' => '72.40',
        ])->assertSuccessful();

        $this->assertSame('31.1', $this->apiQuery()['latitude']);
        Event::assertDispatched(PrayerTimeApproaching::class);
    }
}
