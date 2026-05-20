# Admin-as-Staff Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettere a un admin di lavorare anche come staff prenotabile assegnando entrambi i ruoli `admin` + `staff`, gestito tramite una nuova `AdminResource` con toggle staff.

**Architecture:** Nessuna modifica allo schema. `AdminResource` è simmetrica a `StaffResource`/`CustomerResource` e filtra per `role = 'admin'`. Il form include un toggle virtuale `works_as_staff` che in `handleRecordUpdate` chiama `assignRole('staff')` o `removeRole('staff')`. `StaffResource` guadagna un badge visivo "Admin" nella lista.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Spatie Permission, Pest

---

## File Map

| File | Azione |
|------|--------|
| `app/Filament/Resources/AdminResource.php` | Creare |
| `app/Filament/Resources/AdminResource/Pages/ListAdmins.php` | Creare |
| `app/Filament/Resources/AdminResource/Pages/EditAdmin.php` | Creare |
| `app/Filament/Resources/StaffResource.php` | Modificare (aggiungere badge Admin in tabella) |
| `tests/Feature/Filament/AdminResourceTest.php` | Creare |

---

### Task 1: AdminResource — lista admin

**Files:**
- Create: `app/Filament/Resources/AdminResource.php`
- Create: `app/Filament/Resources/AdminResource/Pages/ListAdmins.php`
- Test: `tests/Feature/Filament/AdminResourceTest.php`

- [ ] **Step 1: Scrivi i test fallenti**

Crea `tests/Feature/Filament/AdminResourceTest.php`:

```php
<?php

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\AdminResource\Pages\EditAdmin;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('admin list shows only admin users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $staff = User::factory()->create(['email' => 'staff@test.com']);
    $staff->assignRole('staff');

    $customer = User::factory()->create(['email' => 'customer@test.com']);
    $customer->assignRole('customer');

    $this->actingAs($admin);

    $this->get(AdminResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee($admin->email)
        ->assertDontSee('staff@test.com')
        ->assertDontSee('customer@test.com');
});

it('non-admin cannot access admin resource', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff);

    $this->get(AdminResource::getUrl('index'))
        ->assertForbidden();
});
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AdminResourceTest.php
```

Expected: FAIL — classe `AdminResource` non trovata.

- [ ] **Step 3: Crea `AdminResource.php`**

Crea `app/Filament/Resources/AdminResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminResource\Pages;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Amministratori';

    protected static string|\UnitEnum|null $navigationGroup = 'Utenti';

    protected static ?string $modelLabel = 'amministratore';

    protected static ?string $pluralModelLabel = 'amministratori';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn(Builder $query): Builder => $query
                ->where('name', 'admin')
                ->where('guard_name', 'web'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(User::class, 'email', ignoreRecord: true)
                ->maxLength(255),

            Toggle::make('works_as_staff')
                ->label('Lavora anche come staff')
                ->helperText('Quando attivo, questo admin appare come personale prenotabile dai clienti.')
                ->live(),

            Select::make('services')
                ->label('Servizi erogati')
                ->relationship(
                    name: 'services',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn(Builder $query): Builder => $query->where('active', true)->orderBy('name'),
                )
                ->multiple()
                ->preload()
                ->searchable()
                ->visible(fn(Get $get): bool => (bool) $get('works_as_staff'))
                ->helperText('Seleziona almeno un servizio per rendere lo staff prenotabile dal portale clienti.')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('staff_badge')
                    ->label('Ruolo staff')
                    ->getStateUsing(fn(User $record): string => $record->hasRole('staff') ? 'Staff' : '—')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Staff' ? 'success' : 'gray'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Modifica'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'edit'  => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Crea `ListAdmins.php`**

Crea `app/Filament/Resources/AdminResource/Pages/ListAdmins.php`:

```php
<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use Filament\Resources\Pages\ListRecords;

class ListAdmins extends ListRecords
{
    protected static string $resource = AdminResource::class;
}
```

- [ ] **Step 5: Esegui i test per verificare che passino**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AdminResourceTest.php --filter "admin list|non-admin"
```

Expected: PASS (2 test).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AdminResource.php \
        app/Filament/Resources/AdminResource/Pages/ListAdmins.php \
        tests/Feature/Filament/AdminResourceTest.php
git commit -m "feat: add AdminResource with list page"
```

---

### Task 2: EditAdmin — toggle staff, servizi, guard rail

**Files:**
- Create: `app/Filament/Resources/AdminResource/Pages/EditAdmin.php`
- Test: `tests/Feature/Filament/AdminResourceTest.php` (aggiungere casi)

- [ ] **Step 1: Aggiungi i test fallenti**

Appendi a `tests/Feature/Filament/AdminResourceTest.php`:

```php
it('toggle ON assigns staff role to admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $service = Service::factory()->create(['active' => true]);

    $this->actingAs($admin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->set('data.works_as_staff', true)
        ->set('data.services', [$service->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->hasRole('staff'))->toBeTrue();
    expect($admin->services()->whereKey($service->id)->exists())->toBeTrue();
});

it('toggle OFF removes staff role from admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->set('data.works_as_staff', false)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->hasRole('staff'))->toBeFalse();
});

it('toggle OFF with future confirmed appointments does not block the operation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->assignRole('staff');

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    Appointment::factory()->create([
        'staff_id'       => $admin->id,
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addDay(),
    ]);

    $this->actingAs($admin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->set('data.works_as_staff', false)
        ->call('save')
        ->assertHasNoFormErrors();

    // Operation is not blocked — role is removed
    expect($admin->refresh()->hasRole('staff'))->toBeFalse();
});

it('admin with staff role appears in staff resource query', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->assignRole('staff');

    $inQuery = User::whereHas('roles', fn(Builder $query) =>
        $query->where('name', 'staff')->where('guard_name', 'web')
    )->whereKey($admin->id)->exists();

    expect($inQuery)->toBeTrue();
});

it('edit form populates works_as_staff based on current role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->assertSet('data.works_as_staff', true);
});
```

Aggiungi l'import mancante nella sezione `use` in cima al file (già presenti `Appointment`, `Builder` — aggiungere `use Illuminate\Database\Eloquent\Builder;` se non c'è):

```php
use Illuminate\Database\Eloquent\Builder;
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AdminResourceTest.php --filter "toggle|staff role appears|form populates"
```

Expected: FAIL — classe `EditAdmin` non trovata.

- [ ] **Step 3: Crea `EditAdmin.php`**

Crea `app/Filament/Resources/AdminResource/Pages/EditAdmin.php`:

```php
<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\StaffResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageAvailability')
                ->label('Gestisci Disponibilità')
                ->color('primary')
                ->icon('heroicon-o-clock')
                ->visible(fn() => $this->getRecord()->hasRole('staff'))
                ->url(fn() => StaffResource::getUrl('manage-availability', ['record' => $this->getRecord()])),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['works_as_staff'] = $this->getRecord()->hasRole('staff');
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $worksAsStaff = $data['works_as_staff'] ?? false;
        unset($data['works_as_staff']);

        $record = parent::handleRecordUpdate($record, $data);

        if ($worksAsStaff) {
            Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
            $record->assignRole('staff');
        } elseif ($record->hasRole('staff')) {
            $hasUpcoming = $record->appointmentsAsStaff()
                ->where('status', 'confirmed')
                ->where('scheduled_date', '>=', now())
                ->exists();

            if ($hasUpcoming) {
                Notification::make()
                    ->title('Attenzione: appuntamenti futuri confermati')
                    ->body('Questo admin ha appuntamenti confermati futuri come staff. Verifica e gestisci gli appuntamenti prima di disattivare il ruolo staff.')
                    ->warning()
                    ->send();
            }

            $record->removeRole('staff');
        }

        return $record;
    }
}
```

- [ ] **Step 4: Esegui i test per verificare che passino**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AdminResourceTest.php
```

Expected: PASS (tutti i test).

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/AdminResource/Pages/EditAdmin.php \
        tests/Feature/Filament/AdminResourceTest.php
git commit -m "feat: add EditAdmin page with staff toggle and guard rail"
```

---

### Task 3: StaffResource — badge Admin nella lista

**Files:**
- Modify: `app/Filament/Resources/StaffResource.php`
- Test: `tests/Feature/Filament/StaffResourceTest.php` (aggiungere caso)

- [ ] **Step 1: Aggiungi il test fallente**

Appendi a `tests/Feature/Filament/StaffResourceTest.php`:

```php
it('admin-staff user shows Admin badge in staff list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $adminStaff = User::factory()->create(['email' => 'admin.staff@test.com']);
    $adminStaff->assignRole('admin');
    $adminStaff->assignRole('staff');

    $this->actingAs($admin);

    $this->get(\App\Filament\Resources\StaffResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('admin.staff@test.com')
        ->assertSee('Admin');
});
```

- [ ] **Step 2: Esegui il test per verificare che fallisca**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffResourceTest.php --filter "Admin badge"
```

Expected: FAIL — la colonna "Admin" non esiste ancora.

- [ ] **Step 3: Aggiungi la colonna badge in `StaffResource::table()`**

In `app/Filament/Resources/StaffResource.php`, nel metodo `table()`, aggiungi la colonna dopo `ColorColumn::make('calendar_color')`:

```php
TextColumn::make('admin_badge')
    ->label('Ruolo')
    ->getStateUsing(fn(User $record): ?string => $record->isAdmin() ? 'Admin' : null)
    ->badge()
    ->color('warning'),
```

Assicurati che `User` sia importato nella sezione `use` (è già presente).

- [ ] **Step 4: Esegui i test per verificare che passino**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffResourceTest.php
```

Expected: PASS (tutti i test del file).

- [ ] **Step 5: Esegui la suite completa per verificare no regressioni**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/StaffResource.php \
        tests/Feature/Filament/StaffResourceTest.php
git commit -m "feat: add Admin badge to StaffResource list for admin-staff users"
```
