<x-filament-panels::page>
    <style>
        .fc-more-popover .fc-popover-body {
            max-height: 60vh;
            overflow-y: auto;
            margin-bottom: 10px;
        }
        .fi-section:has(.filament-fullcalendar) {
            overflow: hidden;
        }
        .filament-fullcalendar .fc-view-harness {
            margin: 0 -1.5rem -1.5rem;
        }
        .fc-daygrid-day-frame {
            padding: 2px 0 !important;
        }
        .fc-daygrid-day-top {
            padding: 1px 2px !important;
        }
        .fc-daygrid-event {
            padding: 3px 2px !important;
            margin: 0 0 2px 0 !important;
            font-size: 0.78rem !important;
            min-height: 22px !important;
        }
        .fc-daygrid-event-harness {
            margin: 0 !important;
        }
        @media (max-width: 767px) {
            .fc-daygrid-day-bottom {
                margin-top: -10px !important;
            }
        }
        .fc-daygrid-more-link {
            display: block !important;
            margin: 1px 0 0 !important;
            padding: 2px 4px !important;
            font-size: 0.7rem !important;
            font-weight: 600 !important;
            color: #6366f1 !important;
            background: #eef2ff !important;
            border-radius: 4px !important;
            text-align: center !important;
            line-height: 1.4 !important;
        }
        .dark .fc-daygrid-more-link {
            color: #a5b4fc !important;
            background: #1e1b4b !important;
        }
        .fc-daygrid-more-link:hover {
            background: #e0e7ff !important;
        }
        @media (max-width: 767px) {
            .fc .fc-header-toolbar {
                flex-wrap: wrap;
                gap: 0.4rem;
            }
        }
    </style>

    <div class="mb-4">
        {{ $this->filtersForm }}
    </div>
</x-filament-panels::page>

@script
<script>
    (function () {
        function switchCalendarView() {
            const view = window.innerWidth < 768 ? 'timeGridDay' : 'dayGridMonth';
            window.dispatchEvent(new CustomEvent('filament-fullcalendar--view', { detail: { view } }));
        }

        let __fcViewSet = false;
        let __fcResizeTimer;

        const observer = new MutationObserver(function (mutations) {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType !== 1) continue;

                    // Switch view once FullCalendar has rendered (x-load is async)
                    if (!__fcViewSet) {
                        const toolbar = node.classList?.contains('fc-header-toolbar')
                            ? node
                            : node.querySelector?.('.fc-header-toolbar');
                        if (toolbar) {
                            __fcViewSet = true;
                            switchCalendarView();
                        }
                    }

                    // Center the more-popover
                    const popover = node.classList?.contains('fc-more-popover')
                        ? node
                        : node.querySelector?.('.fc-more-popover');
                    if (popover) setupPopover(popover);

                    // Close popover when Filament modal opens
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
