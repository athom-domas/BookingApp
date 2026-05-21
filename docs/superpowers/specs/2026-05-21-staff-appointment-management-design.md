# Staff Appointment Management

## Context

The admin panel (Filament 4) currently gives staff read-only access to their own appointments. Admins have full CRUD. This design extends staff permissions to allow status changes and payment registration, without granting create or delete.

## Scope

Staff can:
- View their own appointments (already working)
- Open the edit page for their own non-completed/non-cancelled appointments
- Change the status (pending → confirmed → completed/cancelled)
- Register payment when marking an appointment as completed

Staff cannot:
- Create appointments (risk of overlapping bookings)
- Delete appointments
- Edit any field other than status and payment
- Reassign an appointment to another staff member

## Changes

### `AppointmentResource::canEdit()`

Current logic: returns `false` for any staff user.

New logic:
- If staff: allow only if `$record->staff_id === auth()->id()` AND status is not `completed` or `cancelled`
- If admin: existing logic unchanged

### `AppointmentResource` form — `staff_id` field

Current: disabled only when status is `completed` or `cancelled`.

New: also disabled when the authenticated user is staff. Prevents reassigning the appointment to a colleague.

### `EditAppointment::mount()`

Current: redirects staff users back to the appointment list.

New: redirect removed. Access is already controlled by `canEdit()`.

### `EditAppointment` form footer actions

Current: Save/Cancel actions are disabled for staff.

New: enabled for staff (they need to save status/payment changes).

### `register_payment` table action

No changes needed. The action's visibility condition does not restrict by role and the underlying `PaymentService::recordInPersonPayment()` is role-agnostic. Verify manually after implementation.

## What stays the same

- `canCreate()` returns `false` for staff — create button stays hidden
- `canDelete()` returns `false` for staff — delete action stays hidden
- `DeleteBulkAction` hidden for staff
- Staff sees only their own appointments in the table (query scope)
- All fields except status and payment are already disabled post-creation
- Completed/cancelled appointments remain immutable for everyone except admin

## Files to change

- `app/Filament/Resources/AppointmentResource.php`
- `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php`
