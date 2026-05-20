# Salon Profile — White-label Booking Page

**Date:** 2026-05-20

## Overview

Allow the salon admin to configure branding elements (logo, name, primary color, contact info) that are reflected on the customer-facing booking page and in all email templates. Managed via a dedicated Filament admin page "Profilo Salone".

---

## 1. Data Model

New `salon_profiles` table — singleton row (`id = 1`), same pattern as `SystemSetting`.

| Column | Type | Default | Notes |
|---|---|---|---|
| `name` | `string` | `'Il mio salone'` | Shown in header, browser tab, emails |
| `logo_path` | `string\|null` | `null` | Relative path under `storage/app/public/` |
| `primary_color` | `string` | `#1d4ed8` | Hex color for buttons and accents |
| `phone` | `string\|null` | `null` | Shown in footer and email footer |
| `address` | `string\|null` | `null` | Shown in footer and email footer |
| `website` | `string\|null` | `null` | Shown in footer and email footer |

**Model:** `App\Models\SalonProfile`
- `#[Fillable([...])]` attribute
- `SalonProfile::current()` — finds or creates `id=1` with defaults
- Logo URL helper: `logoUrl()` returns `Storage::url($this->logo_path)` or `null`

---

## 2. Filament Admin Page — "Profilo Salone"

**File:** `app/Filament/Pages/SalonProfilePage.php`  
**Navigation:** icon `heroicon-o-building-storefront`, sort after SystemSettings  
**Access:** `canAccess()` restricted to admins

**Layout — sidebar + griglia:**

Colonna sinistra (griglia 2 colonne, occupa la maggior parte della larghezza):
- Nome del salone (`TextInput`, required) — colonna 1
- Colore primario (`ColorPicker`, required) — colonna 2
- Telefono (`TextInput`, nullable) — colonna 1
- Sito web (`TextInput`, `url`, nullable) — colonna 2
- Indirizzo (`TextInput`, nullable) — span 2 colonne

Colonna destra (sidebar fissa ~160px):
- Logo upload (`FileUpload`, `disk: 'public'`, `directory: 'salon-logo'`, `image`, `maxSize: 2048`, `imageEditor: false`)

Save action calls `SalonProfile::current()->update(...)` with a success notification.

---

## 3. Booking Page Integration

**File modified:** `resources/views/layouts/app.blade.php`

Changes:
1. Load profile once: `@php $salonProfile = \App\Models\SalonProfile::current(); @endphp`
2. Inject CSS custom property in `<head>`:
   ```html
   <style>
     :root { --color-primary: {{ $salonProfile->primary_color }}; }
     .btn-primary { background-color: var(--color-primary) !important; }
     .btn-primary:hover { filter: brightness(0.9); }
   </style>
   ```
3. Replace hardcoded logo `img/logo.png` → `$salonProfile->logoUrl() ?? asset('img/logo.png')`
4. Replace hardcoded `"Booking App"` → `$salonProfile->name`
5. Replace hardcoded `bg-blue-700`/`bg-blue-800` on the "Registrati" button with class `btn-primary`
6. Add footer with phone, address, website (each rendered only if non-null)

---

## 4. Email Integration

**New file:** `resources/views/emails/partials/header.blade.php`

```blade
@php $salonProfile = \App\Models\SalonProfile::current(); @endphp
<div style="background-color:{{ $salonProfile->primary_color }};padding:1.25rem 1.5rem;display:flex;align-items:center;gap:0.75rem;">
  @if($salonProfile->logo_path)
    <img src="{{ $salonProfile->logoUrl() }}" alt="" style="width:2.5rem;height:2.5rem;border-radius:0.375rem;object-fit:contain;">
  @endif
  <span style="color:#fff;font-weight:600;font-size:1rem;">{{ $salonProfile->name }}</span>
</div>
```

**Email footer partial:** `resources/views/emails/partials/footer.blade.php` — shows phone, address, website (each if non-null).

**Templates updated** (add `@include('emails.partials.header')` at top and footer at bottom):
- `emails/appointment-confirmation.blade.php`
- `emails/appointment-cancellation.blade.php`
- `emails/appointment-reminder.blade.php`
- `emails/admin-appointment-notification.blade.php`
- `emails/staff-appointment-notification.blade.php`

---

## 5. Files to Create / Modify

| Action | File |
|---|---|
| Create | `database/migrations/2026_05_20_000000_create_salon_profiles_table.php` |
| Create | `app/Models/SalonProfile.php` |
| Create | `app/Filament/Pages/SalonProfilePage.php` |
| Create | `resources/views/emails/partials/header.blade.php` |
| Create | `resources/views/emails/partials/footer.blade.php` |
| Modify | `resources/views/layouts/app.blade.php` |
| Modify | 5× email template blade files |

---

## 6. Out of Scope

- Multi-tenant / multiple salon support
- Custom fonts
- Social media links
- Dark mode variant of brand color
- Custom domain / subdomain per salon
