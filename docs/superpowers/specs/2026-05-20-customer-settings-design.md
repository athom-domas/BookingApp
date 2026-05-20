# Customer Settings Page — Design Spec

**Date:** 2026-05-20

## Overview

A customer-facing settings page at `/portal/settings` that lets authenticated customers update their profile and notification preferences. Split into two independent sections, each saved separately.

## Routes

```
GET   /portal/settings
PATCH /portal/settings/profile        → updateProfile
PATCH /portal/settings/notifications  → updateNotifications
```

All routes use the `auth` middleware and are grouped under `portal` in `routes/web.php`.

## Controller

`App\Http\Controllers\Portal\SettingsController`

- `index()` — loads the authenticated user, calls `$user->preferences()->firstOrCreate([])` to ensure the record exists, renders `portal.settings.index`
- `updateProfile(Request $request)` — validates and updates `users` table fields
- `updateNotifications(Request $request)` — validates and updates `user_preferences` table fields, creating the record via `firstOrCreate` if absent

## Sections & Validation

### Profilo

Updates `User` model (`users` table).

| Field | Rules |
|---|---|
| `name` | required, string, max:255 |
| `email` | required, email, unique:users,email,{id} |
| `current_password` | required_with:new_password, current_password rule |
| `new_password` | nullable, min:8, confirmed |
| `new_password_confirmation` | — |

Password change is optional: if `new_password` is blank, password is not updated.

### Notifiche

Updates `UserPreference` model (`user_preferences` table).

| Field | Rules |
|---|---|
| `notification_channel` | required, in:email,sms,whatsapp |
| `phone_number` | required_if:notification_channel,sms \| required_if:notification_channel,whatsapp; string, max:20 |

`phone_number` format: international format enforced by a regex (`/^\+[1-9]\d{6,18}$/`).

## View

Single Blade view: `resources/views/portal/settings/index.blade.php`

- Extends `layouts.app`
- Two card sections, each with its own `<form method="POST">` with the appropriate `@method('PATCH')`
- Success flash message per section (uses Laravel session `status` key scoped by section, e.g. `profile_updated`, `notifications_updated`)
- `phone_number` input shown/hidden via Alpine.js `x-show` based on selected `notification_channel`

## Navigation

Add "Impostazioni" link to `resources/views/layouts/app.blade.php` nav, pointing to `/portal/settings`.

## Out of Scope

`timezone`, `preferred_staff`, and `slot_duration_minutes` remain admin-only fields managed via Filament `CustomerResource`. This page does not touch them.

## Files Touched

- `routes/web.php` — add 3 routes
- `app/Http/Controllers/Portal/SettingsController.php` — new controller
- `resources/views/portal/settings/index.blade.php` — new view
- `resources/views/layouts/app.blade.php` — add nav link
