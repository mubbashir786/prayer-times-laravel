<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Support\Carbon;

class WidgetTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_renders_the_default_city(): void
    {
        $this->fakeApi([$this->payload()]);

        $view = $this->blade('<x-prayer-times::widget />');

        $view->assertSee('Islamabad');
        $view->assertSee('1 Ramadan 1447');
        $view->assertSee('6:47 PM');   // Maghrib, formatted
        $this->assertSame('33.6844', $this->apiQuery()['latitude']);
    }

    public function test_it_renders_a_named_city(): void
    {
        $this->fakeApi([$this->payload()]);

        $view = $this->blade('<x-prayer-times::widget city="lahore" />');

        $view->assertSee('Lahore');
        $this->assertSame('31.5204', $this->apiQuery()['latitude']);
    }

    public function test_it_accepts_coordinates(): void
    {
        $this->fakeApi([$this->payload()]);

        $view = $this->blade('<x-prayer-times::widget city="My Village" lat="31.10" lng="72.40" />');

        $view->assertSee('My Village');
        $this->assertSame('31.1', $this->apiQuery()['latitude']);
        $this->assertSame('72.4', $this->apiQuery()['longitude']);
    }

    public function test_it_accepts_a_date(): void
    {
        $this->fakeApi([$this->payload()]);

        $this->blade('<x-prayer-times::widget city="Karachi" date="2026-03-20" />');

        $this->assertStringContainsString('/timings/20-03-2026', $this->apiPath());
    }

    public function test_it_highlights_the_next_prayer(): void
    {
        Carbon::setTestNow('2026-03-20 12:00:00'); // 17:00 PKT - Maghrib is next
        $this->fakeApi([$this->payload()]);

        $view = $this->blade('<x-prayer-times::widget city="Lahore" />');

        $view->assertSee('Next: Maghrib at 6:47 PM');
    }

    public function test_it_shows_the_ramadan_banner_only_during_ramadan(): void
    {
        $this->fakeApi([
            $this->payload(),
            $this->payload(['hijri' => ['day' => '3', 'month' => ['en' => 'Shawwal', 'number' => 10], 'year' => '1447']]),
        ]);

        $this->blade('<x-prayer-times::widget city="Lahore" />')->assertSee('Ramadan Mubarak', false);
        $this->blade('<x-prayer-times::widget city="Karachi" />')->assertDontSee('Ramadan Mubarak', false);
    }
}
