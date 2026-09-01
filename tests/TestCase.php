<?php

namespace Mubbashir786\PrayerTimes\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Facade;
use Mubbashir786\PrayerTimes\PrayerTimesManager;
use Mubbashir786\PrayerTimes\PrayerTimesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    protected array $history = [];

    protected function getPackageProviders($app): array
    {
        return [PrayerTimesServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['PrayerTimes' => \Mubbashir786\PrayerTimes\Facades\PrayerTimes::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('app.locale', 'en');
        // The shipped config shifts Pakistan a day behind Saudi Arabia, which
        // costs an extra API call. Tests that care opt in explicitly.
        $app['config']->set('prayer-times.hijri.adjustments', []);
        $app['config']->set('prayer-times.locale', null);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /**
     * Queue up API responses and swap in a Guzzle client that records requests.
     *
     * @param  array<int, array<string, mixed>>  $payloads
     */
    protected function fakeApi(array $payloads): void
    {
        $mock = new MockHandler(array_map(
            fn (array $payload) => new Response(200, ['Content-Type' => 'application/json'], json_encode($payload)),
            $payloads
        ));

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        $client = new Client(['handler' => $stack]);

        $this->app->forgetInstance('prayer-times');
        Facade::clearResolvedInstance('prayer-times');
        $this->app->singleton('prayer-times', fn () => new PrayerTimesManager($client));
    }

    /**
     * A realistic Aladhan payload, with per-test overrides for timings/meta/hijri.
     */
    protected function payload(array $overrides = []): array
    {
        $timings = array_merge([
            'Fajr' => '05:12 (PKT)',
            'Sunrise' => '06:31 (PKT)',
            'Dhuhr' => '12:10 (PKT)',
            'Asr' => '16:20 (PKT)',
            'Maghrib' => '18:47 (PKT)',
            'Isha' => '20:05 (PKT)',
        ], $overrides['timings'] ?? []);

        $hijri = array_merge([
            'day' => '1',
            'month' => ['en' => 'Ramadan', 'number' => 9],
            'year' => '1447',
        ], $overrides['hijri'] ?? []);

        // The real API returns these exact placeholder coordinates from
        // /timingsByCity for every city, which is why they are never stored.
        $meta = array_merge([
            'latitude' => 8.8888888,
            'longitude' => 7.7777777,
            'timezone' => 'Asia/Karachi',
        ], $overrides['meta'] ?? []);

        return ['data' => ['timings' => $timings, 'date' => ['hijri' => $hijri], 'meta' => $meta]];
    }

    /**
     * An Aladhan /gToH payload, used when a Hijri adjustment is in play.
     */
    protected function gToHPayload(int $day = 30, int $month = 9, string $monthName = 'Ramadan', int $year = 1447): array
    {
        return ['data' => ['hijri' => [
            'day' => (string) $day,
            'month' => ['number' => $month, 'en' => $monthName, 'days' => 30],
            'year' => (string) $year,
        ]]];
    }

    /** The number of API calls made so far. */
    protected function apiCallCount(): int
    {
        return count($this->history);
    }

    /** Path of the nth (0-indexed) API call, e.g. "/v1/timingsByCity/20-03-2026". */
    protected function apiPath(int $index = 0): string
    {
        return $this->history[$index]['request']->getUri()->getPath();
    }

    /** Query parameters of the nth (0-indexed) API call. */
    protected function apiQuery(int $index = 0): array
    {
        parse_str($this->history[$index]['request']->getUri()->getQuery(), $query);

        return $query;
    }
}
