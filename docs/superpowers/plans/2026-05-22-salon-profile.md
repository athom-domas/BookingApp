# Salon Profile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Estendere il profilo salone con campi ricchi (media, orari, social, recensioni) e costruire una vetrina pubblica in 10 sezioni che usa quei dati dinamicamente.

**Architecture:** `SalonProfile` (singleton) implementa `HasMedia` via spatie/laravel-medialibrary per logo, cover e galleria. Nuovo modello `SalonReview` per testimonianze manuali. `User` (staff) riceve `bio` + collezione `avatar`. La homepage è gestita dall'esistente `BookingController::index()` aggiornato. Il pannello Filament usa tab per organizzare l'editing.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Tailwind v4, Alpine.js, spatie/laravel-medialibrary v11, filament/spatie-laravel-media-library-plugin

---

## File Map

| File | Azione | Responsabilità |
|---|---|---|
| `app/Models/SalonProfile.php` | Modifica | HasMedia, nuovi campi fillable, media collections |
| `app/Models/User.php` | Modifica | HasMedia, `bio` fillable, collezione `avatar` |
| `app/Models/SalonReview.php` | Crea | Modello recensioni con scopes `published()` + `ordered()` |
| `database/factories/SalonReviewFactory.php` | Crea | Factory per test |
| `database/migrations/2026_05_22_110000_add_fields_to_salon_profiles_table.php` | Crea | Nuovi campi testuali e JSON |
| `database/migrations/2026_05_22_110001_add_bio_to_users_table.php` | Crea | Campo `bio` su users |
| `database/migrations/2026_05_22_110002_create_salon_reviews_table.php` | Crea | Tabella recensioni |
| `app/Filament/Pages/SalonProfilePage.php` | Modifica | Riscrittura form a 6 tab |
| `app/Filament/Resources/SalonReviewResource.php` | Crea | CRUD recensioni |
| `app/Filament/Resources/SalonReviewResource/Pages/ListSalonReviews.php` | Crea | Pagina lista |
| `app/Filament/Resources/SalonReviewResource/Pages/CreateSalonReview.php` | Crea | Pagina creazione |
| `app/Filament/Resources/SalonReviewResource/Pages/EditSalonReview.php` | Crea | Pagina modifica |
| `app/Filament/Resources/StaffResource.php` | Modifica | Aggiunge bio + avatar al form |
| `app/Providers/Filament/AdminPanelProvider.php` | Modifica | Registra SpatieLaravelMediaLibraryPlugin |
| `app/Http/Controllers/Portal/BookingController.php` | Modifica | `index()` passa profile/staff/reviews alla view |
| `resources/views/layouts/app.blade.php` | Modifica | Inietta `--color-primary` come CSS custom property |
| `resources/views/welcome.blade.php` | Modifica | 10 sezioni dinamiche |
| `tests/Feature/Models/SalonReviewTest.php` | Crea | Test scopes SalonReview |
| `tests/Feature/Http/WelcomeTest.php` | Crea | Test homepage controller |

---

## Task 1: Installa i pacchetti e configura il plugin Filament

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php:57-59`

- [ ] **Step 1: Installa i pacchetti PHP**

```bash
docker-compose run --rm --no-deps app composer require spatie/laravel-medialibrary:"^11" filament/spatie-laravel-media-library-plugin
```

- [ ] **Step 2: Pubblica e lancia la migration di medialibrary**

```bash
docker-compose run --rm app php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
docker-compose run --rm app php artisan migrate
```

- [ ] **Step 3: Registra il plugin nel panel provider**

In `app/Providers/Filament/AdminPanelProvider.php`, aggiorna il blocco `->plugins([...])`  (attualmente alla riga ~57):

```php
use Filament\SpatieLaravelMediaLibraryPlugin\SpatieLaravelMediaLibraryPlugin;

// ...

->plugins([
    FilamentFullCalendarPlugin::make(),
    SpatieLaravelMediaLibraryPlugin::make(),
])
```

> **Nota:** Verifica il namespace esatto del plugin guardando `vendor/filament/spatie-laravel-media-library-plugin/src/` dopo l'installazione. Cerca la classe che implementa `\Filament\Contracts\Plugin`.

- [ ] **Step 4: Verifica che i servizi partano senza errori**

```bash
docker-compose run --rm app php artisan config:clear
```

Atteso: nessun errore.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock app/Providers/Filament/AdminPanelProvider.php database/migrations/*add_media*
git commit -m "feat: install spatie medialibrary and filament plugin"
```

---

## Task 2: Migration — nuovi campi su `salon_profiles`

**Files:**
- Create: `database/migrations/2026_05_22_110000_add_fields_to_salon_profiles_table.php`

- [ ] **Step 1: Crea la migration**

```bash
docker-compose run --rm app php artisan make:migration add_fields_to_salon_profiles_table --table=salon_profiles
```

- [ ] **Step 2: Scrivi la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            $table->longText('description')->nullable()->after('tagline');
            $table->longText('cancellation_policy')->nullable()->after('description');
            $table->text('google_maps_embed')->nullable()->after('cancellation_policy');
            $table->json('opening_hours')->nullable()->after('google_maps_embed');
            $table->string('instagram_url')->nullable()->after('opening_hours');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('tiktok_url')->nullable()->after('facebook_url');
            $table->string('whatsapp_number')->nullable()->after('tiktok_url');
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'tagline', 'description', 'cancellation_policy',
                'google_maps_embed', 'opening_hours',
                'instagram_url', 'facebook_url', 'tiktok_url', 'whatsapp_number',
            ]);
        });
    }
};
```

- [ ] **Step 3: Lancia la migration**

```bash
docker-compose run --rm app php artisan migrate
```

Atteso: `Migrating: ...add_fields_to_salon_profiles_table` + `Migrated`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_22_110000_add_fields_to_salon_profiles_table.php
git commit -m "feat: add rich profile fields to salon_profiles"
```

---

## Task 3: Migrations — `bio` su users e tabella `salon_reviews`

**Files:**
- Create: `database/migrations/2026_05_22_110001_add_bio_to_users_table.php`
- Create: `database/migrations/2026_05_22_110002_create_salon_reviews_table.php`

- [ ] **Step 1: Crea le due migration**

```bash
docker-compose run --rm app php artisan make:migration add_bio_to_users_table --table=users
docker-compose run --rm app php artisan make:migration create_salon_reviews_table --create=salon_reviews
```

- [ ] **Step 2: Scrivi la migration bio**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bio');
        });
    }
};
```

- [ ] **Step 3: Scrivi la migration salon_reviews**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->text('body');
            $table->tinyInteger('rating')->default(5);
            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_reviews');
    }
};
```

- [ ] **Step 4: Lancia le migration**

```bash
docker-compose run --rm app php artisan migrate
```

Atteso: entrambe le migration eseguite senza errori.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_22_110001_add_bio_to_users_table.php \
        database/migrations/2026_05_22_110002_create_salon_reviews_table.php
git commit -m "feat: add bio to users and create salon_reviews table"
```

---

## Task 4: Modello `SalonReview` + factory + test

**Files:**
- Create: `app/Models/SalonReview.php`
- Create: `database/factories/SalonReviewFactory.php`
- Create: `tests/Feature/Models/SalonReviewTest.php`

- [ ] **Step 1: Scrivi i test (TDD)**

`tests/Feature/Models/SalonReviewTest.php`:

```php
<?php

use App\Models\SalonReview;

it('has published scope', function () {
    SalonReview::factory()->create(['is_published' => true]);
    SalonReview::factory()->create(['is_published' => false]);

    expect(SalonReview::published()->count())->toBe(1);
});

it('has ordered scope', function () {
    SalonReview::factory()->create(['sort_order' => 2]);
    SalonReview::factory()->create(['sort_order' => 1]);

    $reviews = SalonReview::ordered()->get();
    expect($reviews->first()->sort_order)->toBe(1);
});

it('combines published and ordered scopes', function () {
    SalonReview::factory()->create(['is_published' => true,  'sort_order' => 2]);
    SalonReview::factory()->create(['is_published' => true,  'sort_order' => 1]);
    SalonReview::factory()->create(['is_published' => false, 'sort_order' => 0]);

    $reviews = SalonReview::published()->ordered()->get();
    expect($reviews)->toHaveCount(2)
        ->and($reviews->first()->sort_order)->toBe(1);
});
```

- [ ] **Step 2: Lancia i test — devono fallire**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/SalonReviewTest.php
```

Atteso: FAIL — `App\Models\SalonReview not found`.

- [ ] **Step 3: Crea il modello**

`app/Models/SalonReview.php`:

```php
<?php

namespace App\Models;

use Database\Factories\SalonReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['author_name', 'body', 'rating', 'is_published', 'sort_order'])]
class SalonReview extends Model
{
    /** @use HasFactory<SalonReviewFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'rating'       => 'integer',
            'sort_order'   => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
```

- [ ] **Step 4: Crea la factory**

`database/factories/SalonReviewFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\SalonReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalonReview> */
class SalonReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'body'        => fake()->paragraph(),
            'rating'      => fake()->numberBetween(3, 5),
            'is_published' => false,
            'sort_order'  => fake()->numberBetween(0, 100),
        ];
    }
}
```

- [ ] **Step 5: Lancia i test — devono passare**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/SalonReviewTest.php
```

Atteso: 3 PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/SalonReview.php database/factories/SalonReviewFactory.php tests/Feature/Models/SalonReviewTest.php
git commit -m "feat: add SalonReview model with published/ordered scopes"
```

---

## Task 5: `SalonProfile` — HasMedia, nuovi fillable, media collections

**Files:**
- Modify: `app/Models/SalonProfile.php`

- [ ] **Step 1: Aggiorna il modello**

Riscrivi `app/Models/SalonProfile.php` completamente:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'name', 'tagline', 'logo_path', 'primary_color',
    'phone', 'address', 'website',
    'description', 'cancellation_policy', 'google_maps_embed',
    'opening_hours',
    'instagram_url', 'facebook_url', 'tiktok_url', 'whatsapp_number',
])]
class SalonProfile extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
        ];
    }

    public static function current(): self
    {
        $existing = self::find(1);

        if ($existing) {
            return $existing;
        }

        $profile = new self([
            'name'          => 'Il mio salone',
            'logo_path'     => null,
            'primary_color' => '#1d4ed8',
            'phone'         => null,
            'address'       => null,
            'website'       => null,
        ]);
        $profile->id = 1;
        $profile->save();

        return $profile;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->nonQueued();

        $this->addMediaConversion('web')
            ->width(1200)
            ->height(800)
            ->nonQueued();
    }

    public function logoUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('logo');
        if ($url) {
            return $url;
        }

        // fallback per logo_path legacy
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    public function coverUrl(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }
}
```

- [ ] **Step 2: Verifica che la suite di test sia ancora verde**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Atteso: tutti i test passano (nessun errore sul modello SalonProfile).

- [ ] **Step 3: Commit**

```bash
git add app/Models/SalonProfile.php
git commit -m "feat: SalonProfile implements HasMedia with logo/cover/gallery collections"
```

---

## Task 6: `User` — HasMedia e campo `bio`

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Aggiungi HasMedia e bio al modello User**

Nel file `app/Models/User.php`:

1. Aggiungi gli import:
```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
```

2. Cambia la dichiarazione della classe per implementare `HasMedia`:
```php
class User extends Authenticatable implements FilamentUser, HasMedia
```

3. Aggiungi `InteractsWithMedia` alla lista dei trait (accanto a HasRoles, ecc.):
```php
use HasApiTokens, HasFactory, HasRoles, Notifiable, InteractsWithMedia;
```

4. Aggiorna l'attributo `#[Fillable]` aggiungendo `'bio'`:
```php
#[Fillable(['name', 'email', 'password', 'internal_notes', 'calendar_color', 'bio'])]
```

5. Aggiungi i metodi per le media collections dopo i metodi esistenti:
```php
public function registerMediaCollections(): void
{
    $this->addMediaCollection('avatar')->singleFile();
}

public function registerMediaConversions(?Media $media = null): void
{
    $this->addMediaConversion('thumb')
        ->width(200)
        ->height(200)
        ->nonQueued();
}
```

- [ ] **Step 2: Verifica la suite di test**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Atteso: tutti i test passano.

- [ ] **Step 3: Commit**

```bash
git add app/Models/User.php
git commit -m "feat: User implements HasMedia with avatar collection and adds bio field"
```

---

## Task 7: `SalonProfilePage` — riscrittura con 6 tab

**Files:**
- Modify: `app/Filament/Pages/SalonProfilePage.php`

- [ ] **Step 1: Verifica il namespace del SpatieMediaLibraryFileUpload**

Dopo aver installato il plugin nel Task 1, verifica il namespace corretto eseguendo:

```bash
find /Users/domas/Progetti/gestionale-prenotazioni/vendor/filament/spatie-laravel-media-library-plugin/src -name "SpatieMediaLibraryFileUpload.php" | head -1
```

Il namespace sarà nel `<?php namespace ...` di quel file. Di norma è `Filament\Forms\Components\SpatieMediaLibraryFileUpload`.

- [ ] **Step 2: Riscrivi SalonProfilePage**

```php
<?php

namespace App\Filament\Pages;

use App\Models\SalonProfile;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class SalonProfilePage extends Page
{
    protected string $view = 'filament.pages.salon-profile';

    protected static ?string $navigationLabel = 'Profilo Salone';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 98;

    public ?array $data = [];

    public function mount(): void
    {
        $profile = SalonProfile::current();
        $hours   = $profile->opening_hours ?? [];

        $formData = [
            'name'                => $profile->name,
            'tagline'             => $profile->tagline,
            'primary_color'       => $profile->primary_color,
            'phone'               => $profile->phone,
            'address'             => $profile->address,
            'website'             => $profile->website,
            'description'         => $profile->description,
            'cancellation_policy' => $profile->cancellation_policy,
            'google_maps_embed'   => $profile->google_maps_embed,
            'instagram_url'       => $profile->instagram_url,
            'facebook_url'        => $profile->facebook_url,
            'tiktok_url'          => $profile->tiktok_url,
            'whatsapp_number'     => $profile->whatsapp_number,
        ];

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
            $formData["hours_{$day}_closed"] = $hours[$day]['closed'] ?? false;
            $formData["hours_{$day}_open"]   = $hours[$day]['open']   ?? '09:00';
            $formData["hours_{$day}_close"]  = $hours[$day]['close']  ?? '18:00';
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $profile = SalonProfile::current();

        $days = [
            'mon' => 'Lunedì',   'tue' => 'Martedì',  'wed' => 'Mercoledì',
            'thu' => 'Giovedì',  'fri' => 'Venerdì',  'sat' => 'Sabato',
            'sun' => 'Domenica',
        ];

        $hoursFields = [];
        foreach ($days as $key => $label) {
            $hoursFields[] = Grid::make(3)->schema([
                Toggle::make("hours_{$key}_closed")
                    ->label($label)
                    ->inline(false),
                TextInput::make("hours_{$key}_open")
                    ->label('Apertura')
                    ->placeholder('09:00'),
                TextInput::make("hours_{$key}_close")
                    ->label('Chiusura')
                    ->placeholder('18:00'),
            ]);
        }

        return $schema
            ->statePath('data')
            ->schema([
                Tabs::make()->tabs([

                    Tab::make('Identità')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nome del salone')
                                ->required(),
                            TextInput::make('tagline')
                                ->label('Tagline'),
                            ColorPicker::make('primary_color')
                                ->label('Colore primario')
                                ->required(),
                        ]),
                        Grid::make(2)->schema([
                            SpatieMediaLibraryFileUpload::make('logo')
                                ->label('Logo')
                                ->model($profile)
                                ->collection('logo')
                                ->image()
                                ->maxSize(2048),
                            SpatieMediaLibraryFileUpload::make('cover')
                                ->label('Immagine di copertina')
                                ->model($profile)
                                ->collection('cover')
                                ->image()
                                ->maxSize(5120),
                        ]),
                    ]),

                    Tab::make('Descrizione')->schema([
                        RichEditor::make('description')
                            ->label('Chi siamo')
                            ->columnSpanFull(),
                        RichEditor::make('cancellation_policy')
                            ->label('Politica di cancellazione')
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Galleria')->schema([
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label('Foto galleria')
                            ->model($profile)
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->maxSize(10240)
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Orari')->schema($hoursFields),

                    Tab::make('Contatti & Social')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('phone')->label('Telefono'),
                            TextInput::make('website')->label('Sito web')->url(),
                        ]),
                        TextInput::make('address')
                            ->label('Indirizzo')
                            ->columnSpanFull(),
                        Textarea::make('google_maps_embed')
                            ->label('Google Maps embed URL')
                            ->placeholder('https://www.google.com/maps/embed?...')
                            ->helperText('Incolla solo il valore src dell\'iframe di Google Maps')
                            ->rows(2)
                            ->columnSpanFull(),
                        Grid::make(2)->schema([
                            TextInput::make('instagram_url')->label('Instagram')->url(),
                            TextInput::make('facebook_url')->label('Facebook')->url(),
                            TextInput::make('tiktok_url')->label('TikTok')->url(),
                            TextInput::make('whatsapp_number')
                                ->label('WhatsApp')
                                ->placeholder('39xxxxxxxxxx')
                                ->helperText('Numero internazionale senza + (es. 39333000000)'),
                        ]),
                    ]),

                    Tab::make('Anteprima')->schema([
                        Placeholder::make('preview_link')
                            ->label('')
                            ->content(new HtmlString(
                                '<a href="/" target="_blank" class="text-primary-600 underline font-medium">Apri la vetrina pubblica →</a>'
                            )),
                    ]),
                ]),
            ]);
    }

    public function save(): void
    {
        $state   = $this->form->getState();
        $profile = SalonProfile::current();

        $days         = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $openingHours = [];
        foreach ($days as $day) {
            $openingHours[$day] = [
                'closed' => (bool) ($state["hours_{$day}_closed"] ?? false),
                'open'   => $state["hours_{$day}_open"]   ?? '09:00',
                'close'  => $state["hours_{$day}_close"]  ?? '18:00',
            ];
        }

        $hourKeys    = array_merge(...array_map(
            fn ($d) => ["hours_{$d}_closed", "hours_{$d}_open", "hours_{$d}_close"],
            $days
        ));
        $profileData = Arr::except($state, [...$hourKeys, 'logo', 'cover', 'gallery']);
        $profileData['opening_hours'] = $openingHours;

        $profile->update($profileData);
        $profile->refresh();

        $this->form->saveRelationships();
        $this->mount();

        Notification::make()->title('Profilo salvato')->success()->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
```

> **Nota:** Se `$this->form->saveRelationships()` non esiste in Filament 4, cerca il metodo equivalente per salvare le relazioni della Schema. In Filament 4 il metodo potrebbe chiamarsi `save()` oppure le SpatieMediaLibraryFileUpload potrebbero salvarsi automaticamente tramite gli upload endpoint di Livewire.

- [ ] **Step 3: Verifica manualmente che la pagina carichi senza errori**

Apri `http://localhost/admin` nel browser, vai su "Profilo Salone" e verifica che i 6 tab siano visibili e la pagina non dia errori PHP.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/SalonProfilePage.php
git commit -m "feat: rewrite SalonProfilePage with 6-tab form and medialibrary support"
```

---

## Task 8: `SalonReviewResource` — CRUD Filament

**Files:**
- Create: `app/Filament/Resources/SalonReviewResource.php`
- Create: `app/Filament/Resources/SalonReviewResource/Pages/ListSalonReviews.php`
- Create: `app/Filament/Resources/SalonReviewResource/Pages/CreateSalonReview.php`
- Create: `app/Filament/Resources/SalonReviewResource/Pages/EditSalonReview.php`

- [ ] **Step 1: Crea le directory**

```bash
docker-compose run --rm app mkdir -p app/Filament/Resources/SalonReviewResource/Pages
```

- [ ] **Step 2: Crea il Resource**

`app/Filament/Resources/SalonReviewResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalonReviewResource\Pages;
use App\Models\SalonReview;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalonReviewResource extends Resource
{
    protected static ?string $model = SalonReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Recensioni';

    protected static ?int $navigationSort = 99;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('author_name')
                ->label('Nome cliente')
                ->required(),
            Select::make('rating')
                ->label('Stelle')
                ->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★'])
                ->required(),
            Textarea::make('body')
                ->label('Testo')
                ->required()
                ->rows(4)
                ->columnSpanFull(),
            Toggle::make('is_published')
                ->label('Pubblicata'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author_name')->label('Cliente')->searchable(),
                TextColumn::make('rating')->label('Stelle')
                    ->formatStateUsing(fn ($state) => str_repeat('★', $state)),
                TextColumn::make('body')->label('Testo')->limit(60),
                IconColumn::make('is_published')->label('Pubblicata')->boolean(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSalonReviews::route('/'),
            'create' => Pages\CreateSalonReview::route('/create'),
            'edit'   => Pages\EditSalonReview::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
```

- [ ] **Step 3: Crea le Pages**

`app/Filament/Resources/SalonReviewResource/Pages/ListSalonReviews.php`:

```php
<?php

namespace App\Filament\Resources\SalonReviewResource\Pages;

use App\Filament\Resources\SalonReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalonReviews extends ListRecords
{
    protected static string $resource = SalonReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
```

`app/Filament/Resources/SalonReviewResource/Pages/CreateSalonReview.php`:

```php
<?php

namespace App\Filament\Resources\SalonReviewResource\Pages;

use App\Filament\Resources\SalonReviewResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalonReview extends CreateRecord
{
    protected static string $resource = SalonReviewResource::class;
}
```

`app/Filament/Resources/SalonReviewResource/Pages/EditSalonReview.php`:

```php
<?php

namespace App\Filament\Resources\SalonReviewResource\Pages;

use App\Filament\Resources\SalonReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalonReview extends EditRecord
{
    protected static string $resource = SalonReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
```

- [ ] **Step 4: Verifica in browser che il resource Recensioni compaia nel menu admin e permetta CRUD**

Apri `http://localhost/admin` → "Recensioni" → crea una recensione di test → pubblicala → verifica che appaia nella lista.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/SalonReviewResource.php \
        app/Filament/Resources/SalonReviewResource/
git commit -m "feat: add SalonReviewResource with reorderable table"
```

---

## Task 9: `StaffResource` — aggiungi bio e avatar

**Files:**
- Modify: `app/Filament/Resources/StaffResource.php`

- [ ] **Step 1: Leggi il form esistente di StaffResource**

```bash
grep -n "form\|schema\|TextInput\|FileUpload" app/Filament/Resources/StaffResource.php | head -30
```

- [ ] **Step 2: Aggiungi i campi al form di StaffResource**

Prima del `return $schema->schema([...])` aggiungi in cima al file (insieme agli altri `use`):

```php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
```

All'interno dello schema del form, subito dopo l'ultimo campo esistente (prima della parentesi di chiusura `])`):

```php
SpatieMediaLibraryFileUpload::make('avatar')
    ->label('Foto profilo')
    ->collection('avatar')
    ->image()
    ->maxSize(2048),
Textarea::make('bio')
    ->label('Bio')
    ->rows(3)
    ->columnSpanFull(),
```

> La classe `StaffResource` non ha `implements InteractsWithMedia` — quelli sono sul modello `User` già aggiornato nel Task 6. Il resource Filament usa il modello direttamente, nessuna modifica alla classe resource è necessaria per far funzionare medialibrary.

- [ ] **Step 3: Verifica in browser che il form Staff mostri i nuovi campi**

Apri `http://localhost/admin/staff/{id}/edit` → verifica che bio e foto profilo siano presenti.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/StaffResource.php
git commit -m "feat: add bio and avatar to StaffResource form"
```

---

## Task 10: `layouts/app.blade.php` — inietta CSS custom property

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Aggiungi il blocco CSS nel `<head>`**

Dopo il tag `<meta name="viewport">` e prima dei tag Vite, aggiungi:

```html
@php $salonProfile = \App\Models\SalonProfile::current(); @endphp
<style>
  :root { --color-primary: {{ $salonProfile->primary_color ?? '#1d4ed8' }}; }
</style>
```

- [ ] **Step 2: Verifica che la homepage carichi senza errori**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost/
```

Atteso: `200`.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: inject primary_color as CSS custom property in layout"
```

---

## Task 11: `BookingController::index()` — passa profile/staff/reviews

**Files:**
- Modify: `app/Http/Controllers/Portal/BookingController.php`
- Create: `tests/Feature/Http/WelcomeTest.php`

- [ ] **Step 1: Scrivi il test**

`tests/Feature/Http/WelcomeTest.php`:

```php
<?php

use App\Models\SalonProfile;
use App\Models\SalonReview;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('homepage loads with required view data', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertViewHas('profile');
    $response->assertViewHas('services');
    $response->assertViewHas('staff');
    $response->assertViewHas('reviews');
});

it('homepage passes only published reviews', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    SalonReview::factory()->create(['is_published' => true,  'sort_order' => 1]);
    SalonReview::factory()->create(['is_published' => false, 'sort_order' => 0]);

    $response = $this->get('/');

    $response->assertViewHas('reviews', fn ($reviews) => $reviews->count() === 1);
});

it('homepage passes only staff with bio or avatar', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    User::factory()->create()->assignRole('staff'); // nessuna bio
    $staffWithBio = User::factory()->create(['bio' => 'Stylist esperto'])->assignRole('staff');

    $response = $this->get('/');

    $response->assertViewHas('staff', fn ($staff) => $staff->contains($staffWithBio));
});
```

- [ ] **Step 2: Lancia i test — devono fallire**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Http/WelcomeTest.php
```

Atteso: FAIL — `profile` non è nella view / altri errori.

- [ ] **Step 3: Aggiorna `BookingController::index()`**

Aggiorna il metodo `index()` in `app/Http/Controllers/Portal/BookingController.php`:

```php
use App\Models\SalonProfile;
use App\Models\SalonReview;

// ...

public function index(): View
{
    $profile  = SalonProfile::current()->load('media');
    $services = Service::active()->orderBy('name')->get();
    $staff    = User::whereHas('roles', fn ($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
        ->with('media')
        ->where(fn ($q) => $q
            ->whereNotNull('bio')
            ->orWhereHas('media', fn ($m) => $m->where('collection_name', 'avatar'))
        )
        ->get();
    $reviews = SalonReview::published()->ordered()->get();

    return view('welcome', compact('profile', 'services', 'staff', 'reviews'));
}
```

- [ ] **Step 4: Lancia i test — devono passare**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Http/WelcomeTest.php
```

Atteso: 3 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Portal/BookingController.php tests/Feature/Http/WelcomeTest.php
git commit -m "feat: WelcomeController passes profile, staff, and reviews to homepage"
```

---

## Task 12: `welcome.blade.php` — sezioni 1–5 (Hero, Servizi, Team, Galleria, Chi siamo)

**Files:**
- Modify: `resources/views/welcome.blade.php`

- [ ] **Step 1: Riscrivi le prime 5 sezioni**

Sostituisci l'intero contenuto di `resources/views/welcome.blade.php` con:

```blade
@extends('layouts.app')

@section('title', $profile->name)

@section('content')

{{-- 1. HERO --}}
<section class="relative flex min-h-[480px] items-center justify-center overflow-hidden"
         style="background-color: var(--color-primary)">
    @if($profile->coverUrl())
        <img src="{{ $profile->coverUrl() }}"
             class="absolute inset-0 h-full w-full object-cover"
             alt="">
        <div class="absolute inset-0 bg-black/50"></div>
    @endif
    <div class="relative z-10 space-y-5 px-4 text-center">
        @if($profile->logoUrl())
            <img src="{{ $profile->logoUrl() }}"
                 class="mx-auto h-16 object-contain"
                 alt="{{ $profile->name }}">
        @endif
        <h1 class="text-4xl font-bold text-white sm:text-5xl">
            {{ $profile->name }}
        </h1>
        @if($profile->tagline)
            <p class="mx-auto max-w-xl text-lg text-white/80">
                {{ $profile->tagline }}
            </p>
        @endif
        <a href="{{ route('booking.create') }}"
           class="inline-block rounded-md border-2 border-white px-7 py-3 text-sm font-semibold text-white transition-colors hover:bg-white hover:text-gray-900">
            Prenota ora
        </a>
    </div>
</section>

{{-- 2. SERVIZI --}}
@if($services->isNotEmpty())
<section class="py-16 px-4">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">
            I nostri servizi
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($services as $service)
                <article class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-50">
                            {{ $service->name }}
                        </h3>
                        <span class="shrink-0 rounded-lg px-2.5 py-1 text-sm font-semibold text-white"
                              style="background-color: var(--color-primary)">
                            {{ number_format((float) $service->price, 2, ',', '.') }} €
                        </span>
                    </div>
                    @if($service->description)
                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                            {{ $service->description }}
                        </p>
                    @endif
                    <p class="mt-3 text-sm text-gray-500">Durata: {{ $service->duration_minutes }} min</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 3. TEAM --}}
@if($staff->isNotEmpty())
<section class="bg-gray-50 dark:bg-gray-900/50 py-16 px-4">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">
            Il nostro team
        </h2>
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($staff as $member)
                <div class="text-center space-y-3">
                    @php $avatarUrl = $member->getFirstMediaUrl('avatar', 'thumb'); @endphp
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}"
                             class="mx-auto h-24 w-24 rounded-full object-cover ring-2 ring-white dark:ring-gray-800"
                             alt="{{ $member->name }}">
                    @else
                        <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full text-2xl font-bold text-white"
                             style="background-color: var(--color-primary)">
                            {{ strtoupper(mb_substr($member->name, 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-gray-50">{{ $member->name }}</p>
                        @if($member->bio)
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                {{ $member->bio }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 4. GALLERIA --}}
@php $galleryItems = $profile->getMedia('gallery'); @endphp
@if($galleryItems->isNotEmpty())
<section class="py-16 px-4" x-data="{ lightbox: null }">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">
            Galleria
        </h2>
        <div class="columns-2 gap-3 sm:columns-3 lg:columns-4">
            @foreach($galleryItems as $item)
                @php $thumbUrl = $item->getUrl('thumb'); $webUrl = $item->getUrl('web'); @endphp
                <div class="mb-3 break-inside-avoid cursor-pointer overflow-hidden rounded-lg"
                     @click="lightbox = '{{ $webUrl }}'">
                    <img src="{{ $thumbUrl }}"
                         class="w-full object-cover transition-transform hover:scale-105"
                         alt="Galleria {{ $loop->iteration }}">
                </div>
            @endforeach
        </div>
    </div>
    {{-- Lightbox overlay --}}
    <div x-show="lightbox"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
         @click="lightbox = null"
         @keydown.escape.window="lightbox = null"
         style="display:none">
        <img :src="lightbox" class="max-h-[90vh] max-w-full rounded-lg object-contain shadow-2xl">
    </div>
</section>
@endif

{{-- 5. CHI SIAMO --}}
@if($profile->description)
<section class="bg-gray-50 dark:bg-gray-900/50 py-16 px-4">
    <div class="mx-auto max-w-2xl text-center">
        <h2 class="mb-6 text-2xl font-bold text-gray-900 dark:text-gray-50">Chi siamo</h2>
        <div class="prose prose-gray dark:prose-invert mx-auto text-left">
            {!! $profile->description !!}
        </div>
    </div>
</section>
@endif

@endsection
```

- [ ] **Step 2: Verifica in browser le 5 sezioni**

Apri `http://localhost/` e verifica:
- Hero visibile con sfondo colore primario (o cover se caricata)
- Sezione servizi presente
- Team visibile solo se ci sono staff con bio/foto
- Galleria presente solo se immagini caricate
- "Chi siamo" presente solo se description compilata

- [ ] **Step 3: Commit**

```bash
git add resources/views/welcome.blade.php
git commit -m "feat: homepage sections 1-5 (hero, servizi, team, galleria, chi siamo)"
```

---

## Task 13: `welcome.blade.php` — sezioni 6–10 (Orari, Contatti, Recensioni, Policy, Footer)

**Files:**
- Modify: `resources/views/welcome.blade.php`

- [ ] **Step 1: Aggiungi le sezioni 6-10 prima di `@endsection`**

Sostituisci `@endsection` finale con le sezioni seguenti più `@endsection`:

```blade
{{-- 6. ORARI --}}
@if($profile->opening_hours)
@php
    $days = ['mon'=>'Lunedì','tue'=>'Martedì','wed'=>'Mercoledì','thu'=>'Giovedì','fri'=>'Venerdì','sat'=>'Sabato','sun'=>'Domenica'];
    $todayKey = strtolower(now()->format('D'));
    $dayMap = ['Mon'=>'mon','Tue'=>'tue','Wed'=>'wed','Thu'=>'thu','Fri'=>'fri','Sat'=>'sat','Sun'=>'sun'];
    $todayKey = $dayMap[now()->format('D')] ?? '';
@endphp
<section class="py-16 px-4">
    <div class="mx-auto max-w-md">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">Orari</h2>
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            @foreach($days as $key => $label)
                @php $day = $profile->opening_hours[$key] ?? null; @endphp
                <div class="flex items-center justify-between px-4 py-3 {{ $loop->even ? 'bg-gray-50 dark:bg-gray-900/50' : 'bg-white dark:bg-gray-900' }} {{ $key === $todayKey ? 'ring-1 ring-inset' : '' }}"
                     @if($key === $todayKey) style="ring-color: var(--color-primary)" @endif>
                    <span class="text-sm font-medium {{ $key === $todayKey ? 'font-semibold' : '' }} text-gray-900 dark:text-gray-50">
                        {{ $label }}
                        @if($key === $todayKey)
                            <span class="ml-1.5 rounded-full px-1.5 py-0.5 text-xs text-white"
                                  style="background-color: var(--color-primary)">oggi</span>
                        @endif
                    </span>
                    @if($day && !($day['closed'] ?? false))
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $day['open'] ?? '09:00' }} – {{ $day['close'] ?? '18:00' }}
                        </span>
                    @else
                        <span class="text-sm text-gray-400">Chiuso</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 7. CONTATTI + MAPPA --}}
@if($profile->phone || $profile->address)
<section class="bg-gray-50 dark:bg-gray-900/50 py-16 px-4">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">Contatti</h2>
        <div class="grid gap-8 lg:grid-cols-2">
            <div class="space-y-4">
                @if($profile->phone)
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <a href="tel:{{ $profile->phone }}" class="text-gray-700 dark:text-gray-300 hover:underline">{{ $profile->phone }}</a>
                    </div>
                @endif
                @if($profile->address)
                    <div class="flex items-start gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="text-gray-700 dark:text-gray-300">{{ $profile->address }}</span>
                    </div>
                @endif
                @if($profile->website)
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                        <a href="{{ $profile->website }}" target="_blank" class="text-gray-700 dark:text-gray-300 hover:underline">{{ $profile->website }}</a>
                    </div>
                @endif
                <div class="pt-4">
                    <a href="{{ route('booking.create') }}"
                       class="inline-block rounded-md px-6 py-3 text-sm font-semibold text-white shadow-sm"
                       style="background-color: var(--color-primary)">
                        Prenota un appuntamento
                    </a>
                </div>
            </div>
            @if($profile->google_maps_embed)
                <div class="overflow-hidden rounded-xl">
                    <iframe src="{{ $profile->google_maps_embed }}"
                            class="h-64 w-full lg:h-full"
                            style="border:0"
                            allowfullscreen
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- 8. RECENSIONI --}}
@if($reviews->isNotEmpty())
<section class="py-16 px-4">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">
            Cosa dicono di noi
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($reviews as $review)
                <article class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm space-y-3">
                    <div class="flex gap-0.5" style="color: var(--color-primary)">
                        @for($i = 0; $i < $review->rating; $i++)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        @endfor
                    </div>
                    <p class="text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $review->body }}</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-50">— {{ $review->author_name }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 9. POLICY DI CANCELLAZIONE --}}
@if($profile->cancellation_policy)
<section class="bg-gray-50 dark:bg-gray-900/50 py-16 px-4" x-data="{ open: false }">
    <div class="mx-auto max-w-2xl">
        <button @click="open = !open"
                class="flex w-full items-center justify-between rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-left text-base font-semibold text-gray-900 dark:text-gray-50 shadow-sm">
            Politica di cancellazione
            <svg xmlns="http://www.w3.org/2000/svg"
                 class="h-5 w-5 text-gray-400 transition-transform"
                 :class="open && 'rotate-180'"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" x-transition class="mt-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4" style="display:none">
            {{-- {!! !!} è accettabile: cancellation_policy è editata solo da admin autenticati --}}
            <div class="prose prose-sm prose-gray dark:prose-invert">
                {!! $profile->cancellation_policy !!}
            </div>
        </div>
    </div>
</section>
@endif

{{-- 10. FOOTER --}}
<footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-8 px-4">
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                @if($profile->logoUrl())
                    <img src="{{ $profile->logoUrl() }}" class="h-7 object-contain" alt="{{ $profile->name }}">
                @endif
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $profile->name }}</span>
            </div>
            <div class="flex items-center gap-4">
                @if($profile->instagram_url)
                    <a href="{{ $profile->instagram_url }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Instagram">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                @endif
                @if($profile->facebook_url)
                    <a href="{{ $profile->facebook_url }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Facebook">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                @endif
                @if($profile->tiktok_url)
                    <a href="{{ $profile->tiktok_url }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="TikTok">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.73a4.85 4.85 0 01-1.01-.04z"/></svg>
                    </a>
                @endif
                @if($profile->whatsapp_number)
                    <a href="https://wa.me/{{ $profile->whatsapp_number }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="WhatsApp">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.999 0C5.373 0 0 5.373 0 12c0 2.117.554 4.107 1.523 5.832L.044 23.956l6.278-1.647A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 11.999 0zm.001 21.818a9.818 9.818 0 01-5.011-1.37l-.36-.213-3.726.977.997-3.634-.234-.374A9.775 9.775 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>
                    </a>
                @endif
            </div>
        </div>
        <p class="text-center text-xs text-gray-400">
            © {{ date('Y') }} {{ $profile->name }}. Tutti i diritti riservati.
        </p>
    </div>
</footer>

@endsection
```

- [ ] **Step 2: Verifica in browser tutte le 10 sezioni**

Apri `http://localhost/` e verifica:
- Sezione orari con giorno corrente evidenziato
- Sezione contatti (e mappa se embed configurato)
- Sezione recensioni (visible solo con recensioni pubblicate)
- Accordion policy cancellazione funzionante
- Footer con social links

- [ ] **Step 3: Lancia la suite di test completa**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Atteso: tutti i test verdi.

- [ ] **Step 4: Commit finale**

```bash
git add resources/views/welcome.blade.php
git commit -m "feat: homepage sections 6-10 (orari, contatti, recensioni, policy, footer)"
```

---

## Verifica finale

Dopo tutti i task, fare un test manuale end-to-end:

1. Apri `http://localhost/admin` → "Profilo Salone"
2. Compila tutti i tab: nome, tagline, colore, logo, cover, descrizione, galleria (2-3 foto), orari, contatti, social
3. Vai su "Recensioni" → crea 2 recensioni e pubblicale
4. Vai su "Staff" → modifica uno staff member, aggiungi bio e foto
5. Apri `http://localhost/` → verifica che tutte le sezioni riflettano i dati inseriti
6. Verifica su mobile (DevTools o dispositivo reale) che il layout sia corretto
