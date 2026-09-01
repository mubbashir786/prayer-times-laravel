<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves v1.0.0's `hijri_date` string and `is_ramadan` flag onto the structured
 * columns the Hijri calendar needs.
 *
 * A fresh install already gets the new shape from the create migration, so this
 * is a no-op there. Existing rows keep their prayer times and simply refetch
 * their Hijri date the next time they are asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prayer_times') || Schema::hasColumn('prayer_times', 'hijri_day')) {
            return;
        }

        Schema::table('prayer_times', function (Blueprint $table) {
            $table->unsignedTinyInteger('hijri_day')->nullable()->after('date');
            $table->unsignedTinyInteger('hijri_month')->nullable()->index()->after('hijri_day');
            $table->unsignedSmallInteger('hijri_year')->nullable()->after('hijri_month');
            $table->tinyInteger('hijri_adjustment')->default(0)->after('hijri_year');
        });

        Schema::table('prayer_times', function (Blueprint $table) {
            $table->dropColumn(['hijri_date', 'is_ramadan']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prayer_times') || ! Schema::hasColumn('prayer_times', 'hijri_day')) {
            return;
        }

        Schema::table('prayer_times', function (Blueprint $table) {
            $table->string('hijri_date')->nullable()->after('date');
            $table->boolean('is_ramadan')->default(false)->after('hijri_date');
        });

        Schema::table('prayer_times', function (Blueprint $table) {
            // SQLite refuses to drop a column an index still points at.
            $table->dropIndex(['hijri_month']);
        });

        Schema::table('prayer_times', function (Blueprint $table) {
            $table->dropColumn(['hijri_day', 'hijri_month', 'hijri_year', 'hijri_adjustment']);
        });
    }
};
