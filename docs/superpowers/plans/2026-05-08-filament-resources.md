# Filament Resources – Phase 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create 5 Filament 4 resources (Appointment, Service, AvailabilityRule, TimeSlot, Payment) with customized forms, tables, filters, and actions for the booking management admin panel.

**Architecture:** Resources live in `app/Filament/Resources/`. Each resource has a main class file and a `Pages/` subdirectory with page classes. Filament auto-discovers resources via the `AdminPanelProvider` (`discoverResources` from `app/Filament/Resources`). In local environment all authenticated users can access the admin panel.

**Tech Stack:** Filament 4, Laravel 13, PHP 8.4, Docker (all commands via `docker-compose run --rm app`).

---

## Critical Filament 4 API notes

- `form()` signature: `public static function form(Schema $schema): Schema` — use `$schema->schema([...])`
- All actions (Edit, Delete, DeleteBulk, custom Action) are in namespace `Filament\Actions`
- Form components in `Filament\Forms\Components\*`
- Table columns in `Filament\Tables\Columns\*`, filters in `Filament\Tables\Filters\*`
- `Select` reactive form injection: `fn (\Filament\Schemas\Components\Utilities\Get $get) => ...`
- `TernaryFilter::->boolean()` handles boolean columns

## File map

**Create:**
```
app/Filament/Resources/
  AppointmentResource.php
  AppointmentResource/Pages/ListAppointments.php
  AppointmentResource/Pages/CreateAppointment.php
  AppointmentResource/Pages/EditAppointment.php
  ServiceResource.php
  ServiceResource/Pages/ListServices.php
  ServiceResource/Pages/CreateService.php
  ServiceResource/Pages/EditService.php
  AvailabilityRuleResource.php
  AvailabilityRuleResource/Pages/ListAvailabilityRules.php
  AvailabilityRuleResource/Pages/CreateAvailabilityRule.php
  AvailabilityRuleResource/Pages/EditAvailabilityRule.php
  TimeSlotResource.php
  TimeSlotResource/Pages/ListTimeSlots.php
  PaymentResource.php
  PaymentResource/Pages/ListPayments.php
tests/Feature/Filament/ResourcesTest.php
```

---

### Task 1: AppointmentResource

**Files:**
- Create: `app/Filament/Resources/AppointmentResource.php`
- Create: `app/Filament/Resources/AppointmentResource/Pages/ListAppointments.php`
- Create: `app/Filament/Resources/AppointmentResource/Pages/CreateAppointment.php`
- Create: `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php`
- Create: `tests/Feature/Filament/ResourcesTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Filament/ResourcesTest.php`:
```php
<?php

use App\Filament\Resources\AppointmentResource;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('appointment list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(AppointmentResource::getUrl('index'))
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php
```

Expected: FAIL — `App\Filament\Resources\AppointmentResource` not found.

- [ ] **Step 3: Create AppointmentResource.php**

Create `app/Filament/Resources/AppointmentResource.php`:
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $modelLabel = 'prenotazione';
    protected static ?string $pluralModelLabel = 'prenotazioni';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('user_id')
                ->label('Cliente')
                ->relationship('user', 'name')
                ->required()
                ->searchable(),

            Select::make('service_id')
                ->label('Servizio')
                ->relationship('service', 'name')
                ->required()
                ->searchable(),

            Select::make('staff_id')
                ->label('Staff')
                ->relationship('staff', 'name')
                ->required()
                ->searchable(),

            DateTimePicker::make('scheduled_date')
                ->label('Data e ora')
                ->required()
                ->minDate(now()),

            Select::make('status')
                ->label('Stato')
                ->options([
                    'pending'   => 'In attesa',
                    'confirmed' => 'Confermato',
                    'cancelled' => 'Annullato',
                    'completed' => 'Completato',
                ])
                ->required()
                ->default('pending'),

            Textarea::make('notes')
                ->label('Note')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('service.name')
                    ->label('Servizio')
                    ->sortable(),

                TextColumn::make('scheduled_date')
                    ->label('Data e ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'cancelled' => 'Annullato',
                        'completed' => 'Completato',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'gray',
                        default     => 'secondary',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'cancelled' => 'Annullato',
                        'completed' => 'Completato',
                    ]),

                SelectFilter::make('service')
                    ->label('Servizio')
                    ->relationship('service', 'name')
                    ->searchable(),

                SelectFilter::make('staff')
                    ->label('Staff')
                    ->relationship('staff', 'name')
                    ->searchable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit'   => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Create page classes**

Create `app/Filament/Resources/AppointmentResource/Pages/ListAppointments.php`:
```php
<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

Create `app/Filament/Resources/AppointmentResource/Pages/CreateAppointment.php`:
```php
<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;
}
```

Create `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php`:
```php
<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php
```

Expected: PASS — 1 test passed.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AppointmentResource.php \
    app/Filament/Resources/AppointmentResource/ \
    tests/Feature/Filament/ResourcesTest.php
git commit -m "feat: add AppointmentResource with form, table, and filters"
```

---

### Task 2: ServiceResource

**Files:**
- Create: `app/Filament/Resources/ServiceResource.php`
- Create: `app/Filament/Resources/ServiceResource/Pages/ListServices.php`
- Create: `app/Filament/Resources/ServiceResource/Pages/CreateService.php`
- Create: `app/Filament/Resources/ServiceResource/Pages/EditService.php`
- Modify: `tests/Feature/Filament/ResourcesTest.php`

- [ ] **Step 1: Add failing test for ServiceResource**

Add to `tests/Feature/Filament/ResourcesTest.php`:
```php
use App\Filament\Resources\ServiceResource;

it('service list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(ServiceResource::getUrl('index'))
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php --filter "service list"
```

Expected: FAIL — `App\Filament\Resources\ServiceResource` not found.

- [ ] **Step 3: Create ServiceResource.php**

Create `app/Filament/Resources/ServiceResource.php`:
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $modelLabel = 'servizio';
    protected static ?string $pluralModelLabel = 'servizi';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->unique(Service::class, 'name', ignoreRecord: true)
                ->maxLength(255),

            Textarea::make('description')
                ->label('Descrizione')
                ->rows(3)
                ->columnSpanFull(),

            TextInput::make('duration_minutes')
                ->label('Durata (minuti)')
                ->required()
                ->numeric()
                ->minValue(1)
                ->integer(),

            TextInput::make('price')
                ->label('Prezzo (€)')
                ->required()
                ->numeric()
                ->minValue(0.01)
                ->step(0.01),

            Toggle::make('active')
                ->label('Attivo')
                ->default(true),
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

                TextColumn::make('duration_minutes')
                    ->label('Durata (min)')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR')
                    ->sortable(),

                ToggleColumn::make('active')
                    ->label('Attivo'),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Attivo')
                    ->boolean()
                    ->trueLabel('Solo attivi')
                    ->falseLabel('Solo inattivi'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit'   => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Create page classes**

Create `app/Filament/Resources/ServiceResource/Pages/ListServices.php`:
```php
<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

Create `app/Filament/Resources/ServiceResource/Pages/CreateService.php`:
```php
<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;
}
```

Create `app/Filament/Resources/ServiceResource/Pages/EditService.php`:
```php
<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php --filter "service list"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/ServiceResource.php \
    app/Filament/Resources/ServiceResource/ \
    tests/Feature/Filament/ResourcesTest.php
git commit -m "feat: add ServiceResource with form, table, and filters"
```

---

### Task 3: AvailabilityRuleResource

**Files:**
- Create: `app/Filament/Resources/AvailabilityRuleResource.php`
- Create: `app/Filament/Resources/AvailabilityRuleResource/Pages/ListAvailabilityRules.php`
- Create: `app/Filament/Resources/AvailabilityRuleResource/Pages/CreateAvailabilityRule.php`
- Create: `app/Filament/Resources/AvailabilityRuleResource/Pages/EditAvailabilityRule.php`
- Modify: `tests/Feature/Filament/ResourcesTest.php`

- [ ] **Step 1: Add failing test**

Add to `tests/Feature/Filament/ResourcesTest.php`:
```php
use App\Filament\Resources\AvailabilityRuleResource;

it('availability rule list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(AvailabilityRuleResource::getUrl('index'))
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php --filter "availability rule"
```

Expected: FAIL.

- [ ] **Step 3: Create AvailabilityRuleResource.php**

Create `app/Filament/Resources/AvailabilityRuleResource.php`:
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AvailabilityRuleResource\Pages;
use App\Models\AvailabilityRule;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class AvailabilityRuleResource extends Resource
{
    protected static ?string $model = AvailabilityRule::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $modelLabel = 'regola disponibilità';
    protected static ?string $pluralModelLabel = 'regole disponibilità';

    private static array $dayLabels = [
        0 => 'Domenica',
        1 => 'Lunedì',
        2 => 'Martedì',
        3 => 'Mercoledì',
        4 => 'Giovedì',
        5 => 'Venerdì',
        6 => 'Sabato',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('user_id')
                ->label('Staff')
                ->relationship('user', 'name')
                ->required()
                ->searchable()
                ->live(),

            Select::make('day_of_week')
                ->label('Giorno')
                ->options(self::$dayLabels)
                ->required()
                ->unique(
                    table: AvailabilityRule::class,
                    column: 'day_of_week',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('user_id', $get('user_id')),
                ),

            TimePicker::make('start_time')
                ->label('Inizio')
                ->required(),

            TimePicker::make('end_time')
                ->label('Fine')
                ->required(),

            Toggle::make('is_available')
                ->label('Disponibile')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('day_of_week')
                    ->label('Giorno')
                    ->formatStateUsing(fn (int $state): string => self::$dayLabels[$state] ?? $state)
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Inizio'),

                TextColumn::make('end_time')
                    ->label('Fine'),

                ToggleColumn::make('is_available')
                    ->label('Disponibile'),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label('Staff')
                    ->relationship('user', 'name')
                    ->searchable(),

                SelectFilter::make('day_of_week')
                    ->label('Giorno')
                    ->options(self::$dayLabels),

                TernaryFilter::make('is_available')
                    ->label('Disponibile')
                    ->boolean()
                    ->trueLabel('Disponibile')
                    ->falseLabel('Non disponibile'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAvailabilityRules::route('/'),
            'create' => Pages\CreateAvailabilityRule::route('/create'),
            'edit'   => Pages\EditAvailabilityRule::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Create page classes**

Create `app/Filament/Resources/AvailabilityRuleResource/Pages/ListAvailabilityRules.php`:
```php
<?php

namespace App\Filament\Resources\AvailabilityRuleResource\Pages;

use App\Filament\Resources\AvailabilityRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvailabilityRules extends ListRecords
{
    protected static string $resource = AvailabilityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

Create `app/Filament/Resources/AvailabilityRuleResource/Pages/CreateAvailabilityRule.php`:
```php
<?php

namespace App\Filament\Resources\AvailabilityRuleResource\Pages;

use App\Filament\Resources\AvailabilityRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAvailabilityRule extends CreateRecord
{
    protected static string $resource = AvailabilityRuleResource::class;
}
```

Create `app/Filament/Resources/AvailabilityRuleResource/Pages/EditAvailabilityRule.php`:
```php
<?php

namespace App\Filament\Resources\AvailabilityRuleResource\Pages;

use App\Filament\Resources\AvailabilityRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAvailabilityRule extends EditRecord
{
    protected static string $resource = AvailabilityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php --filter "availability rule"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AvailabilityRuleResource.php \
    app/Filament/Resources/AvailabilityRuleResource/ \
    tests/Feature/Filament/ResourcesTest.php
git commit -m "feat: add AvailabilityRuleResource with Italian day labels and unique validation"
```

---

### Task 4: TimeSlotResource (read-only)

**Files:**
- Create: `app/Filament/Resources/TimeSlotResource.php`
- Create: `app/Filament/Resources/TimeSlotResource/Pages/ListTimeSlots.php`
- Modify: `tests/Feature/Filament/ResourcesTest.php`

TimeSlot is read-only (slots are generated by a service, not manually created). No Create/Edit pages, no form.

- [ ] **Step 1: Add failing test**

Add to `tests/Feature/Filament/ResourcesTest.php`:
```php
use App\Filament\Resources\TimeSlotResource;

it('time slot list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(TimeSlotResource::getUrl('index'))
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php --filter "time slot"
```

Expected: FAIL.

- [ ] **Step 3: Create TimeSlotResource.php**

Create `app/Filament/Resources/TimeSlotResource.php`:
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimeSlotResource\Pages;
use App\Models\TimeSlot;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TimeSlotResource extends Resource
{
    protected static ?string $model = TimeSlot::class;
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $modelLabel = 'slot';
    protected static ?string $pluralModelLabel = 'slot';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Inizio'),

                TextColumn::make('end_time')
                    ->label('Fine'),

                TextColumn::make('is_available')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Disponibile' : 'Occupato')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label('Staff')
                    ->relationship('user', 'name')
                    ->searchable(),

                Filter::make('date')
                    ->label('Data')
                    ->form([
                        DatePicker::make('date')->label('Data'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when($data['date'], fn (Builder $q) => $q->whereDate('date', $data['date']))
                    ),

                TernaryFilter::make('is_available')
                    ->label('Disponibile')
                    ->boolean()
                    ->trueLabel('Disponibili')
                    ->falseLabel('Occupati'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimeSlots::route('/'),
        ];
    }
}
```

- [ ] **Step 4: Create page class**

Create `app/Filament/Resources/TimeSlotResource/Pages/ListTimeSlots.php`:
```php
<?php

namespace App\Filament\Resources\TimeSlotResource\Pages;

use App\Filament\Resources\TimeSlotResource;
use Filament\Resources\Pages\ListRecords;

class ListTimeSlots extends ListRecords
{
    protected static string $resource = TimeSlotResource::class;
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php --filter "time slot"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/TimeSlotResource.php \
    app/Filament/Resources/TimeSlotResource/ \
    tests/Feature/Filament/ResourcesTest.php
git commit -m "feat: add TimeSlotResource as read-only table"
```

---

### Task 5: PaymentResource

**Files:**
- Create: `app/Filament/Resources/PaymentResource.php`
- Create: `app/Filament/Resources/PaymentResource/Pages/ListPayments.php`
- Modify: `tests/Feature/Filament/ResourcesTest.php`

PaymentResource has no Create/Edit pages (payments are created by the payment system). It has a custom refund action visible only when status is `completed`.

- [ ] **Step 1: Add failing test**

Add to `tests/Feature/Filament/ResourcesTest.php`:
```php
use App\Filament\Resources\PaymentResource;

it('payment list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(PaymentResource::getUrl('index'))
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php --filter "payment list"
```

Expected: FAIL.

- [ ] **Step 3: Create PaymentResource.php**

Create `app/Filament/Resources/PaymentResource.php`:
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $modelLabel = 'pagamento';
    protected static ?string $pluralModelLabel = 'pagamenti';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('appointment_id')
                    ->label('Prenotazione #')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'In attesa',
                        'completed' => 'Completato',
                        'refunded'  => 'Rimborsato',
                        'failed'    => 'Fallito',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'completed' => 'success',
                        'refunded'  => 'info',
                        'failed'    => 'danger',
                        default     => 'secondary',
                    }),

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'pending'   => 'In attesa',
                        'completed' => 'Completato',
                        'refunded'  => 'Rimborsato',
                        'failed'    => 'Fallito',
                    ]),

                Filter::make('created_at')
                    ->label('Periodo')
                    ->form([
                        DatePicker::make('from')->label('Dal'),
                        DatePicker::make('until')->label('Al'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['until'], fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']))
                    )
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dal: ' . $data['from'];
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Al: ' . $data['until'];
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Action::make('refund')
                    ->label('Rimborsa')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Conferma rimborso')
                    ->modalDescription('Sei sicuro di voler rimborsare questo pagamento?')
                    ->action(fn (Payment $record) => $record->update(['status' => 'refunded']))
                    ->visible(fn (Payment $record): bool => $record->status === 'completed'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
```

- [ ] **Step 4: Create page class**

Create `app/Filament/Resources/PaymentResource/Pages/ListPayments.php`:
```php
<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
```

- [ ] **Step 5: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: All tests pass (previous 38 + 5 new Filament tests = 43 total).

- [ ] **Step 6: Verify Filament cache-components succeeds**

```bash
docker-compose run --rm --no-deps app php artisan filament:cache-components
```

Expected: success (no PHP errors in any resource file).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/PaymentResource.php \
    app/Filament/Resources/PaymentResource/ \
    tests/Feature/Filament/ResourcesTest.php
git commit -m "feat: add PaymentResource with refund action and date range filter"
```
