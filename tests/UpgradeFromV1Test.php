<?php

namespace Mubbashir786\PrayerTimes\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mubbashir786\PrayerTimes\Facades\PrayerTimes;
use Mubbashir786\PrayerTimes\Models\PrayerTime;

/**
 * Upgrading an app that already installed v1.0.0 and ran its migration.
 */
class UpgradeFromV1Test extends TestCase
{
    public function test_the_upgrade_migration_is_a_no_op_on_a_fresh_install(): void
    {
        // The suite migrates from scratch, so the new shape is already in place.
        $this->assertTrue(Schema::hasColumns('prayer_times', [
            'hijri_day', 'hijri_month', 'hijri_year', 'hijri_adjustment',
        ]));
        $this->assertFalse(Schema::hasColumn('prayer_times', 'hijri_date'));
        $this->assertFalse(Schema::hasColumn('prayer_times', 'is_ramadan'));
    }

    public function test_it_swaps_the_v1_columns_and_keeps_the_prayer_times(): void
    {
        $this->rebuildV1Table();

        DB::table('prayer_times')->insert([
            'city' => 'Lahore',
            'latitude' => 31.5204,
            'longitude' => 74.3587,
            'timezone' => 'Asia/Karachi',
            'date' => '2026-03-20',
            'hijri_date' => '1 Shawwāl 1447',
            'is_ramadan' => false,
            'fajr' => '05:12:00', 'sunrise' => '06:31:00', 'dhuhr' => '12:10:00',
            'asr' => '16:20:00', 'maghrib' => '18:47:00', 'isha' => '20:05:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->runUpgradeMigration();

        $this->assertTrue(Schema::hasColumns('prayer_times', [
            'hijri_day', 'hijri_month', 'hijri_year', 'hijri_adjustment',
        ]));
        $this->assertFalse(Schema::hasColumn('prayer_times', 'hijri_date'));
        $this->assertFalse(Schema::hasColumn('prayer_times', 'is_ramadan'));

        // The expensive part - the prayer times themselves - survives untouched.
        $row = PrayerTime::first();
        $this->assertSame('Lahore', $row->city);
        $this->assertSame('05:12', $row->fajr);
        $this->assertSame('18:47', $row->maghrib);
        $this->assertNull($row->hijri_day);
    }

    public function test_an_upgraded_row_refetches_its_hijri_date_on_first_use(): void
    {
        $this->rebuildV1Table();
        DB::table('prayer_times')->insert([
            'city' => 'Lahore',
            'latitude' => 31.5204,
            'longitude' => 74.3587,
            'timezone' => 'Asia/Karachi',
            'date' => Carbon::today()->toDateString(),
            'hijri_date' => '1 Shawwāl 1447',
            'is_ramadan' => false,
            'fajr' => '05:20:00', 'sunrise' => '06:40:00', 'dhuhr' => '12:15:00',
            'asr' => '16:25:00', 'maghrib' => '18:50:00', 'isha' => '20:10:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->runUpgradeMigration();

        $this->fakeApi([$this->payload()]);
        $row = PrayerTimes::today('Lahore');

        $this->assertSame(1, $this->apiCallCount(), 'the row with no Hijri date should refetch');
        $this->assertSame('1 Ramadan 1447', $row->hijri_date);
        $this->assertSame('05:12', $row->fajr);
        $this->assertSame(1, PrayerTime::count(), 'the row is updated, not duplicated');
    }

    public function test_the_upgrade_can_be_rolled_back(): void
    {
        $this->rebuildV1Table();
        $this->runUpgradeMigration();
        $this->runUpgradeMigration('down');

        $this->assertTrue(Schema::hasColumn('prayer_times', 'hijri_date'));
        $this->assertTrue(Schema::hasColumn('prayer_times', 'is_ramadan'));
        $this->assertFalse(Schema::hasColumn('prayer_times', 'hijri_day'));
    }

    /** Recreate the table exactly as v1.0.0's migration left it. */
    protected function rebuildV1Table(): void
    {
        Schema::dropIfExists('prayer_times');

        Schema::create('prayer_times', function ($table) {
            $table->id();
            $table->string('city')->index();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->string('timezone')->nullable();
            $table->date('date')->index();
            $table->string('hijri_date')->nullable();
            $table->boolean('is_ramadan')->default(false);
            $table->time('fajr');
            $table->time('sunrise');
            $table->time('dhuhr');
            $table->time('asr');
            $table->time('maghrib');
            $table->time('isha');
            $table->timestamps();
            $table->unique(['city', 'date']);
        });
    }

    protected function runUpgradeMigration(string $direction = 'up'): void
    {
        $migration = require __DIR__ . '/../database/migrations/2026_09_02_000000_upgrade_prayer_times_hijri_columns.php';
        $migration->{$direction}();
    }
}
