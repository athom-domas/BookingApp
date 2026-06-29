<ul class="sf-hours-list">
    @foreach($days as $key => $label)
    @php
        $day     = $profile->opening_hours[$key] ?? null;
        $dayType = $day['type'] ?? null;
        $isOpen  = $day && in_array($dayType, ['split', 'continuous']);
    @endphp
    <li class="sf-hours-item {{ $key === $todayKey ? 'is-today' : '' }} {{ !$isOpen ? 'is-closed' : '' }}">
        <div class="sf-hours-day">{{ $label }} @if($key === $todayKey)<span class="sf-today-badge">oggi</span>@endif</div>
        <div class="sf-hours-time">
            @if($isOpen)
                @if($dayType === 'continuous')
                    {{ $day['open_time'] }}–{{ $day['close_time'] }}
                @else
                    {{ $day['morning_open'] ?? '09:00' }}–{{ $day['morning_close'] ?? '13:00' }}
                    @if(!empty($day['afternoon_open']) && !empty($day['afternoon_close']))
                        &thinsp;/&thinsp;{{ $day['afternoon_open'] }}–{{ $day['afternoon_close'] }}
                    @endif
                @endif
            @else
                Chiuso
            @endif
        </div>
    </li>
    @endforeach
</ul>
