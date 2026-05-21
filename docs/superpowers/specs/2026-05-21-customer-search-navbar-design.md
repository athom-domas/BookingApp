# Customer Search Navbar — Design Spec

**Date:** 2026-05-21  
**Status:** Approved

## Overview

Un componente Livewire custom inserito nella topbar dell'admin Filament che permette di cercare clienti per nome/email e visualizzare tutti i loro appuntamenti con data e stato evidenziati. Keyboard shortcut Ctrl+K per attivare il focus sull'input.

## Architettura

### Componente Livewire: `CustomerSearch`

**File:** `app/Livewire/CustomerSearch.php`  
**View:** `resources/views/livewire/customer-search.blade.php`

**Proprietà:**
- `$query` (string) — legato all'input con `wire:model.live.debounce.300ms`

**Computed property `results`:**
- Attiva solo se `strlen($query) >= 2`
- Cerca `User` con ruolo `customer` per nome o email (LIKE `%query%`)
- Eager load `appointmentsAsCustomer` ordinate per `scheduled_date` desc
- Limite: 5 clienti per query
- Restituisce array vuoto se query < 2 caratteri

**Visibilità:** solo utenti con ruolo `admin` o `staff`.

### Integrazione topbar

**File:** `resources/views/vendor/filament-panels/livewire/topbar.blade.php`

Aggiunge `@livewire('customer-search')` nella sezione `fi-topbar-end`, prima del componente `pending-completion-notifications` esistente.

## UI

**Input:**
- Icona lente di ingrandimento a sinistra
- Placeholder: `"Cerca cliente... (Ctrl+K)"`
- Stile coerente con il topbar Filament (ring, rounded, shadow)

**Dropdown risultati:**
- Max-height ~400px, scrollabile verticalmente
- Posizionato sotto l'input, larghezza ~380px
- Spinner `wire:loading` durante il fetch

**Per ogni cliente trovato:**
- Header: nome in grassetto + email in grigio
- Lista appuntamenti sotto l'header:
  - Data formattata (es. `21 mag 2026, 10:00`)
  - Nome servizio
  - Badge stato colorato:
    - `pending` → giallo
    - `confirmed` → verde
    - `completed` → grigio
    - `cancelled` → rosso
  - Click → naviga a `/admin/appointments/{id}/edit`
- Se il cliente non ha appuntamenti: riga `"Nessun appuntamento"`

**Empty state:** messaggio `"Nessun cliente trovato"` se la query non produce risultati.

## Keyboard & interazioni

- **Ctrl+K** (qualsiasi pagina admin): focus sull'input + apertura dropdown, gestito via Alpine.js `@keydown.ctrl.k.window.prevent`
- **Escape**: chiude il dropdown
- **Click fuori**: chiude il dropdown (`@click.outside`)
- Ctrl+K non ha effetto se il dropdown è già aperto

## Pattern di riferimento

Il componente segue lo stesso pattern di `PendingCompletionNotifications` (Livewire + Alpine.js, inserito nel topbar customizzato).
