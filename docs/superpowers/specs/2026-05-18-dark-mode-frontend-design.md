# Dark Mode — Frontend (Customer Area)

**Date:** 2026-05-18
**Scope:** All customer-facing frontend pages (portal, auth, booking wizard, public confirmation/cancellation, welcome)
**Stack:** Tailwind CSS v4, Alpine.js, Laravel Blade

---

## Summary

Add dark mode to all customer-facing pages. Default follows the OS preference (`prefers-color-scheme`); the user can override via a toggle button in the navbar, persisted in `localStorage`.

---

## Architecture

### CSS — `resources/css/app.css`

Add a `@custom-variant` directive so Tailwind v4 generates `dark:` utilities that respond to both the OS media query and the `.dark` class on `<html>`:

```css
@custom-variant dark (&:where(.dark, .dark *));
```

This makes class-based override take precedence over the media query in the CSS cascade.

### JavaScript — `layouts/app.blade.php` `<head>`

Inline script (before stylesheets) to apply `.dark` immediately and avoid FOUC:

```html
<script>
  if (localStorage.theme === 'dark' ||
      (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark')
  }
</script>
```

Logic:
- `localStorage.theme === 'dark'` → user explicitly chose dark → add `.dark`
- `localStorage.theme === 'light'` → user explicitly chose light → do nothing
- no entry in localStorage → fall back to OS preference

### Toggle Button — navbar in `layouts/app.blade.php`

Alpine.js button added to the navbar. On click: toggles `.dark` on `<html>`, writes `'dark'` or `'light'` to `localStorage.theme`. Displays a sun/moon SVG icon (inline, no extra dependency).

### Public Standalone Pages

`public/appointment-confirmed.blade.php`, `public/appointment-cancelled.blade.php`, `public/appointment-cancel.blade.php` use inline `<style>` only (no Tailwind). Dark mode is added via `@media (prefers-color-scheme: dark)` blocks in the existing `<style>` tag. Same anti-FOUC script is included to support class-based override, but no toggle button is shown (these are one-shot pages with no navbar).

---

## Color Mapping

| Light class | Dark class |
|---|---|
| `bg-white` | `dark:bg-gray-900` |
| `bg-gray-50` | `dark:bg-gray-950` |
| `bg-gray-100` | `dark:bg-gray-800` |
| `text-gray-950` | `dark:text-gray-50` |
| `text-gray-700`, `text-gray-900` | `dark:text-gray-300` |
| `text-gray-600` | `dark:text-gray-400` |
| `text-gray-500` | `dark:text-gray-500` |
| `border-gray-200` | `dark:border-gray-700` |
| `border-gray-300` | `dark:border-gray-600` |
| `divide-gray-200`, `divide-gray-100` | `dark:divide-gray-700` |
| `hover:bg-gray-50` | `dark:hover:bg-gray-800` |
| `hover:bg-gray-100` | `dark:hover:bg-gray-700` |
| `focus:ring-blue-100` | `dark:focus:ring-blue-900` |

Body base: `bg-gray-50 dark:bg-gray-950 text-gray-950 dark:text-gray-50`

---

## Files Modified

**Tailwind views (add `dark:` classes):**
- `resources/css/app.css` — add `@custom-variant`
- `resources/views/layouts/app.blade.php` — anti-FOUC script, body classes, toggle button
- `resources/views/portal/appointments/index.blade.php`
- `resources/views/portal/appointments/show.blade.php`
- `resources/views/portal/appointments/payment.blade.php`
- `resources/views/portal/appointments/partials/list.blade.php`
- `resources/views/portal/appointments/partials/status-badge.blade.php`
- `resources/views/portal/appointments/partials/payment-badge.blade.php`
- `resources/views/portal/booking/index.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/welcome.blade.php`

**Standalone (add `@media` dark blocks to inline `<style>`):**
- `resources/views/public/appointment-confirmed.blade.php`
- `resources/views/public/appointment-cancelled.blade.php`
- `resources/views/public/appointment-cancel.blade.php`

---

## Out of Scope

- Filament admin panel (managed separately by Filament's own dark mode)
- Email templates (rendered by mail clients, not the app)
- Any DB persistence of theme preference (localStorage is sufficient)
