@php
    // Usage: <x-prayer-times::widget city="Lahore" />
    //        <x-prayer-times::widget city="My Village" lat="31.10" lng="72.40" />
    //        <x-prayer-times::widget city="Lahore" date="2026-03-20" />
    $prayerTime = \Mubbashir786\PrayerTimes\Facades\PrayerTimes::forDate(
        isset($date) ? \Illuminate\Support\Carbon::parse($date) : null,
        $city ?? null,
        isset($lat) ? (float) $lat : null,
        isset($lng) ? (float) $lng : null,
    );
    $next = $prayerTime->nextPrayer();
@endphp

<div class="prayer-times-widget" style="border:1px solid #e2e2e2;border-radius:8px;padding:16px;font-family:sans-serif;">
    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:4px;">
        <span style="font-weight:600;">{{ $prayerTime->city }}</span>
        <span style="font-size:12px;color:#666;">{{ $prayerTime->hijri_date }}</span>
    </div>

    @if($prayerTime->is_ramadan)
        <div style="color:#2c7a4b;font-size:13px;margin-bottom:8px;">Ramadan Mubarak 🌙</div>
    @endif

    <ul style="list-style:none;padding:0;margin:0;">
        @foreach($prayerTime->toPrayerArray() as $name => $time)
            <li style="display:flex;justify-content:space-between;padding:4px 0;{{ $next && $next['name'] === $name ? 'font-weight:700;' : '' }}">
                <span>{{ $name }}</span>
                <span>{{ \Illuminate\Support\Carbon::parse($time)->format('g:i A') }}</span>
            </li>
        @endforeach
    </ul>

    @if($next)
        <div style="margin-top:8px;font-size:13px;color:#666;">
            Next: {{ $next['name'] }} at {{ \Illuminate\Support\Carbon::parse($next['time'])->format('g:i A') }}
        </div>
    @endif
</div>
