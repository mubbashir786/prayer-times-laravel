@php
    // Usage: <x-prayer-times::widget city="Lahore" />
    //        <x-prayer-times::widget city="My Village" lat="31.10" lng="72.40" />
    //        <x-prayer-times::widget city="Lahore" date="2026-03-20" locale="ur" />
    use Mubbashir786\PrayerTimes\Facades\PrayerTimes;

    $prayerTime = PrayerTimes::forDate(
        isset($date) ? \Illuminate\Support\Carbon::parse($date) : null,
        $city ?? null,
        isset($lat) ? (float) $lat : null,
        isset($lng) ? (float) $lng : null,
    );
    $next = $prayerTime->nextPrayer();
    $lang = $locale ?? PrayerTimes::locale();
    $calendar = app(\Mubbashir786\PrayerTimes\Hijri\HijriCalendar::class);
    $rtl = $calendar->isRtl($lang);
@endphp

<div class="prayer-times-widget" @if($rtl) dir="rtl" @endif lang="{{ $lang }}"
     style="border:1px solid #e2e2e2;border-radius:8px;padding:16px;font-family:sans-serif;">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin-bottom:4px;">
        <span style="font-weight:600;">{{ $prayerTime->city }}</span>
        <span style="font-size:12px;color:#666;">{{ $prayerTime->hijri->format($lang) }}</span>
    </div>

    @if($prayerTime->is_ramadan)
        <div style="color:#2c7a4b;font-size:13px;margin-bottom:8px;">
            {{ trans('prayer-times::prayers.ramadan_greeting', [], $lang) }} 🌙
        </div>
    @endif

    <ul style="list-style:none;padding:0;margin:0;">
        @foreach($prayerTime->toPrayerArray() as $name => $time)
            <li style="display:flex;justify-content:space-between;padding:4px 0;{{ $next && $next['name'] === $name ? 'font-weight:700;' : '' }}">
                <span>{{ $calendar->prayerName($name, $lang) }}</span>
                <span dir="ltr">{{ \Illuminate\Support\Carbon::parse($time)->format('g:i A') }}</span>
            </li>
        @endforeach
    </ul>

    @if($next)
        <div style="margin-top:8px;font-size:13px;color:#666;">
            {{ trans('prayer-times::prayers.next', [], $lang) }}:
            {{ $calendar->prayerName($next['name'], $lang) }}
            <span dir="ltr">{{ \Illuminate\Support\Carbon::parse($next['time'])->format('g:i A') }}</span>
        </div>
    @endif
</div>
