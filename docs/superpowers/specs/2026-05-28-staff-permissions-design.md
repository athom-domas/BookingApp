# Staff Permissions Design

## Goal

Permettere all'admin di assegnare liberamente 5 permessi granulari a ogni membro del proprio staff, controllando cosa può vedere e fare nel pannello amministrativo.

## Architecture

Spatie Laravel Permission è già installato e le sue tabelle sono migrate. Si usano le **named permissions** di Spatie (record nella tabella `permissions`), assegnate per singolo utente tramite `givePermissionTo` / `syncPermissions`. Nessuna nuova tabella è necessaria.

I permessi sono globali (non tenant-scoped), il che è accettabile perché ogni utente staff appartiene a un solo business e non può autenticarsi su tenant diversi.

## Permissions

Cinque permessi seedati una volta in `DatabaseSeeder`:

| Nome | Label UI | Cosa sblocca |
|---|---|---|
| `appointments.view_all` | Vedi tutti gli appuntamenti | Lista e calendario mostrano tutti gli appuntamenti del salone, non solo i propri |
| `appointments.create` | Crea appuntamenti | Bottone "Nuovo appuntamento" abilitato in `AppointmentResource` |
| `customers.view` | Gestisci clienti | Accesso a `CustomerResource` (view + edit, no create/delete) |
| `payments.manage` | Registra pagamenti | Accesso a `PaymentResource` |
| `reports.view` | Vedi report | Accesso a `ReportPage` |

Guard name: `web` per tutti.

## UI — StaffResource

Nel form di modifica staff (`StaffResource`) aggiungere un `CheckboxList` con le 5 opzioni. Visibile **solo in modalità edit** (non in create). Al salvataggio chiama `$record->syncPermissions($state ?? [])`.

L'idratazione dello stato usa `$record->getPermissionNames()->toArray()`.

## Authorization Changes

### AppointmentResource
- `canCreate()`: `isAdmin() || (isStaff() && auth()->user()->can('appointments.create'))`
- `getEloquentQuery()`: se staff senza `appointments.view_all` → `->where('staff_id', auth()->id())`; altrimenti ritorna query senza filtro staff
- `canEdit()`: invariato — staff può modificare solo i propri appuntamenti (non completati/cancellati), indipendentemente da `view_all`
- `canDelete()`: invariato — solo admin

### AppointmentCalendar (Page)
- Query degli appuntamenti: stessa logica di `AppointmentResource::getEloquentQuery()` — filtra per `staff_id` se staff senza `appointments.view_all`

### CustomerResource
- `canViewAny()`: `isAdmin() || (isStaff() && auth()->user()->can('customers.view'))`
- `canEdit()`: `isAdmin() || (isStaff() && auth()->user()->can('customers.view'))`
- `canCreate()`: invariato — sempre false (clienti si registrano via portale)
- `canDelete()`: invariato — sempre false

### PaymentResource
- `canViewAny()`: `isAdmin() || (isStaff() && auth()->user()->can('payments.manage'))`
- Azioni di creazione/modifica: invariato (solo admin può creare pagamenti)

### ReportPage
- `canAccess()`: `isAdmin() || (isStaff() && auth()->user()->can('reports.view'))`

## Seeder

In `DatabaseSeeder::run()`, dopo la creazione dei ruoli:

```php
use Spatie\Permission\Models\Permission;

$staffPermissions = [
    'appointments.view_all',
    'appointments.create',
    'customers.view',
    'payments.manage',
    'reports.view',
];
foreach ($staffPermissions as $perm) {
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
}
```

## Files Modified

- `database/seeders/DatabaseSeeder.php` — seed 5 permissions
- `app/Filament/Resources/StaffResource.php` — CheckboxList nel form edit
- `app/Filament/Resources/AppointmentResource.php` — `canCreate()`, `getEloquentQuery()`
- `app/Filament/Pages/AppointmentCalendar.php` — query scope per `view_all`
- `app/Filament/Resources/CustomerResource.php` — `canViewAny()`, `canEdit()`
- `app/Filament/Resources/PaymentResource.php` — `canViewAny()`
- `app/Filament/Pages/ReportPage.php` — `canAccess()`

## Out of Scope

- Impostazioni salone, servizi, integrazioni, gestione staff/admin: sempre solo admin
- Permessi per utenti `admin`: non modificabili da UI (hanno accesso pieno per definizione)
- Tenant-scoping delle permissions: non necessario data l'architettura attuale
