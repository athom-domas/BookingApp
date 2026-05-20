# Admin come Staff — Design

**Data:** 2026-05-20

## Obiettivo

Permettere a un utente con ruolo `admin` di lavorare anche come membro del personale prenotabile dai clienti, senza modifiche allo schema del database e riusando l'infrastruttura esistente.

## Sezione 1 — Role model

Un admin-staff detiene entrambi i ruoli `admin` + `staff` sulla stessa riga in `model_has_roles` (Spatie Permission). Nessuna colonna aggiuntiva, nessuna migrazione.

Effetti:
- `StaffResource` filtra per `whereHas('roles', name='staff')` → include automaticamente gli admin-staff, senza modifiche.
- `SlotCalculationService` e il flusso di prenotazione usano lo stesso criterio → nessuna modifica.
- `canAccessPanel()` controlla `isAdmin() || isStaff()` → già corretto.

Quando il toggle è OFF: l'utente ha solo `admin`, non appare nel personale prenotabile. I record `service_staff` e `AvailabilityRule` rimangono in DB per consentire la riattivazione senza perdita di configurazione.

## Sezione 2 — AdminResource

Nuova risorsa Filament `AdminResource` (`app/Filament/Resources/AdminResource.php`), simmetrica a `StaffResource` e `CustomerResource`. Filtra utenti per `role = 'admin'`.

### Form di modifica (`EditAdmin`)

Campi base:
- `name`, `email`

Sezione staff (condizionale):
- Toggle `"Lavora anche come staff"` — al salvataggio:
  - ON → `$record->assignRole('staff')`
  - OFF → `$record->removeRole('staff')` + avviso se esistono appuntamenti futuri confermati come staff
- Se toggle ON: multiselect servizi erogati (via `service_staff` pivot, stessa logica di `StaffResource`)
- Se toggle ON: link diretto alle regole di disponibilità (`AvailabilityRuleResource` filtrata per `staff_id = $record->id`)

Politica:
- Nessuna azione `DeleteAction` (coerente con `CustomerResource`).
- Nessuna `CreateAction` — gli admin si creano tramite seeder o invito separato.

### Guard rail toggle OFF

Se `$record->appointmentsAsStaff()->where('status', 'confirmed')->where('scheduled_date', '>=', now())->exists()`, mostra una `Notification::warning()` prima di rimuovere il ruolo. Non blocca l'operazione.

## Sezione 3 — StaffResource

Nessuna modifica alla query o al form esistente.

Aggiunta visiva nella lista (`ListStaff`): colonna/badge `"Admin"` visibile quando `$record->isAdmin()`. Serve a evitare confusione all'operatore che vede l'utente in entrambe le risorse.

## Componenti coinvolti

| File | Azione |
|------|--------|
| `app/Filament/Resources/AdminResource.php` | Creare |
| `app/Filament/Resources/AdminResource/Pages/ListAdmins.php` | Creare |
| `app/Filament/Resources/AdminResource/Pages/EditAdmin.php` | Creare |
| `app/Filament/Resources/StaffResource/Pages/ListStaff.php` | Aggiungere badge Admin |

## Fuori scope

- Creazione di nuovi admin dal pannello.
- Modifica della password admin dal pannello.
- Separazione delle availability rules per contesto (admin vs staff) — l'admin-staff usa le stesse `AvailabilityRule` di qualsiasi staff.
