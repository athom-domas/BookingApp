# TimeSlot Calendar View — Design Spec

**Date:** 2026-05-14  
**Status:** Approved

## Problem

The current `ListTimeSlots` page shows a flat table of individual records. With 16+ slots per staff per day, the list becomes unmanageable. The primary use case is operational consultation: an admin wants to see at a glance what's available for a specific staff member during a given week.

## Solution

Replace the flat table with a weekly calendar view. The page becomes a custom Filament page with a Livewire widget that renders a 7-column grid (Mon–Sun), one slot badge per row per column.

## Page Structure

**URL:** `/admin/time-slots` (replaces `ListTimeSlots`)

**Controls row:**
```
[Select Staff ▼]   [<]  Lun 12 – Dom 18 mag 2026  [>]
```

**Calendar grid:** 7 columns (Mon–Sun). Each column shows the date header and a list of slot badges:
- Green badge (`bg-green-100 text-green-800`): available slot (`is_available = true` and `appointment_id = null`)
- Red badge (`bg-red-100 text-red-800`): occupied slot

Empty day column shows "—". No staff selected shows a centered prompt message.

## Behavior

- Staff select is required; changing it reloads the calendar via `wire:model.live`
- Week navigation: `<` and `>` buttons shift by 7 days; default is current week (starting Monday)
- Slots are read-only — no click actions
- Slot status derived from existing `available()` scope logic (`is_available = true AND appointment_id IS NULL`)

## Files

### New files

**`app/Filament/Pages/TimeSlotCalendar.php`**  
Custom Filament page. Registers `StaffWeekCalendarWidget`, sets navigation label and icon. Replaces `ListTimeSlots` in the sidebar.

**`app/Filament/Widgets/StaffWeekCalendarWidget.php`**  
Livewire widget with:
- `$staffId` (int|null)
- `$weekStart` (Carbon, initialized to current Monday)
- Query: `TimeSlot::where('user_id', $staffId)->whereBetween('date', [$start, $end])->orderBy('start_time')->get()->groupBy('date')`
- Methods: `previousWeek()`, `nextWeek()`, computed `weekDays()` array (7 Carbon dates)

**`resources/views/filament/widgets/staff-week-calendar.blade.php`**  
Blade template. CSS grid with 7 columns (Tailwind `grid-cols-7`). Each cell: date header + slot badges with time range.

### Removed / modified

- `TimeSlotResource` navigation entry hidden (already done via `->hidden()`) — can be fully removed or kept for potential future use
- `ListTimeSlots` page no longer the default landing for time slots

## Out of Scope

- Creating or editing slots from the calendar
- Multi-staff view
- Monthly view
