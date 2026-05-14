# Slot Generation Improvements — Design Spec

**Date:** 2026-05-14

## Problem

The current `GenerateWeeklySlots` job generates slots only for the next week. This means:

1. Customers cannot book appointments more than ~1 week in advance (no slots exist).
2. When a staff member changes `slot_duration_minutes`, already-generated future slots remain with the old duration.

## Goals

1. Make the generation horizon (how many weeks ahead) configurable by admins via the admin panel.
2. When a staff member's slot duration changes, give the admin an explicit choice to regenerate future slots.

## Out of Scope

- Per-staff generation horizons (global setting only).
- Retroactive slot regeneration for past dates.
- UI for viewing which weeks have been generated.

---

## Architecture

### 1. SystemSetting Model (singleton)

**Migration:** `create_system_settings_table`

```
system_settings
  id                     bigint unsigned PK
  slot_generation_weeks  int unsigned NOT NULL DEFAULT 4
  created_at / updated_at timestamps
```

Single row, enforced at the application layer via `SystemSetting::current()` which uses `firstOrCreate(['id' => 1], [...defaults])`.

**Model:** `app/Models/SystemSetting.php`
- `casts()`: `slot_generation_weeks => 'integer'`
- Static helper: `SystemSetting::current(): self`
- `#[Fillable(['slot_generation_weeks'])]`

**Seeder:** `SystemSettingSeeder` — called from `DatabaseSeeder`, inserts the default row if it doesn't exist.

---

### 2. Filament Settings Page

**File:** `app/Filament/Pages/SystemSettings.php`

- Custom Filament page (extends `Page`, not a Resource)
- Visible only to admins (`canAccess(): bool => auth()->user()->isAdmin()`)
- Navigation: label "Impostazioni", icon `heroicon-o-cog-6-tooth`, group bottom of sidebar
- Form fields:
  - `slot_generation_weeks`: `TextInput::make()->integer()->minValue(1)->maxValue(52)->required()->suffix('settimane')`
- On submit: `SystemSetting::current()->update([...])` + success notification

---

### 3. GenerateWeeklySlots Job — Updated

**Change:** iterate from `nextWeek` to `now + slot_generation_weeks weeks` (inclusive).

```php
$horizon = SystemSetting::current()->slot_generation_weeks;
$nextWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek();

for ($i = 0; $i < $horizon; $i++) {
    $weekStart = $nextWeek->copy()->addWeeks($i);
    // generate for each staff...
}
```

**Idempotence:** `firstOrCreate` in `SlotGeneratorService` ensures re-running the job for already-covered weeks is a no-op.

**Behaviour at deploy / horizon increase:** On the next Sunday run (or manual dispatch), all missing weeks up to the new horizon are generated.

**No backfill command needed** for normal operation — the Sunday job covers it. If an immediate backfill is needed after a horizon increase, the admin can dispatch the job manually via Artisan or a future UI trigger.

---

### 4. RegenerateStaffSlots Job (new)

**File:** `app/Jobs/RegenerateStaffSlots.php`

Constructor: `int $staffId, int $slotMinutes`

Steps:
1. Delete all `TimeSlot` where `user_id = $staffId AND date >= today AND appointment_id IS NULL`.
2. Retrieve `SystemSetting::current()->slot_generation_weeks`.
3. Call `SlotGeneratorService::generateWeeklySlots()` for each week from `startOfWeek(today)` to `now + horizon` (weeks in the past are skipped automatically since `startOfWeek(today)` is at most the current week).

Implements `ShouldQueue`. Logs completion count via `Log::info`.

---

### 5. EditStaff — Slot Duration Change Flow

**File:** `app/Filament/Resources/StaffResource/Pages/EditStaff.php`

In `afterSave()`:
1. Compare `$this->record->preferences->slot_duration_minutes` (fresh from DB after save) against the value captured in `beforeSave()` as `$this->oldSlotDuration`.
2. If changed, register a Filament action modal on the page (or use `$this->replaceMountedAction(...)`) that presents:

> "La durata degli slot è cambiata da **X min** a **Y min**. Vuoi rigenerare gli slot futuri senza prenotazioni attive per questo staff?"

Buttons:
- **Sì, rigenera** → `RegenerateStaffSlots::dispatch($staffId, $newDuration)` + success notification
- **No, mantieni** → closes modal, no further action

The new `slot_duration_minutes` is saved regardless of the choice. The choice only controls whether pre-generated slots are rebuilt.

---

## Data Flow Summary

```
Admin changes slot_generation_weeks
  └─> SystemSettings Filament page saves to system_settings row

Every Sunday 01:00
  └─> GenerateWeeklySlots job
        └─> reads SystemSetting::current()->slot_generation_weeks
        └─> for each week in [nextWeek .. nextWeek + N]
              └─> SlotGeneratorService::generateWeeklySlots() (idempotent)

Admin changes staff slot_duration_minutes
  └─> EditStaff::afterSave() detects change
        └─> Filament confirmation modal
              ├─ Confirmed → RegenerateStaffSlots::dispatch()
              │                └─> deletes future unbooked slots
              │                └─> regenerates with new duration
              └─ Cancelled → new duration applies on next Sunday job run
```

---

## Testing

- `SystemSettingTest`: `current()` creates default row if missing; update persists correctly.
- `GenerateWeeklySlotsTest`: with `slot_generation_weeks = 3`, generates slots for 3 weeks; idempotent on second run.
- `RegenerateStaffSlotsTest`: deletes only unbooked future slots; booked slots untouched; new slots use new duration.
- `EditStaffTest`: modal shown when duration changes; not shown when other fields change; dispatch called on confirm; not called on cancel.
