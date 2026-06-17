# Sidebar Toggle + Help Page Redesign

**Date:** 2026-06-17

## Scope

Two independent changes to the admin panel UI.

## 1. Sidebar collapsibile su desktop

**File:** `app/Providers/Filament/AdminPanelProvider.php`

Aggiungere `->sidebarCollapsibleOnDesktop()` nella chain del pannello. Filament gestisce internamente il toggle (pulsante nell'header della sidebar, stato in `$store.sidebar`, persistenza in localStorage). La sidebar si riduce a una rail di soli icone quando compressa.

## 2. Help page — larghezza completa

**File:** `resources/views/filament/pages/help.blade.php`

Rimuovere `max-w-3xl` dal `<div>` wrapper esterno (riga 2). Le guide occuperanno tutta la larghezza del contenitore Filament.

## 3. Help page — hover border colorato su tutte le card

**File:** `resources/views/filament/pages/help.blade.php`

Le classi `hover:border-green-400 dark:hover:border-green-500` (WhatsApp) e `hover:border-blue-400 dark:hover:border-blue-500` (Google Calendar) sono già presenti nel markup. Il problema è probabilmente la build CSS non aggiornata. Il fix è rigenerare gli asset con `npm run build` dentro il container Docker.

Se dopo il rebuild il problema persiste, aggiungere le classi mancanti al `@source` safelist in `resources/css/app.css` come fallback.

## Nessuna migrazione DB, nessun cambiamento al modello dati.
