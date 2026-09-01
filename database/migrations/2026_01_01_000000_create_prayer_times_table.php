<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_times', function (Blueprint $table) {
            $table->id();
            $table->string('city')->index();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->string('timezone')->nullable();
            $table->date('date')->index();
            $table->unsignedTinyInteger('hijri_day')->nullable();
            $table->unsignedTinyInteger('hijri_month')->nullable()->index();
            $table->unsignedSmallInteger('hijri_year')->nullable();
            // Days this row's Hijri date was shifted by, so a change in config
            // refetches instead of serving the wrong country's calendar.
            $table->tinyInteger('hijri_adjustment')->default(0);
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

    public function down(): void
    {
        Schema::dropIfExists('prayer_times');
    }
};
