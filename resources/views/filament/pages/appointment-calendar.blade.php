<x-filament-panels::page>
    @php
        $primaryHex = preg_replace('/[^#0-9a-fA-F]/', '', \App\Models\SalonProfile::current()->primary_color ?? '#1d1d1d');
    @endphp
    <style>
        :root { --color-primary: {{ $primaryHex }}; }

        /* ---------- Buttons ---------- */
        .fc .fc-button-primary {
            background-color: var(--color-primary) !important;
            border-color: var(--color-primary) !important;
            color: #fff !important;
            font-size: 0.78rem !important;
            font-weight: 500 !important;
            padding: 0.28rem 0.65rem !important;
            border-radius: 0.375rem !important;
            box-shadow: none !important;
            transition: filter 0.15s ease !important;
        }

        .fc .fc-button-primary:not(:disabled):hover {
            filter: brightness(0.82) !important;
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            filter: brightness(0.72) !important;
        }

        .fc .fc-button-primary:disabled {
            opacity: 0.45 !important;
        }

        /* ---------- Toolbar spacing ---------- */
        .fc .fc-toolbar-chunk {
            display: flex !important;
            align-items: center !important;
            gap: 0.35rem !important;
        }

        .fc .fc-button-group {
            display: inline-flex !important;
            gap: 2px !important;
        }

        .fc .fc-button-group .fc-button {
            border-radius: 0.375rem !important;
        }

        /* ---------- Today highlight ---------- */
        .fc .fc-day-today {
            background-color: rgba(0,0,0,0.03) !important;
        }

        .dark .fc .fc-day-today {
            background-color: rgba(255,255,255,0.04) !important;
        }

        .fc .fc-day-today .fc-daygrid-day-number {
            background-color: var(--color-primary) !important;
            color: #fff !important;
            border-radius: 50% !important;
            min-width: 22px !important;
            height: 22px !important;
            padding: 0 3px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 700 !important;
            font-size: 0.78rem !important;
        }

        .fc .fc-timegrid-col.fc-day-today {
            background-color: rgba(0,0,0,0.02) !important;
        }

        .dark .fc .fc-timegrid-col.fc-day-today {
            background-color: rgba(255,255,255,0.03) !important;
        }

        /* ---------- Column headers ---------- */
        .fc .fc-col-header-cell-cushion {
            font-size: 0.7rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.06em !important;
            padding: 5px 4px !important;
            color: #9ca3af !important;
        }

        .fc .fc-timegrid-slot-label-cushion {
            font-size: 0.68rem !important;
            color: #9ca3af !important;
        }

        /* ---------- Grid borders ---------- */
        .fc-theme-standard td,
        .fc-theme-standard th,
        .fc-theme-standard .fc-scrollgrid {
            border-color: rgba(0,0,0,0.07) !important;
        }

        .dark .fc-theme-standard td,
        .dark .fc-theme-standard th,
        .dark .fc-theme-standard .fc-scrollgrid {
            border-color: rgba(255,255,255,0.07) !important;
        }

        /* ---------- Day cell ---------- */
        .fc-daygrid-day-frame {
            padding: 2px 0 !important;
        }

        .fc-daygrid-day-top {
            padding: 2px 4px !important;
        }

        .fc .fc-daygrid-day-number {
            font-size: 0.78rem !important;
            color: #6b7280 !important;
            padding: 3px 5px !important;
        }

        .dark .fc .fc-daygrid-day-number {
            color: #9ca3af !important;
        }

        /* ---------- Events (month / day grid) ---------- */
        .fc-daygrid-event {
            padding: 2px 5px !important;
            margin: 0 2px 2px !important;
            font-size: 0.74rem !important;
            min-height: 20px !important;
            border-radius: 3px !important;
            border: none !important;
        }

        .fc-daygrid-event-harness {
            margin: 0 !important;
        }

        /* ---------- Events (time grid week/day) ---------- */
        .fc-timegrid-event {
            border-radius: 3px !important;
            border: none !important;
            font-size: 0.74rem !important;
        }

        .fc-timegrid-event .fc-event-title {
            font-weight: 500 !important;
        }

        /* ---------- Status classes ---------- */
        .fc-appt-cancelled {
            opacity: 0.4 !important;
            text-decoration: line-through !important;
        }

        .fc-appt-completed {
            opacity: 0.6 !important;
        }

        .fc-appt-pending {
            border-left: 3px solid rgba(245,158,11,0.8) !important;
        }

        /* ---------- "More" link ---------- */
        .fc-daygrid-more-link {
            display: block !important;
            margin: 1px 2px 0 !important;
            padding: 1px 4px !important;
            font-size: 0.68rem !important;
            font-weight: 600 !important;
            color: var(--color-primary) !important;
            background-color: rgba(0,0,0,0.06) !important;
            border-radius: 3px !important;
            text-align: center !important;
            line-height: 1.6 !important;
        }

        .dark .fc-daygrid-more-link {
            color: #fff !important;
            background-color: rgba(255,255,255,0.1) !important;
        }

        .fc-daygrid-more-link:hover {
            background-color: rgba(0,0,0,0.11) !important;
        }

        @media (max-width: 767px) {
            .fc-daygrid-day-bottom {
                margin-top: -10px !important;
            }
        }

        /* ---------- More popover ---------- */
        .fc-more-popover .fc-popover-header {
            font-size: 0.78rem !important;
            font-weight: 600 !important;
        }

        .fc-more-popover .fc-popover-body {
            max-height: 60vh;
            overflow-y: auto;
            padding: 6px !important;
        }

        /* ---------- List view ---------- */
        .fc-list-day-cushion {
            font-size: 0.7rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.07em !important;
            background-color: rgba(0,0,0,0.03) !important;
        }

        .dark .fc-list-day-cushion {
            background-color: rgba(255,255,255,0.04) !important;
        }

        .fc-list-day-text,
        .fc-list-day-side-text {
            color: #6b7280 !important;
        }

        .fc-list-event-time {
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            color: #9ca3af !important;
            white-space: nowrap !important;
        }

        .fc-list-event-title a {
            font-size: 0.84rem !important;
        }

        .fc-list-event:hover td {
            background-color: rgba(0,0,0,0.02) !important;
            cursor: pointer !important;
        }

        .dark .fc-list-event:hover td {
            background-color: rgba(255,255,255,0.03) !important;
        }

        .fc-list-event-dot {
            border-radius: 50% !important;
            border-width: 5px !important;
        }

        .fc-list-empty-cushion {
            font-size: 0.85rem !important;
            color: #9ca3af !important;
        }

        /* ---------- Filament section integration ---------- */
        .fi-section:has(.filament-fullcalendar) {
            overflow: hidden;
        }

        .filament-fullcalendar .fc-view-harness {
            margin: 0 -1.5rem -1.5rem;
        }

        /* ---------- Responsive toolbar ---------- */
        @media (max-width: 767px) {
            .fc .fc-header-toolbar {
                flex-wrap: wrap !important;
                gap: 0.4rem !important;
            }

            .fc .fc-toolbar-chunk {
                flex-shrink: 0 !important;
            }
        }
    </style>

    {{-- Filters --}}
    <div
        x-data="{ open: window.innerWidth >= 768 }"
        x-on:resize.window.debounce.300ms="open = window.innerWidth >= 768"
        class="mb-4"
    >
        <button
            type="button"
            x-on:click="open = !open"
            class="lg:hidden w-full flex items-center justify-between px-3 py-2.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
        >
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/>
                </svg>
                Filtri
            </span>
            <svg
                class="w-4 h-4 text-gray-400 transition-transform duration-200"
                :class="open ? 'rotate-180' : ''"
                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
        >
            {{ $this->filtersForm }}
        </div>
    </div>
</x-filament-panels::page>

@script
<script>
    (function () {
        function switchCalendarView() {
            const view = window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth';
            window.dispatchEvent(new CustomEvent('filament-fullcalendar--view', { detail: { view } }));
        }

        let __fcViewSet = false;
        let __fcResizeTimer;

        const observer = new MutationObserver(function (mutations) {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType !== 1) continue;

                    if (!__fcViewSet) {
                        const toolbar = node.classList?.contains('fc-header-toolbar')
                            ? node
                            : node.querySelector?.('.fc-header-toolbar');
                        if (toolbar) {
                            __fcViewSet = true;
                            switchCalendarView();
                        }
                    }

                    const popover = node.classList?.contains('fc-more-popover')
                        ? node
                        : node.querySelector?.('.fc-more-popover');
                    if (popover) setupPopover(popover);

                    if (node.classList?.contains('fi-modal')) {
                        document.querySelector('.fc-more-popover')?.remove();
                        document.getElementById('__fc-backdrop')?.remove();
                    }
                }

                for (const node of mutation.removedNodes) {
                    if (node.nodeType === 1 && node.classList?.contains('fc-more-popover')) {
                        document.getElementById('__fc-backdrop')?.remove();
                    }
                }
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });

        window.addEventListener('resize', function () {
            clearTimeout(__fcResizeTimer);
            __fcResizeTimer = setTimeout(switchCalendarView, 200);
        });

        function setupPopover(popover) {
            const fc = document.querySelector('.filament-fullcalendar');
            if (fc) {
                const r = fc.getBoundingClientRect();
                popover.style.setProperty('position', 'fixed', 'important');
                popover.style.setProperty('left', (r.left + r.width / 2) + 'px', 'important');
                popover.style.setProperty('top', (r.top + r.height / 2) + 'px', 'important');
                popover.style.setProperty('transform', 'translate(-50%, -55%)', 'important');
                popover.style.setProperty('width', '360px', 'important');
                popover.style.setProperty('max-width', '90vw', 'important');
                popover.style.setProperty('z-index', '41', 'important');
                popover.style.setProperty('border-radius', '0.5rem', 'important');
            }

            const backdrop = document.createElement('div');
            backdrop.id = '__fc-backdrop';
            backdrop.style.cssText = 'position:fixed;inset:0;z-index:40;background:rgba(0,0,0,0.25);';
            backdrop.addEventListener('click', function () {
                popover.remove();
                backdrop.remove();
            });
            document.body.appendChild(backdrop);
        }
    })();
</script>
@endscript
