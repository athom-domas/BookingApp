# Filament Admin Panel Customization

**Date:** 2026-05-12

## Goal

Brand the Filament 4 admin panel as "Booking App" and replace the default dashboard widgets with meaningful booking-domain stats.

## Approach

All branding is configured via `AdminPanelProvider.php` (Filament's native API). No custom CSS files or Blade components. Two custom widget classes are added under `app/Filament/Widgets/`.

## Branding

Changes to `app/Providers/Filament/AdminPanelProvider.php`:

- `->brandName('Booking App')`
- `->brandLogo(asset('img/logo.png'))` — sourced from `public/img/logo.png`
- `->brandLogoHeight('2rem')`
- `->favicon(asset('img/logo.png'))`
- `->colors(['primary' => Color::hex('#2563eb')])` — Tailwind blue-600

## Dashboard Widgets

Default widgets (`AccountWidget`, `FilamentInfoWidget`) are removed.

### `BookingStatsWidget`

Extends `Filament\Widgets\StatsOverviewWidget`. Three stats:

1. **Appuntamenti oggi** — `Appointment::whereDate('scheduled_date', today())->count()`
2. **Appuntamenti questo mese** — `Appointment::whereMonth('scheduled_date', now()->month)->whereYear('scheduled_date', now()->year)->count()`
3. **Ricavi del mese** — `Payment::completed()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount')` formatted as currency (€)

### `LatestAppointmentsWidget`

Extends `Filament\Widgets\TableWidget`. Shows the 5 most recently created appointments.

Columns:
- Cliente (via `user` relationship → `name`)
- Staff (via `staff` relationship → `name`)
- Servizio (via `service` relationship → `name`)
- Data/ora (`scheduled_date`, formatted)
- Stato (badge with color per enum value: `pending`→gray, `confirmed`→blue, `completed`→green, `cancelled`→red)

## Files Changed / Created

| File | Action |
|------|--------|
| `app/Providers/Filament/AdminPanelProvider.php` | Edit — branding + widget list |
| `app/Filament/Widgets/BookingStatsWidget.php` | Create |
| `app/Filament/Widgets/LatestAppointmentsWidget.php` | Create |

## Out of Scope

- Custom navigation ordering
- Role-based widget visibility
- Dark mode customization
- Custom login page beyond logo/colors
