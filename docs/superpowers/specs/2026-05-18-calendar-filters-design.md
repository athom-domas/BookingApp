# Calendar Filters — Design Spec
**Date:** 2026-05-18

## Goal

Replace the single staff select on the calendar page with four multi-select filters (staff, status, service, customer) visible above the calendar for both admin and staff roles.

## Layout Fix

`AppointmentCalendar::getHeaderWidgets()` → `getFooterWidgets()`.  
This puts the calendar widget after page content, so filters (in the view slot) always render above it.

## Filter Fields

| Field | Source | Roles |
|---|---|---|
| Staff | `User::role('staff')->orderBy('name')->pluck('name', 'id')` | admin only |
| Status | `['pending' => 'In attesa', 'confirmed' => 'Confermato', 'completed' => 'Completato', 'cancelled' => 'Annullato']` | admin + staff |
| Service | `Service::orderBy('name')->pluck('name', 'id')` | admin + staff |
| Customer | `User::role('customer')->orderBy('name')->pluck('name', 'id')` | admin + staff |

All fields: `->multiple()`, `->placeholder('Tutti/e')`, `->live()`, not required. Rendered in a 2-column grid; the staff field spans full width (admin) or is absent (staff).

## Page State & Dispatch

**AppointmentCalendar.php** changes:
- Remove `public ?int $staffFilter`
- Add `public array $filterStaff = []`, `public array $filterStatus = []`, `public array $filterService = []`, `public array $filterCustomer = []`
- Rename `staffFilterForm()` → `filtersForm()`
- Remove `updatedStaffFilter()`, add `updatedFilterStaff()`, `updatedFilterStatus()`, `updatedFilterService()`, `updatedFilterCustomer()` — each calls `dispatchFilters()`
- `dispatchFilters()`: dispatches `calendar-filters-updated` with all four arrays to `AppointmentCalendarWidget::class`
- View: show `$this->filtersForm` for all roles (no `@if(isAdmin())` wrapper); the form itself conditionally includes the staff field

## Widget Changes

**AppointmentCalendarWidget.php** changes:
- Remove `public ?int $staffFilter`
- Add `public array $filterStaff = []`, `public array $filterStatus = []`, `public array $filterService = []`, `public array $filterCustomer = []`
- Replace `#[On('calendar-staff-filter-updated')]` listener with `#[On('calendar-filters-updated')]`
- `handleFiltersUpdated(array $staff, array $status, array $service, array $customer)`: sets all four properties and dispatches `filament-fullcalendar--refresh`

## Query Logic (fetchEvents)

```
if staff:        query->where('staff_id', $user->id)           // staff always
elif admin && filterStaff not empty: query->whereIn('staff_id', filterStaff)

if filterStatus not empty:   query->whereIn('status', filterStatus)
if filterCustomer not empty: query->whereIn('user_id', filterCustomer)
if filterService not empty:  query->whereRaw('JSON_OVERLAPS(service_ids, ?)', [json_encode(filterService)])
```

## Files Changed

- `app/Filament/Pages/AppointmentCalendar.php`
- `resources/views/filament/pages/appointment-calendar.blade.php`
- `app/Filament/Widgets/AppointmentCalendarWidget.php`

No new files.

## Tests to Update

- Existing `AppointmentCalendarTest.php` filter test uses `staffFilter` property on the widget — update to use new event signature `calendar-filters-updated` with named args
