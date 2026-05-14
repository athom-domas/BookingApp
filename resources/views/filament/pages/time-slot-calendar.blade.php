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
        .dark .cal-controls {
            background: #1f2937;
            border-color: #374151;
        }

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
        .dark .cal-staff-select {
            background: #374151;
            border-color: #4b5563;
            color: #f9fafb;
        }

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

        .cal-week-label {
            min-width: 160px;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            padding: 0 0.5rem;
        }
        .dark .cal-week-label { color: #e5e7eb; }

        .cal-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 4rem 1rem;
            color: #9ca3af;
        }
        .cal-empty-icon { font-size: 2rem; opacity: .5; }

        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.5rem;
        }

        .cal-day {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            min-height: 200px;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            background: white;
            overflow: hidden;
        }
        .dark .cal-day { background: #1f2937; border-color: #374151; }
        .cal-day.cal-today { border-color: #6366f1; border-width: 2px; }

        .cal-day-header {
            padding: 0.5rem 0.5rem 0.375rem;
            border-bottom: 1px solid #f3f4f6;
            background: #f9fafb;
        }
        .dark .cal-day-header { background: #111827; border-color: #374151; }
        .cal-today .cal-day-header { background: #eef2ff; }
        .dark .cal-today .cal-day-header { background: #1e1b4b; }

        .cal-day-name {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #9ca3af;
        }
        .cal-today .cal-day-name { color: #6366f1; }

        .cal-day-num {
            font-size: 1.125rem;
            font-weight: 700;
            line-height: 1.2;
            color: #111827;
        }
        .dark .cal-day-num { color: #f9fafb; }
        .cal-today .cal-day-num { color: #6366f1; }

        .cal-day-body {
            padding: 0.375rem 0.375rem;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            flex: 1;
        }

        .cal-slot {
            display: block;
            padding: 0.2rem 0.4rem;
            border-radius: 0.375rem;
            font-size: 0.7rem;
            font-family: ui-monospace, monospace;
            font-weight: 500;
            white-space: nowrap;
        }
        .cal-slot-available {
            background: #dcfce7;
            color: #15803d;
            border-left: 3px solid #22c55e;
        }
        .dark .cal-slot-available { background: rgba(21,128,61,.2); color: #86efac; border-left-color: #4ade80; }
        .cal-slot-occupied {
            background: #fee2e2;
            color: #b91c1c;
            border-left: 3px solid #f87171;
        }
        .dark .cal-slot-occupied { background: rgba(185,28,28,.2); color: #fca5a5; border-left-color: #f87171; }

        .cal-no-slots {
            color: #d1d5db;
            font-size: 0.75rem;
            padding: 0.25rem 0.25rem;
        }
        .dark .cal-no-slots { color: #4b5563; }

        .cal-legend {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        .cal-legend-item { display: flex; align-items: center; gap: 0.35rem; }
        .cal-legend-dot {
            width: 0.6rem;
            height: 0.6rem;
            border-radius: 50%;
        }
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
                <button type="button" wire:click="previousWeek" class="cal-nav-btn" title="Settimana precedente">
                    <x-heroicon-o-chevron-left class="w-4 h-4" />
                </button>
                <span class="cal-week-label">{{ $this->weekLabel }}</span>
                <button type="button" wire:click="nextWeek" class="cal-nav-btn" title="Settimana successiva">
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
            {{-- Calendar grid --}}
            @php $today = now()->format('Y-m-d'); @endphp
            <div class="cal-grid">
                @foreach ($this->weekDays as $day)
                    @php
                        $key      = $day->format('Y-m-d');
                        $daySlots = $this->slots->get($key, collect());
                        $isToday  = $key === $today;
                    @endphp
                    <div class="cal-day {{ $isToday ? 'cal-today' : '' }}">
                        <div class="cal-day-header">
                            <div class="cal-day-name">{{ $day->isoFormat('ddd') }}</div>
                            <div class="cal-day-num">{{ $day->format('d') }}</div>
                        </div>
                        <div class="cal-day-body">
                            @forelse ($daySlots as $slot)
                                @php $available = $slot->is_available && is_null($slot->appointment_id); @endphp
                                <span class="cal-slot {{ $available ? 'cal-slot-available' : 'cal-slot-occupied' }}">
                                    {{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}
                                </span>
                            @empty
                                <span class="cal-no-slots">—</span>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="cal-legend">
                <div class="cal-legend-item">
                    <span class="cal-legend-dot" style="background:#22c55e"></span>
                    Disponibile
                </div>
                <div class="cal-legend-item">
                    <span class="cal-legend-dot" style="background:#f87171"></span>
                    Occupato
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
