<x-filament-panels::page>
    <style>
        .cal-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
        }
        .dark .cal-controls { background: #1f2937; border-color: #374151; }

        .cal-staff-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            min-width: 200px;
        }
        .cal-staff-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        .dark .cal-staff-label { color: #9ca3af; }

        .cal-staff-select {
            flex: 1;
            padding: 0.4rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background: white;
            color: #111827;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
            outline: none;
            transition: border-color .15s;
            max-width: 260px;
        }
        .cal-staff-select:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
        .dark .cal-staff-select { background: #374151; border-color: #4b5563; color: #f9fafb; }

        .cal-week-nav {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-left: auto;
        }
        .cal-nav-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            background: white;
            color: #374151;
            cursor: pointer;
            transition: background .15s, border-color .15s;
        }
        .cal-nav-btn:hover { background: #f3f4f6; border-color: #d1d5db; }
        .dark .cal-nav-btn { background: #374151; border-color: #4b5563; color: #d1d5db; }
        .dark .cal-nav-btn:hover { background: #4b5563; }

        .cal-month-label {
            min-width: 170px;
            text-align: center;
            font-size: 0.9375rem;
            font-weight: 700;
            color: #111827;
            padding: 0 0.5rem;
        }
        .dark .cal-month-label { color: #f9fafb; }

        .cal-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 4rem 1rem;
            color: #9ca3af;
        }

        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.35rem;
        }

        .cal-weekday-label {
            text-align: center;
            padding: 0.4rem 0.25rem;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #9ca3af;
        }
        .dark .cal-weekday-label { color: #6b7280; }

        .cal-day {
            min-height: 80px;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            background: white;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: border-color .15s;
        }
        .dark .cal-day { background: #1f2937; border-color: #374151; }
        .cal-day.cal-out-month {
            background: #fafafa;
            border-color: #f3f4f6;
            opacity: 0.45;
        }
        .dark .cal-day.cal-out-month { background: #111827; border-color: #1f2937; }
        .cal-day.cal-today { border-color: #6366f1; border-width: 2px; }

        .cal-day-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            padding: 0.3rem 0.4rem 0.25rem;
            background: #f9fafb;
            border-bottom: 1px solid #f3f4f6;
        }
        .dark .cal-day-header { background: #111827; border-color: #374151; }
        .cal-today .cal-day-header { background: #eef2ff; border-bottom-color: #c7d2fe; }
        .dark .cal-today .cal-day-header { background: #1e1b4b; border-bottom-color: #3730a3; }

        .cal-day-num {
            font-size: 0.8125rem;
            font-weight: 700;
            color: #374151;
            line-height: 1;
        }
        .dark .cal-day-num { color: #e5e7eb; }
        .cal-today .cal-day-num { color: #6366f1; }

        .cal-day-body {
            padding: 0.3rem 0.35rem;
            display: flex;
            flex-direction: column;
            gap: 0.175rem;
            flex: 1;
        }

        .cal-summary {
            display: flex;
            flex-direction: column;
            gap: 0.175rem;
        }
        .cal-badge {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.15rem 0.35rem;
            border-radius: 0.3rem;
            line-height: 1.2;
        }
        .cal-badge-available {
            background: #dcfce7;
            color: #15803d;
        }
        .dark .cal-badge-available { background: rgba(21,128,61,.2); color: #86efac; }
        .cal-badge-occupied {
            background: #fee2e2;
            color: #b91c1c;
        }
        .dark .cal-badge-occupied { background: rgba(185,28,28,.2); color: #fca5a5; }
        .cal-badge-dot {
            width: 0.4rem;
            height: 0.4rem;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .cal-badge-available .cal-badge-dot { background: #22c55e; }
        .cal-badge-occupied .cal-badge-dot { background: #f87171; }

        .cal-times {
            display: flex;
            flex-wrap: wrap;
            gap: 0.15rem;
            margin-top: 0.25rem;
        }
        .cal-time {
            font-size: 0.62rem;
            font-family: ui-monospace, monospace;
            font-weight: 500;
            color: #15803d;
            background: #f0fdf4;
            padding: 0.1rem 0.25rem;
            border-radius: 0.2rem;
            white-space: nowrap;
        }
        .dark .cal-time { background: rgba(21,128,61,.15); color: #86efac; }
        .cal-time-more {
            font-size: 0.62rem;
            color: #9ca3af;
            padding: 0.1rem 0.15rem;
            font-weight: 500;
            align-self: center;
        }

        .cal-no-slots {
            color: #e5e7eb;
            font-size: 0.7rem;
            line-height: 1;
        }
        .dark .cal-no-slots { color: #374151; }

        .cal-legend {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        .cal-legend-item { display: flex; align-items: center; gap: 0.35rem; }
        .cal-legend-dot { width: 0.55rem; height: 0.55rem; border-radius: 50%; }
    </style>

    <div class="space-y-4">

        {{-- Controls --}}
        <div class="cal-controls">
            <div class="cal-staff-group">
                <span class="cal-staff-label">Staff</span>
                <select wire:model.live="staffId" class="cal-staff-select">
                    <option value="">Seleziona...</option>
                    @foreach ($this->staffOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cal-week-nav">
                <button type="button" wire:click="previousMonth" class="cal-nav-btn" title="Mese precedente">
                    <x-heroicon-o-chevron-left class="w-4 h-4" />
                </button>
                <span class="cal-month-label">{{ $this->monthLabel }}</span>
                <button type="button" wire:click="nextMonth" class="cal-nav-btn" title="Mese successivo">
                    <x-heroicon-o-chevron-right class="w-4 h-4" />
                </button>
            </div>
        </div>

        @if (! $staffId)
            <div class="cal-empty-state">
                <x-heroicon-o-calendar-days class="w-10 h-10 opacity-30" />
                <p class="text-sm">Seleziona uno staff per vedere il calendario.</p>
            </div>
        @else
            @php $today = now()->format('Y-m-d'); @endphp
            <div class="cal-grid">
                {{-- Day name headers --}}
                @foreach (['Lun','Mar','Mer','Gio','Ven','Sab','Dom'] as $dayName)
                    <div class="cal-weekday-label">{{ $dayName }}</div>
                @endforeach

                {{-- Calendar cells --}}
                @foreach ($this->calendarCells as $cell)
                    @php
                        $key         = $cell['date']->format('Y-m-d');
                        $inMonth     = $cell['inMonth'];
                        $isToday     = $key === $today;
                        $daySlots    = $this->slots->get($key, collect());
                        $availSlots  = $daySlots->filter(fn ($s) => $s->is_available && is_null($s->appointment_id));
                        $availCount  = $availSlots->count();
                        $occupied    = $daySlots->count() - $availCount;
                        $showSlots   = $availSlots->take(5);
                        $moreCount   = $availCount - $showSlots->count();
                    @endphp
                    <div class="cal-day {{ $inMonth ? '' : 'cal-out-month' }} {{ $isToday ? 'cal-today' : '' }}">
                        <div class="cal-day-header">
                            <span class="cal-day-num">{{ $cell['date']->format('j') }}</span>
                        </div>
                        <div class="cal-day-body">
                            @if ($daySlots->isEmpty())
                                <span class="cal-no-slots">—</span>
                            @else
                                <div class="cal-summary">
                                    @if ($availCount > 0)
                                        <div class="cal-badge cal-badge-available">
                                            <span class="cal-badge-dot"></span>
                                            {{ $availCount }} disp.
                                        </div>
                                    @endif
                                    @if ($occupied > 0)
                                        <div class="cal-badge cal-badge-occupied">
                                            <span class="cal-badge-dot"></span>
                                            {{ $occupied }} occ.
                                        </div>
                                    @endif
                                </div>
                                @if ($availCount > 0)
                                    <div class="cal-times">
                                        @foreach ($showSlots as $slot)
                                            <span class="cal-time">{{ substr($slot->start_time, 0, 5) }}</span>
                                        @endforeach
                                        @if ($moreCount > 0)
                                            <span class="cal-time-more">+{{ $moreCount }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="cal-legend">
                <div class="cal-legend-item">
                    <span class="cal-legend-dot" style="background:#22c55e"></span>
                    Disponibili
                </div>
                <div class="cal-legend-item">
                    <span class="cal-legend-dot" style="background:#f87171"></span>
                    Occupati
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
