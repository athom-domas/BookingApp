# Help Page — Pannello Stato e Nuove Guide

**Date:** 2026-06-17

## Obiettivo

Migliorare la pagina Aiuto del pannello admin con:
1. Un banner di stato che avvisa l'admin quando un'integrazione non è configurata
2. Due nuove guide: "Setup iniziale del salone" e "Gestione staff e servizi"
3. Layout a griglia 3 colonne per tutte le guide

## Layout

Griglia 3 colonne (sm:grid-cols-3), ordine guide:
1. Setup iniziale del salone (amber)
2. Gestione staff e servizi (violet)
3. Pagamenti con Stripe (indigo)
4. WhatsApp Meta Cloud API (green)
5. Sincronizzazione Google Calendar (blue)

## Pannello di stato

### Comportamento
- Visibile **solo se almeno un'integrazione non è configurata**
- Un avviso per ogni integrazione mancante
- Ogni avviso ha un link diretto a `/admin/impostazioni-integrazioni` (o equivalente)
- Se tutte le integrazioni sono configurate, il banner non appare

### Logica di configurazione
- **Stripe configurato** → `stripe_public_key` non vuoto AND `stripe_secret_key` non vuoto
- **WhatsApp configurato** → `IntegrationSetting::current()->isWhatsappConfigured()` (metodo già esistente)
- **Google Calendar configurato** → `google_calendar_id` non vuoto AND `google_credentials_json` non vuoto

### Implementazione
La logica risiede nella classe PHP della pagina Filament: `app/Filament/Pages/Help.php`.

Aggiungere proprietà pubblica `$integrationStatuses` popolata in `mount()`, usando `IntegrationSetting::current()`. Array con chiavi `stripe`, `whatsapp`, `google_calendar`, ciascuna con `configured: bool` e `label: string`.

Il link "Configura ora" usa `IntegrationSettings::getUrl()` per generare l'URL corretto tenant-aware.

### Stile banner
Stile amber/warning (come le note ⚠️ nelle guide esistenti), un elemento per integrazione mancante.

## Nuove guide

### Stile
Stesso pattern delle guide esistenti:
- Card indice: icona + titolo + descrizione + "Leggi la guida →"
- Guida aperta: header colorato con icona, steps numerati con cerchio colorato, callout finale

### Guide: Setup iniziale del salone (amber)

**Contenuto steps:**
1. Vai su Impostazioni → Profilo Salone → inserisci nome, indirizzo, telefono
2. Carica il logo (usato su storefront e email)
3. Imposta gli orari di apertura in Impostazioni → Orari
4. Scegli tema, font e colori in Impostazioni → Aspetto
5. ✓ Il portale clienti è pronto

### Guida: Gestione staff e servizi (violet)

**Contenuto steps:**
1. Aggiungi i collaboratori in Salone → Staff → Nuovo
2. Crea i servizi in Salone → Servizi → Nuovo (nome, durata, prezzo)
3. Assegna i servizi a ogni collaboratore dalla scheda dello staff
4. Imposta la disponibilità di ogni collaboratore in Salone → Disponibilità
5. ✓ I clienti possono prenotare

## File coinvolti

| File | Modifica |
|------|----------|
| `app/Filament/Pages/HelpPage.php` | Modificare — aggiungere metodo `getIntegrationStatuses(): array` e proprietà pubblica `$integrationStatuses` passata alla view |
| `resources/views/filament/pages/help.blade.php` | Aggiungere banner stato, due nuove card, due nuove guide, cambiare griglia da 2 a 3 colonne |

## Note

- Il blade `help.blade.php` è già un template statico con x-data Alpine. Il banner stato è l'unico elemento dinamico.
- `IntegrationSetting::current()` già esiste e gestisce il tenant. Una sola query per pagina.
- Nessuna migrazione DB.
