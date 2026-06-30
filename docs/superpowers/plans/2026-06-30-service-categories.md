# Service Categories Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere categorie di servizi per-business con CRUD Filament, associazione opzionale servizio→categoria, e filtro per categoria nella pagina pubblica di prenotazione.

**Architecture:** Nuova tabella `service_categories` con `BelongsToBusiness` (stesso pattern di `Service`); FK nullable su `services`; `ServiceCategoryResource` speculare a `ServiceResource`; tab di filtraggio in Step 1 del booking wizard (Alpine JS).

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Alpine.js, Pest, MySQL 8

## Global Constraints

- Tutti i comandi vanno eseguiti dentro Docker: `docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest`
- Modelli usano `#[Fillable([...])]` attribute (non `$fillable`) e `protected function casts(): array` (non `$casts`)
- Factory: docblock `@extends Factory<\App\Models\Foo>` obbligatorio
- Modello con factory: docblock `@use HasFactory<\Database\Factories\FooFactory>` sulla riga `use HasFactory`
- `RefreshDatabase` è globale in `tests/Pest.php` per tutti i Feature test
- Per i test tenant: usare il trait `Tests\Concerns\WithBusinessContext` e chiamare `$this->setBusinessContext($business)`
- Filament 4: `Filament\Schemas\Components\Section`, `Filament\Schemas\Schema`; le Actions sono in `Filament\Actions\*`

---

## File map

| File | Azione |
|------|--------|
| `database/migrations/2026_06_30_200001_create_service_categories_table.php` | Crea |
| `database/migrations/2026_06_30_200002_add_service_category_id_to_services.php` | Crea |
| `app/Models/ServiceCategory.php` | Crea |
| `database/factories/ServiceCategoryFactory.php` | Crea |
| `tests/Feature/Models/ServiceCategoryTest.php` | Crea |
| `tests/Feature/MultiTenancy/ModelScopingTest.php` | Modifica — aggiunge scoping test per ServiceCategory |
| `app/Filament/Resources/ServiceCategoryResource.php` | Crea |
| `app/Filament/Resources/ServiceCategoryResource/Pages/ListServiceCategories.php` | Crea |
| `app/Filament/Resources/ServiceCategoryResource/Pages/EditServiceCategory.php` | Crea |
| `tests/Feature/Filament/ResourcesTest.php` | Modifica — aggiunge list page test |
| `app/Models/Service.php` | Modifica — fillable + relazione `category()` |
| `app/Filament/Resources/ServiceResource.php` | Modifica — aggiunge Select categoria |
| `tests/Feature/Filament/ServiceCategorySelectTest.php` | Crea |
| `app/Http/Controllers/Portal/BookingController.php` | Modifica — carica categorie |
| `resources/views/portal/booking/index.blade.php` | Modifica — tab categorie in Step 1 |
| `resources/js/booking-wizard.js` | Modifica — stato `selectedCategory` |
| `tests/Feature/Portal/BookingCategoriesTest.php` | Crea |

---

## Task 1: Migrazioni + Modello + Factory + Test modello

**Files:**
- Crea: `database/migrations/2026_06_30_200001_create_service_categories_table.php`
- Crea: `database/migrations/2026_06_30_200002_add_service_category_id_to_services.php`
- Crea: `app/Models/ServiceCategory.php`
- Crea: `database/factories/ServiceCategoryFactory.php`
- Crea: `tests/Feature/Models/ServiceCategoryTest.php`
- Modifica: `tests/Feature/MultiTenancy/ModelScopingTest.php`

**Interfaces:**
- Produce: `ServiceCategory` model con trait `BelongsToBusiness`, relazione `services()`, scope `active()`
- Produce: `ServiceCategoryFactory` usabile con `ServiceCategory::factory()->create([...])`
- Produce: `services.service_category_id` nullable FK con nullOnDelete

- [ ] **Step 1: Scrivi i test che devono fallire**

`tests/Feature/Models/ServiceCategoryTest.php`:

```php
<?php

use App\Models\Service;
use App\Models\ServiceCategory;

it('has active scope', function () {
    $active   = ServiceCategory::factory()->create(['is_active' => true]);
    $inactive = ServiceCategory::factory()->create(['is_active' => false]);
    $ids = [$active->id, $inactive->id];

    expect(ServiceCategory::whereIn('id', $ids)->active()->count())->toBe(1);
});

it('has many services', function () {
    $category = ServiceCategory::factory()->create();
    $service  = Service::factory()->create(['service_category_id' => $category->id]);

    expect($category->services)->toHaveCount(1);
    expect($category->services->first()->id)->toBe($service->id);
});

it('sets service_category_id to null when category is deleted', function () {
    $category = ServiceCategory::factory()->create();
    $service  = Service::factory()->create(['service_category_id' => $category->id]);

    $category->delete();

    expect($service->fresh()->service_category_id)->toBeNull();
});
```

- [ ] **Step 2: Verifica che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/ServiceCategoryTest.php
```

Atteso: FAIL — classe ServiceCategory non trovata.

- [ ] **Step 3: Crea la migrazione per `service_categories`**

`database/migrations/2026_06_30_200001_create_service_categories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
```

- [ ] **Step 4: Crea la migrazione per `service_category_id` su `services`**

`database/migrations/2026_06_30_200002_add_service_category_id_to_services.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('service_category_id')
                ->nullable()
                ->nullOnDelete()
                ->constrained('service_categories')
                ->after('business_id');

            $table->index(['business_id', 'service_category_id']);
            $table->index(['business_id', 'service_category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['service_category_id']);
            $table->dropColumn('service_category_id');
        });
    }
};
```

- [ ] **Step 5: Crea il modello `ServiceCategory`**

`app/Models/ServiceCategory.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'name', 'description', 'sort_order', 'is_active'])]
class ServiceCategory extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceCategoryFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 6: Crea la factory**

`database/factories/ServiceCategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'name'        => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'sort_order'  => 0,
            'is_active'   => true,
        ];
    }
}
```

- [ ] **Step 7: Esegui le migrazioni di test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app php artisan migrate
```

- [ ] **Step 8: Esegui i test del modello — devono passare**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/ServiceCategoryTest.php
```

Atteso: 3 test PASS.

- [ ] **Step 9: Aggiungi lo scoping test in `ModelScopingTest.php`**

Apri `tests/Feature/MultiTenancy/ModelScopingTest.php` e aggiungi alla fine del file, dopo il blocco esistente `// --- Service ---`:

```php
// --- ServiceCategory ---

it('scopes service categories to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    \App\Models\ServiceCategory::factory()->create(['business_id' => $b1->id]);
    \App\Models\ServiceCategory::factory()->create(['business_id' => $b2->id]);

    $this->setBusinessContext($b1);
    expect(\App\Models\ServiceCategory::count())->toBe(1);

    $this->setBusinessContext($b2);
    expect(\App\Models\ServiceCategory::count())->toBe(1);
});
```

- [ ] **Step 10: Esegui lo scoping test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/MultiTenancy/ModelScopingTest.php
```

Atteso: tutti i test esistenti + il nuovo PASS.

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_06_30_200001_create_service_categories_table.php \
        database/migrations/2026_06_30_200002_add_service_category_id_to_services.php \
        app/Models/ServiceCategory.php \
        database/factories/ServiceCategoryFactory.php \
        tests/Feature/Models/ServiceCategoryTest.php \
        tests/Feature/MultiTenancy/ModelScopingTest.php
git commit -m "feat(categories): add ServiceCategory model, migrations, factory and scoping tests"
```

---

## Task 2: ServiceCategoryResource Filament + test

**Files:**
- Crea: `app/Filament/Resources/ServiceCategoryResource.php`
- Crea: `app/Filament/Resources/ServiceCategoryResource/Pages/ListServiceCategories.php`
- Crea: `app/Filament/Resources/ServiceCategoryResource/Pages/EditServiceCategory.php`
- Modifica: `tests/Feature/Filament/ResourcesTest.php`

**Interfaces:**
- Consuma: `ServiceCategory` model (Task 1)
- Produce: resource accessibile ad admin su `/admin/service-categories`

- [ ] **Step 1: Aggiungi il test della list page in `ResourcesTest.php`**

Apri `tests/Feature/Filament/ResourcesTest.php` e aggiungi questi due import e il test alla fine:

```php
use App\Filament\Resources\ServiceCategoryResource;
```

E in fondo al file:

```php
it('service category list page renders', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get(ServiceCategoryResource::getUrl('index'))
        ->assertSuccessful();
});
```

- [ ] **Step 2: Verifica che il test fallisca**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php --filter "service category list page renders"
```

Atteso: FAIL — classe non trovata.

- [ ] **Step 3: Crea il file della resource**

`app/Filament/Resources/ServiceCategoryResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceCategoryResource\Pages;
use App\Models\Business;
use App\Models\ServiceCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ServiceCategoryResource extends Resource
{
    protected static ?string $model = ServiceCategory::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'categoria';
    protected static ?string $pluralModelLabel = 'categorie';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() || ! auth()->user()?->isStaff();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informazioni')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->unique(
                            table: ServiceCategory::class,
                            column: 'name',
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule) => $rule->where('business_id', Business::currentId()),
                        )
                        ->maxLength(255)
                        ->validationMessages([
                            'required' => 'Il nome della categoria è obbligatorio.',
                            'unique'   => 'Esiste già una categoria con questo nome.',
                            'max'      => 'Il nome non può superare 255 caratteri.',
                        ]),

                    Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Impostazioni')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Attiva')
                        ->default(true),
                ])
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

                TextColumn::make('services_count')
                    ->label('Servizi')
                    ->counts('services')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Attiva'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceCategories::route('/'),
            'edit'  => Pages\EditServiceCategory::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Crea le pagine della resource**

`app/Filament/Resources/ServiceCategoryResource/Pages/ListServiceCategories.php`:

```php
<?php

namespace App\Filament\Resources\ServiceCategoryResource\Pages;

use App\Filament\Resources\ServiceCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceCategories extends ListRecords
{
    protected static string $resource = ServiceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`app/Filament/Resources/ServiceCategoryResource/Pages/EditServiceCategory.php`:

```php
<?php

namespace App\Filament\Resources\ServiceCategoryResource\Pages;

use App\Filament\Resources\ServiceCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceCategory extends EditRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

- [ ] **Step 5: Esegui il test Filament**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php
```

Atteso: tutti i test PASS incluso il nuovo.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/ServiceCategoryResource.php \
        app/Filament/Resources/ServiceCategoryResource/Pages/ListServiceCategories.php \
        app/Filament/Resources/ServiceCategoryResource/Pages/EditServiceCategory.php \
        tests/Feature/Filament/ResourcesTest.php
git commit -m "feat(categories): add ServiceCategoryResource with list and edit pages"
```

---

## Task 3: Service model + ServiceResource Select + test cross-tenant

**Files:**
- Modifica: `app/Models/Service.php`
- Modifica: `app/Filament/Resources/ServiceResource.php`
- Crea: `tests/Feature/Filament/ServiceCategorySelectTest.php`

**Interfaces:**
- Consuma: `ServiceCategory` model (Task 1), `ServiceCategoryResource` (Task 2)
- Produce: `Service::category()` relazione; campo Select in ServiceResource nascosto se non ci sono categorie e validato lato backend

- [ ] **Step 1: Scrivi i test che devono fallire**

`tests/Feature/Filament/ServiceCategorySelectTest.php`:

```php
<?php

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->business = Business::withoutGlobalScopes()->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);
});

it('service can be assigned a category', function () {
    $category = ServiceCategory::factory()->create(['business_id' => $this->business->id]);
    $service  = Service::factory()->create(['business_id' => $this->business->id]);

    $service->update(['service_category_id' => $category->id]);

    expect($service->fresh()->category->id)->toBe($category->id);
});

it('service category_id is null when no category assigned', function () {
    $service = Service::factory()->create(['business_id' => $this->business->id]);

    expect($service->service_category_id)->toBeNull();
    expect($service->category)->toBeNull();
});

it('cannot assign a category from another business', function () {
    $otherBusiness = Business::factory()->create();
    $otherCategory = ServiceCategory::factory()->create(['business_id' => $otherBusiness->id]);

    $rule = \Illuminate\Validation\Rule::exists('service_categories', 'id')
        ->where('business_id', $this->business->id);

    $validator = validator(
        ['service_category_id' => $otherCategory->id],
        ['service_category_id' => ['nullable', $rule]]
    );

    expect($validator->fails())->toBeTrue();
});
```

- [ ] **Step 2: Verifica che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Filament/ServiceCategorySelectTest.php
```

Atteso: FAIL (relazione non definita / validazione non presente).

- [ ] **Step 3: Aggiorna il modello `Service`**

In `app/Models/Service.php`, cambia la riga `#[Fillable]` aggiungendo `service_category_id` e aggiungi la relazione `category()`:

La riga `#[Fillable]` deve diventare:
```php
#[Fillable(['business_id', 'name', 'description', 'duration_minutes', 'price', 'active', 'featured', 'sort_order', 'image_path', 'service_category_id'])]
```

E aggiungi il metodo (dopo `appointments()`):

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// in fondo ai metodi:
public function category(): BelongsTo
{
    return $this->belongsTo(ServiceCategory::class, 'service_category_id');
}
```

E l'import in testa al file:
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
```

- [ ] **Step 4: Aggiorna `ServiceResource` — aggiungi il Select**

In `app/Filament/Resources/ServiceResource.php`, aggiungi gli import:

```php
use App\Models\ServiceCategory;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rule;
```

Nella sezione `Informazioni` del form, aggiungi il Select **dopo** il campo `price` (prima della chiusura `]` della sezione):

```php
Select::make('service_category_id')
    ->label('Categoria')
    ->options(fn () => ServiceCategory::orderBy('sort_order')->pluck('name', 'id'))
    ->searchable()
    ->preload()
    ->nullable()
    ->placeholder('Nessuna categoria')
    ->rules([
        'nullable',
        Rule::exists('service_categories', 'id')
            ->where('business_id', \App\Models\Business::currentId()),
    ])
    ->hidden(fn () => ServiceCategory::count() === 0)
    ->columnSpanFull(),
```

- [ ] **Step 5: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Filament/ServiceCategorySelectTest.php
```

Atteso: 3 test PASS.

- [ ] **Step 6: Esegui la suite completa per regressioni**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Atteso: nessun test rotto rispetto al commit precedente.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Service.php \
        app/Filament/Resources/ServiceResource.php \
        tests/Feature/Filament/ServiceCategorySelectTest.php
git commit -m "feat(categories): add category relation to Service and Select field in ServiceResource"
```

---

## Task 4: BookingController + Blade view + Alpine JS

**Files:**
- Modifica: `app/Http/Controllers/Portal/BookingController.php`
- Modifica: `resources/views/portal/booking/index.blade.php`
- Modifica: `resources/js/booking-wizard.js`
- Crea: `tests/Feature/Portal/BookingCategoriesTest.php`

**Interfaces:**
- Consuma: `ServiceCategory` model con scope `active()` e relazione `services()` (Task 1)
- Produce: variabile `$categories` nella view; tab di filtraggio in Step 1; stato `selectedCategory` in Alpine

- [ ] **Step 1: Scrivi i test che devono fallire**

`tests/Feature/Portal/BookingCategoriesTest.php`:

```php
<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('booking page passes empty categories when none exist', function () {
    Service::factory()->create(['active' => true]);

    $response = $this->get('/prenota');

    $response->assertSuccessful();
    $response->assertViewHas('categories', fn ($cats) => $cats->isEmpty());
});

it('booking page passes only active categories with active services', function () {
    $activeCategory   = ServiceCategory::factory()->create(['is_active' => true]);
    $inactiveCategory = ServiceCategory::factory()->create(['is_active' => false]);

    Service::factory()->create(['active' => true, 'service_category_id' => $activeCategory->id]);
    Service::factory()->create(['active' => true, 'service_category_id' => $inactiveCategory->id]);

    $response = $this->get('/prenota');

    $response->assertSuccessful();
    $response->assertViewHas('categories', function ($cats) use ($activeCategory, $inactiveCategory) {
        return $cats->contains('id', $activeCategory->id)
            && ! $cats->contains('id', $inactiveCategory->id);
    });
});

it('booking page excludes categories with no active services', function () {
    $categoryWithActive   = ServiceCategory::factory()->create(['is_active' => true]);
    $categoryWithInactive = ServiceCategory::factory()->create(['is_active' => true]);

    Service::factory()->create(['active' => true,  'service_category_id' => $categoryWithActive->id]);
    Service::factory()->create(['active' => false, 'service_category_id' => $categoryWithInactive->id]);

    $response = $this->get('/prenota');

    $response->assertSuccessful();
    $response->assertViewHas('categories', function ($cats) use ($categoryWithActive, $categoryWithInactive) {
        return $cats->contains('id', $categoryWithActive->id)
            && ! $cats->contains('id', $categoryWithInactive->id);
    });
});
```

- [ ] **Step 2: Verifica che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Portal/BookingCategoriesTest.php
```

Atteso: FAIL — variabile `categories` non nella view.

- [ ] **Step 3: Aggiorna `BookingController::create()`**

In `app/Http/Controllers/Portal/BookingController.php`, aggiungi l'import:

```php
use App\Models\ServiceCategory;
```

Nel metodo `create()`, dopo il blocco `$services = ...`, prima di `$staff = ...`, aggiungi:

```php
$categories = ServiceCategory::active()
    ->whereHas('services', fn ($q) => $q->where('active', true))
    ->orderBy('sort_order')
    ->get();
```

Nell'array `return view('portal.booking.index', [...])`, aggiungi:

```php
'categories' => $categories,
```

- [ ] **Step 4: Esegui i test del controller**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Portal/BookingCategoriesTest.php
```

Atteso: 3 test PASS.

- [ ] **Step 5: Aggiorna il JSON dei servizi nella view Blade**

In `resources/views/portal/booking/index.blade.php`, nel blocco `@php` in cima al `@section('content')`, aggiungi `category_id` alla mappatura `$servicesJson`:

```php
$servicesJson = $services->map(fn ($s) => [
    'id'          => $s->id,
    'name'        => $s->name,
    'description' => $s->description ?? '',
    'duration'    => $s->duration_minutes,
    'price'       => (float) $s->price,
    'featured'    => (bool) $s->featured,
    'staff_ids'   => $s->staff->pluck('id')->values()->all(),
    'category_id' => $s->service_category_id,  // aggiunto
])->values()->all();
```

E aggiungi il JSON delle categorie subito dopo:

```php
$categoriesJson = $categories->map(fn ($c) => [
    'id'   => $c->id,
    'name' => $c->name,
])->values()->all();
```

- [ ] **Step 6: Aggiorna la chiamata Alpine nella view**

Nella sezione con `x-data="bookingWizard(..."`, aggiungi `categories` come quinto argomento:

```html
<div
    x-data="bookingWizard(
        {{ Illuminate\Support\Js::from($servicesJson) }},
        {{ Illuminate\Support\Js::from($staffJson) }},
        {{ Illuminate\Support\Js::from($bookingPreferences) }},
        {{ Illuminate\Support\Js::from($paymentMode) }},
        {{ Illuminate\Support\Js::from($categoriesJson) }}
    )"
    class="space-y-3"
>
```

- [ ] **Step 7: Aggiungi i tab categorie nella view — Step 1**

In `resources/views/portal/booking/index.blade.php`, dentro Step 1, subito dopo:
```html
<div x-show="isOpen(1)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4">
```

e prima di:
```html
<div class="grid gap-3 sm:grid-cols-2">
```

inserisci il blocco dei tab:

```html
@if($categories->isNotEmpty())
<div class="mb-3 flex flex-wrap gap-2">
    <button
        type="button"
        @click="selectedCategory = null; showAllServices = false"
        :class="selectedCategory === null
            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
        class="rounded-full px-3 py-1 text-xs font-semibold transition-colors"
    >Tutti</button>
    <template x-for="cat in categories" :key="cat.id">
        <button
            type="button"
            @click="selectedCategory = cat.id; showAllServices = false"
            :class="selectedCategory === cat.id
                ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
            class="rounded-full px-3 py-1 text-xs font-semibold transition-colors"
            x-text="cat.name"
        ></button>
    </template>
    <button
        x-show="hasUncategorized"
        type="button"
        @click="selectedCategory = 'altri'; showAllServices = false"
        :class="selectedCategory === 'altri'
            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
        class="rounded-full px-3 py-1 text-xs font-semibold transition-colors"
    >Altri</button>
</div>
@endif
```

- [ ] **Step 8: Aggiorna `booking-wizard.js`**

In `resources/js/booking-wizard.js`, modifica la firma della funzione per aggiungere il parametro `categories`:

```js
export function bookingWizard(allServices, allStaff, bookingPreferences = null, paymentMode = 'both', categories = []) {
```

Nell'oggetto restituito, aggiungi sotto `showAllServices: false`:

```js
categories,
selectedCategory: null,
```

Sostituisci il getter `visibleServices` esistente:

```js
get visibleServices() {
    if (this.showAllServices) return this.allServices;
    const featured = this.allServices.filter(s => s.featured);
    return featured.length > 0 ? featured : this.allServices;
},
```

con:

```js
get visibleServices() {
    if (this.selectedCategory !== null) {
        if (this.selectedCategory === 'altri') {
            return this.allServices.filter(s => s.category_id === null);
        }
        return this.allServices.filter(s => s.category_id === this.selectedCategory);
    }
    if (this.showAllServices) return this.allServices;
    const featured = this.allServices.filter(s => s.featured);
    return featured.length > 0 ? featured : this.allServices;
},
```

Sostituisci il getter `hasMoreServices`:

```js
get hasMoreServices() {
    if (this.selectedCategory !== null) return false;
    const featured = this.allServices.filter(s => s.featured);
    return featured.length > 0 && featured.length < this.allServices.length;
},
```

Aggiungi il getter `hasUncategorized` dopo `hasMoreServices`:

```js
get hasUncategorized() {
    return this.categories.length > 0 && this.allServices.some(s => s.category_id === null);
},
```

- [ ] **Step 9: Esegui la suite completa**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Atteso: nessuna regressione.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Portal/BookingController.php \
        resources/views/portal/booking/index.blade.php \
        resources/js/booking-wizard.js \
        tests/Feature/Portal/BookingCategoriesTest.php
git commit -m "feat(categories): add category filter tabs to booking wizard"
```
