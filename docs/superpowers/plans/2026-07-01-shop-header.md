# Shop Header Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere un header configurabile (variante layout + immagine + testo) alla pagina pubblica `/prodotti`, riusando le view hero esistenti.

**Architecture:** Sei nuove colonne nullable in `salon_profiles` contengono la configurazione dell'header. Un nuovo tab "Shop" in `SalonProfilePage` (Filament) permette all'admin di configurarle. La view `shop/index.blade.php` include il sotto-template hero corrispondente alla variante scelta, passando i dati direttamente da `SalonProfile::current()`.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Blade, Spatie FileUpload (non MediaLibrary), AbstractPageBlock::storeAsWebp per conversione webp.

## Global Constraints

- PHP 8 attribute syntax: `#[Fillable([...])]`, non `$fillable`
- `protected function casts(): array` — non `$casts`
- Query scopes restituiscono `Builder`
- Nessun CSS nuovo — riuso completo delle classi `.sf-hero*` esistenti
- Nessun campo CTA nell'header shop (già presente il pulsante "Vai al checkout")
- FileUpload non Spatie per le immagini shop header — i path scalari vengono salvati via `$profile->update($profileData)` nel `save()` esistente
- Il `save()` di `SalonProfilePage` esclude già `['logo', 'logo_dark', 'cover', 'favicon', 'gallery', 'portfolio']` — i nuovi campi shop non vanno esclusi
- Test con `route('shop.index')` — non path hardcodati

---

## File Map

| File | Tipo | Responsabilità |
|---|---|---|
| `database/migrations/2026_07_01_100000_add_shop_header_to_salon_profiles.php` | Nuovo | 6 colonne nullable su `salon_profiles` |
| `app/Models/SalonProfile.php` | Modifica | Aggiunta 6 campi a `#[Fillable]` |
| `app/Filament/Pages/SalonProfilePage.php` | Modifica | `mount()` + nuovo tab "Shop" nel form |
| `app/Http/Controllers/Portal/ProductController.php` | Modifica | Passare `$business` alla view |
| `resources/views/shop/index.blade.php` | Modifica | Include hero view prima della griglia prodotti |
| `tests/Feature/Portal/ProductPortalTest.php` | Modifica | Test per header configurabile |

---

## Task 1: Migration e aggiornamento modello

**Files:**
- Create: `database/migrations/2026_07_01_100000_add_shop_header_to_salon_profiles.php`
- Modify: `app/Models/SalonProfile.php`

**Interfaces:**
- Produces: `SalonProfile` con campi `shop_header_variant`, `shop_header_title`, `shop_header_subtitle`, `shop_header_image`, `shop_header_image_mobile`, `shop_header_image_preset` tutti nullable

- [ ] **Step 1: Scrivere il test che verifica i campi nel DB**

In `tests/Feature/Portal/ProductPortalTest.php`, aggiungere alla fine:

```php
it('can store and retrieve shop header fields on salon profile', function () {
    $profile = \App\Models\SalonProfile::firstOrCreate(
        ['business_id' => $this->business->id],
        ['name' => 'Test Salone']
    );

    $profile->update([
        'shop_header_variant'  => 'editorial',
        'shop_header_title'    => 'I nostri prodotti',
        'shop_header_subtitle' => 'Spedizione gratuita sopra i 50€',
        'shop_header_image'    => 'site-builder/shop-header/test.webp',
    ]);

    $profile->refresh();

    expect($profile->shop_header_variant)->toBe('editorial')
        ->and($profile->shop_header_title)->toBe('I nostri prodotti')
        ->and($profile->shop_header_subtitle)->toBe('Spedizione gratuita sopra i 50€')
        ->and($profile->shop_header_image)->toBe('site-builder/shop-header/test.webp')
        ->and($profile->shop_header_image_mobile)->toBeNull()
        ->and($profile->shop_header_image_preset)->toBeNull();
});
```

- [ ] **Step 2: Eseguire il test per verificare che fallisce**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Portal/ProductPortalTest.php --filter "can store and retrieve shop header fields"
```

Expected: FAIL — colonne non esistono ancora.

- [ ] **Step 3: Creare la migrazione**

```bash
docker-compose run --rm --no-deps app php artisan make:migration add_shop_header_to_salon_profiles
```

Aprire il file generato e sostituire il contenuto con:

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
            $table->string('shop_header_variant', 20)->nullable()->after('bg_texture');
            $table->string('shop_header_title', 120)->nullable()->after('shop_header_variant');
            $table->string('shop_header_subtitle', 200)->nullable()->after('shop_header_title');
            $table->string('shop_header_image')->nullable()->after('shop_header_subtitle');
            $table->string('shop_header_image_mobile')->nullable()->after('shop_header_image');
            $table->string('shop_header_image_preset', 50)->nullable()->after('shop_header_image_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'shop_header_variant',
                'shop_header_title',
                'shop_header_subtitle',
                'shop_header_image',
                'shop_header_image_mobile',
                'shop_header_image_preset',
            ]);
        });
    }
};
```

- [ ] **Step 4: Aggiornare `SalonProfile` `#[Fillable]`**

In `app/Models/SalonProfile.php`, aggiungere i 6 campi all'attributo `#[Fillable]`:

```php
#[Fillable([
    'business_id',
    'name', 'logo_path', 'theme', 'theme_mode', 'hero_image_preset',
    'announcement_active', 'announcement_text',
    'meta_description',
    'font_pair', 'border_style', 'bg_texture',
    'phone', 'address',
    'google_maps_embed',
    'opening_hours',
    'instagram_url', 'facebook_url', 'tiktok_url', 'whatsapp_number',
    'email_greeting', 'email_footer_note', 'email_accent_color',
    'shop_header_variant', 'shop_header_title', 'shop_header_subtitle',
    'shop_header_image', 'shop_header_image_mobile', 'shop_header_image_preset',
])]
```

- [ ] **Step 5: Eseguire la migrazione e il test**

```bash
docker-compose run --rm app php artisan migrate
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Portal/ProductPortalTest.php --filter "can store and retrieve shop header fields"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_01_100000_add_shop_header_to_salon_profiles.php \
        app/Models/SalonProfile.php \
        tests/Feature/Portal/ProductPortalTest.php
git commit -m "feat(shop-header): add DB columns and update SalonProfile fillable"
```

---

## Task 2: Tab "Shop" in SalonProfilePage (admin)

**Files:**
- Modify: `app/Filament/Pages/SalonProfilePage.php`

**Interfaces:**
- Consumes: `SalonProfile` con i 6 campi nullable da Task 1
- Consumes: `SalonProfile::heroPresets()` — restituisce `array<string, ['label' => string, 'url' => string, 'thumb' => string]>`
- Consumes: `AbstractPageBlock::storeAsWebp(UploadedFile $file, string $directory): string` — restituisce path stringa relativo al disco `public`
- Consumes: view Filament `filament.forms.hero-preset-picker` — stessa view già usata dal blocco Hero
- Produces: salvataggio automatico via `save()` esistente (i nuovi campi fluiscono in `$profile->update($profileData)` senza modifiche a `save()`)

- [ ] **Step 1: Aggiungere inizializzazione in `mount()`**

Nella funzione `mount()` di `SalonProfilePage`, aggiungere dopo `'email_accent_color' => $profile->email_accent_color,`:

```php
'shop_header_variant'      => $profile->shop_header_variant      ?? 'classic',
'shop_header_title'        => $profile->shop_header_title,
'shop_header_subtitle'     => $profile->shop_header_subtitle,
'shop_header_image'        => $profile->shop_header_image,
'shop_header_image_mobile' => $profile->shop_header_image_mobile,
'shop_header_image_preset' => $profile->shop_header_image_preset ?? '',
```

- [ ] **Step 2: Aggiungere il tab "Shop" al form**

Nella funzione `form()`, dopo il tab `'Anteprima & Condivisione'` (l'ultimo tab), aggiungere:

```php
Tab::make('Shop')->schema([
    Radio::make('shop_header_variant')
        ->label('Layout header shop')
        ->options([
            'classic'   => 'Sfondo immagine piena con testo centrato',
            'editorial' => 'Immagine laterale con testo a sinistra',
            'centered'  => 'Sfondo tinta unita con testo centrato',
        ])
        ->default('classic')
        ->columnSpanFull(),

    TextInput::make('shop_header_title')
        ->label('Titolo')
        ->placeholder('Prodotti')
        ->maxLength(120),

    Textarea::make('shop_header_subtitle')
        ->label('Sottotitolo')
        ->placeholder('Acquista i prodotti del salone con ritiro in sede.')
        ->maxLength(200)
        ->rows(2),

    FileUpload::make('shop_header_image')
        ->label('Immagine desktop')
        ->image()
        ->disk('public')
        ->saveUploadedFileUsing(fn ($file) => \App\PageBlocks\AbstractPageBlock::storeAsWebp($file, 'site-builder/shop-header'))
        ->helperText('Mostrata su tutti i dispositivi se non viene caricata un\'immagine mobile.'),

    FileUpload::make('shop_header_image_mobile')
        ->label('Immagine mobile (opzionale)')
        ->image()
        ->disk('public')
        ->saveUploadedFileUsing(fn ($file) => \App\PageBlocks\AbstractPageBlock::storeAsWebp($file, 'site-builder/shop-header'))
        ->helperText('Sostituisce l\'immagine desktop su schermi ≤ 640px. Usa formato verticale o quadrato.'),

    Radio::make('shop_header_image_preset')
        ->label('Oppure scegli immagine predefinita')
        ->options(array_merge(
            ['' => 'Nessuna'],
            array_map(fn ($p) => $p['label'], SalonProfile::heroPresets())
        ))
        ->dehydrateStateUsing(fn ($state) => $state ?: null)
        ->view('filament.forms.hero-preset-picker'),
]),
```

Note: `FileUpload` e `Textarea` sono già importati nelle use del file. Verificare che `Radio` sia importato (`use Filament\Forms\Components\Radio;` — già presente).

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Pages/SalonProfilePage.php
git commit -m "feat(shop-header): add Shop tab in SalonProfilePage admin"
```

---

## Task 3: Frontend — controller e view

**Files:**
- Modify: `app/Http/Controllers/Portal/ProductController.php`
- Modify: `resources/views/shop/index.blade.php`
- Modify: `tests/Feature/Portal/ProductPortalTest.php`

**Interfaces:**
- Consumes: `Business::currentId(): int` — ID del business corrente (lancia `RuntimeException` se non bound)
- Consumes: `SalonProfile::current(): SalonProfile` — profilo del business corrente, campi shop_header_* nullable
- Consumes: `SalonProfile::heroPresets(): array` — stessa mappa preset già usata nel blocco Hero
- Consumes: view Blade `page-blocks.hero.{variant}` con variabili: `$content`, `$settings`, `$hero_preset_url`, `$business`, `$block`

- [ ] **Step 1: Scrivere i test per la view shop con header**

In `tests/Feature/Portal/ProductPortalTest.php`, aggiungere:

```php
it('shows default shop header title when profile has no shop config', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->get(route('shop.index'))
        ->assertOk()
        ->assertSee('Prodotti');
});

it('shows configured shop header title', function () {
    \App\Models\SalonProfile::firstOrCreate(
        ['business_id' => $this->business->id],
        ['name' => 'Test Salone']
    )->update([
        'shop_header_title'   => 'I nostri prodotti',
        'shop_header_variant' => 'classic',
    ]);

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->get(route('shop.index'))
        ->assertOk()
        ->assertSee('I nostri prodotti');
});

it('shows centered variant shop header', function () {
    \App\Models\SalonProfile::firstOrCreate(
        ['business_id' => $this->business->id],
        ['name' => 'Test Salone']
    )->update([
        'shop_header_variant'  => 'centered',
        'shop_header_title'    => 'Shop',
        'shop_header_subtitle' => 'Acquista online',
    ]);

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->get(route('shop.index'))
        ->assertOk()
        ->assertSee('Shop')
        ->assertSee('Acquista online');
});
```

- [ ] **Step 2: Eseguire i test per verificare che falliscono**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Portal/ProductPortalTest.php --filter "shop header"
```

Expected: FAIL — `$business` non definita nella view.

- [ ] **Step 3: Aggiornare `ProductController@index`**

In `app/Http/Controllers/Portal/ProductController.php`, aggiungere l'import di `Business`:

```php
use App\Models\Business;
```

Aggiornare il metodo `index()`:

```php
public function index(): View
{
    $products = Product::inSale()->with('media')->orderBy('name')->get();
    $cart     = session('product_cart', []);
    $business = Business::find(Business::currentId());

    $cartItems = collect($cart)->map(function (int $qty, int $productId) use ($products) {
        $product = $products->firstWhere('id', $productId);
        return $product ? ['product' => $product, 'quantity' => $qty] : null;
    })->filter()->values();

    return view('shop.index', compact('products', 'cartItems', 'business'));
}
```

- [ ] **Step 4: Aggiornare `shop/index.blade.php`**

Sostituire l'intero contenuto del file con:

```blade
@extends('layouts.storefront')

@section('title', 'Prodotti')

@section('content')
@php
    $shopProfile = \App\Models\SalonProfile::current();
    $shopVariant = $shopProfile->shop_header_variant ?? 'classic';
    $_shopContent = [
        'title'        => $shopProfile->shop_header_title ?? 'Prodotti',
        'subtitle'     => $shopProfile->shop_header_subtitle ?? 'Acquista i prodotti del salone con ritiro in sede.',
        'image'        => $shopProfile->shop_header_image,
        'image_mobile' => $shopProfile->shop_header_image_mobile,
        'cta_label'    => '',
    ];
    $_shopSettings  = ['show_cta' => false];
    $_shopPreset    = $shopProfile->shop_header_image_preset;
    $_heroPresetUrl = $_shopPreset ? (\App\Models\SalonProfile::heroPresets()[$_shopPreset]['url'] ?? null) : null;
@endphp
@include("page-blocks.hero.{$shopVariant}", [
    'content'         => $_shopContent,
    'settings'        => $_shopSettings,
    'hero_preset_url' => $_heroPresetUrl,
    'business'        => $business,
    'block'           => null,
])

<section class="sf-section">
    <div style="max-width:1100px;margin:0 auto">
        @if ($cartItems->isNotEmpty())
            <div style="display:flex;justify-content:flex-end;margin-bottom:40px">
                <a href="{{ route('shop.checkout') }}" class="sf-btn" style="text-decoration:none">
                    Vai al checkout ({{ $cartItems->sum('quantity') }})
                </a>
            </div>
        @endif

        @if ($errors->any())
            <div style="background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.2);border-radius:min(var(--sf-radius),12px);padding:14px 18px;margin-bottom:24px;color:#dc2626;font-size:0.9rem">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($products->isEmpty())
            <p style="color:var(--sf-body);text-align:center;padding:60px 0">Nessun prodotto disponibile al momento.</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px">
                @foreach ($products as $product)
                    @php $inCart = $cartItems->firstWhere('product.id', $product->id); @endphp
                    <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:min(var(--sf-radius),16px);overflow:hidden;display:flex;flex-direction:column">
                        @if ($product->hasMedia('photo'))
                            <img src="{{ $product->getFirstMediaUrl('photo', 'thumb') }}" alt="{{ $product->name }}"
                                 style="width:100%;height:220px;object-fit:cover;display:block">
                        @else
                            <div style="width:100%;height:220px;background:var(--sf-bg-alt);display:flex;align-items:center;justify-content:center">
                                <span style="color:var(--sf-body);font-size:0.85rem">Nessuna foto</span>
                            </div>
                        @endif

                        <div style="padding:20px;flex:1;display:flex;flex-direction:column;gap:12px">
                            <div>
                                <h3 style="font-weight:600;color:var(--sf-ink);margin:0 0 6px;font-size:1rem">{{ $product->name }}</h3>
                                @if ($product->description)
                                    <p style="color:var(--sf-body);font-size:0.875rem;margin:0;line-height:1.5">{{ $product->description }}</p>
                                @endif
                            </div>
                            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px">
                                <p style="font-size:1.2rem;font-weight:700;color:var(--sf-accent);margin:0">
                                    {{ number_format($product->price, 2, ',', '.') }} €
                                </p>
                                @if ($inCart)
                                    <form method="POST" action="{{ route('shop.cart.remove', $product->id) }}" style="display:inline;flex-shrink:0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background:none;border:none;cursor:pointer;font-size:0.78rem;color:var(--sf-muted);padding:0;text-decoration:underline;text-underline-offset:2px">
                                            Rimuovi
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div style="margin-top:auto">
                                @if ($product->stock === 0)
                                    <span style="display:inline-block;background:var(--sf-bg-alt);border:1px solid var(--sf-border);border-radius:100px;padding:6px 14px;font-size:0.8rem;color:var(--sf-body)">
                                        Esaurito
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('shop.cart.update') }}">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <input type="number" name="quantity" value="{{ $inCart['quantity'] ?? 1 }}"
                                                   min="1" max="{{ $product->stock }}"
                                                   style="width:72px;border:1px solid var(--sf-border);border-radius:min(var(--sf-radius),20px);padding:8px 10px;font-size:0.875rem;background:var(--sf-bg);color:var(--sf-ink)">
                                            <button type="submit" class="sf-btn" style="flex:1;text-align:center">
                                                {{ $inCart ? 'Aggiorna' : 'Aggiungi' }}
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
```

Nota: il `<div>` con titolo e sottotitolo statici è stato svuotato — l'header è ora nel blocco hero. Il `<a>` carrello è rimasto nella sezione perché è funzionale, non decorativo.

- [ ] **Step 5: Eseguire i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Portal/ProductPortalTest.php --filter "shop header"
```

Expected: tutti e 3 i test PASS.

- [ ] **Step 6: Eseguire la suite completa**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: tutti i test passano (nessuna regressione).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Portal/ProductController.php \
        resources/views/shop/index.blade.php \
        tests/Feature/Portal/ProductPortalTest.php
git commit -m "feat(shop-header): render configurable hero header on shop page"
```
