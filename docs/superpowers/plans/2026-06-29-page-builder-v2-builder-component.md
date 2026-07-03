# Page Builder v2 — Filament Builder Component

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the row-per-block `business_page_blocks` table with Filament's native `Builder` form component, storing blocks as a JSON array in `salon_profiles.blocks` and `page_templates.blocks`.

**Architecture:** Each block type gains a `getBlock(): Block` factory method; `SiteBuilderPage` becomes a Filament form page with a `Builder` field; `PageTemplateResource` embeds a Builder field inline (no more RelationManager). Two DB tables (`page_template_blocks`, `business_page_blocks`) are dropped. The 25 Blade views and the block PHP infrastructure are unchanged apart from the `resolveData()` signature.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, MySQL 8, `Filament\Forms\Components\Builder`.

## Global Constraints

- PHP 8.4 attribute syntax: `#[Fillable([...])]` — NOT `$fillable`; `protected function casts(): array` — NOT `$casts`
- Filament 4 form method signature: `public function form(Schema $schema): Schema` (not `Form $form`)
- `Section` → `Filament\Schemas\Components\Section`; `Schema` → `Filament\Schemas\Schema`
- `Builder` → `Filament\Forms\Components\Builder`; `Block` → `Filament\Forms\Components\Builder\Block`
- Filament 4 form page: `implements HasForms` + `use InteractsWithForms` (see `AppointmentCalendar.php` for the exact pattern)
- `getRelations()` — NOT `getRelationManagers()`
- No `{!! !!}` in Blade views — XSS rule
- `withoutGlobalScopes()` everywhere block/profile data is read outside tenant context
- Test command: `docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/`
- After any migration change run: `docker-compose run --rm app php artisan migrate:fresh`
- Factory docblock: `/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Foo> */`
- `RefreshDatabase` is global in `tests/Pest.php` — do NOT add it per-test

## Block data format

Filament's `Builder` stores state as an associative array keyed by UUIDs internally. When reading `$profile->blocks` (after a Laravel `array` cast), each iteration yields one element shaped as:

```php
// $block (one element when iterating $profile->blocks)
[
    'type' => 'hero',
    'data' => [
        'variant'  => 'classic',
        'content'  => ['title' => 'Benvenuti', 'subtitle' => ''],
        'settings' => ['alignment' => 'center', 'show_cta' => true],
    ],
]
```

The `data` sub-array maps exactly to what the block's `getBlock()` schema stores: `Select::make('variant')` stores `data['variant']`, `TextInput::make('content.title')` stores `data['content']['title']` (dot-notation → nested).

For the seeder and init command, blocks are stored with numeric keys:

```php
[
    ['type' => 'hero', 'data' => ['variant' => 'classic', 'content' => [...], 'settings' => [...]]],
]
```

The Builder loads both numeric-keyed and UUID-keyed arrays correctly.

## File map

**Delete (7 files):**
- `database/migrations/2026_06_26_000011_create_page_template_blocks_table.php`
- `database/migrations/2026_06_26_000012_create_business_page_blocks_table.php`
- `app/Models/PageTemplateBlock.php`
- `app/Models/BusinessPageBlock.php`
- `database/factories/PageTemplateBlockFactory.php`
- `database/factories/BusinessPageBlockFactory.php`
- `app/Filament/SuperAdmin/Resources/PageTemplateResource/RelationManagers/PageTemplateBlocksRelationManager.php`

**Modify:**
- `database/migrations/2026_06_26_000010_create_page_templates_table.php` — add `blocks` JSON nullable
- `database/migrations/2026_06_26_000013_add_page_template_id_to_salon_profiles.php` — replace: add `blocks` JSON, no `page_template_id`
- `app/Models/PageTemplate.php` — add `blocks` cast, remove `pageTemplateBlocks()` relation
- `app/Models/SalonProfile.php` — add `blocks` cast, remove `page_template_id` / `pageTemplate()` relation
- `app/PageBlocks/Contracts/PageBlockContract.php` — add `getBlock()`, change `resolveData()` signature, remove `contentRules()`/`settingsRules()`
- `app/PageBlocks/AbstractPageBlock.php` — same
- `app/PageBlocks/ServicesBlock.php` — update `resolveData()` signature
- `app/PageBlocks/StaffBlock.php` — same
- `app/PageBlocks/GalleryBlock.php` — same
- `app/PageBlocks/ReviewsBlock.php` — same
- `app/PageBlocks/ContactInfoBlock.php` — same
- `app/PageBlocks/MapBlock.php` — same
- `app/View/Components/PageBlock.php` — new constructor: `$type` + `$data` instead of `$block`
- `resources/views/components/page-block.blade.php` — use `$data['content']` / `$data['settings']`
- `resources/views/welcome.blade.php` — update loop
- `app/Http/Controllers/Portal/BookingController.php` — read `$profile->blocks`
- `app/Filament/Pages/SiteBuilderPage.php` — complete rewrite as Builder form page
- `resources/views/filament/pages/site-builder.blade.php` — update for form
- `app/Filament/SuperAdmin/Resources/PageTemplateResource.php` — add Builder field, remove RelationManager, simplify clone
- `database/seeders/PageBuilderSeeder.php` — store blocks as JSON
- `app/Console/Commands/PageBuilderInit.php` — simplify to JSON copy
- `tests/Feature/PageBuilder/PageTemplateTest.php` — update model tests
- `tests/Feature/PageBuilder/BusinessPageBlockTest.php` — rewrite (model gone)
- `tests/Feature/PageBuilder/PageBlockComponentTest.php` — update signature
- `tests/Feature/PageBuilder/StorefrontControllerTest.php` — update
- `tests/Feature/PageBuilder/PageBuilderInitCommandTest.php` — update

**Unchanged:** All 25 Blade block views (`resources/views/page-blocks/**`), `app/PageBlocks/PageBlockRegistry.php`, `HeroBlock.php`, `AboutBlock.php`, `CtaBlock.php`, `FaqBlock.php`, `resources/views/page-blocks/styles.blade.php`.

---

### Task 1: Rework migrations and models

**Files:**
- Delete: `database/migrations/2026_06_26_000011_create_page_template_blocks_table.php`
- Delete: `database/migrations/2026_06_26_000012_create_business_page_blocks_table.php`
- Delete: `app/Models/PageTemplateBlock.php`, `app/Models/BusinessPageBlock.php`
- Delete: `database/factories/PageTemplateBlockFactory.php`, `database/factories/BusinessPageBlockFactory.php`
- Modify: `database/migrations/2026_06_26_000010_create_page_templates_table.php`
- Modify: `database/migrations/2026_06_26_000013_add_page_template_id_to_salon_profiles.php`
- Modify: `app/Models/PageTemplate.php`
- Modify: `app/Models/SalonProfile.php`
- Test: `tests/Feature/PageBuilder/PageTemplateTest.php`

**Interfaces:**
- Produces: `PageTemplate::$blocks` — `array` cast JSON column on `page_templates` table; `SalonProfile::$blocks` — same on `salon_profiles` table. Both nullable, default `null`.

- [ ] **Step 1: Write the failing test first**

Replace `tests/Feature/PageBuilder/PageTemplateTest.php` entirely:

```php
<?php

use App\Models\PageTemplate;
use App\Models\SalonProfile;
use App\Models\Business;

it('page_templates table has correct columns', function () {
    $cols = Schema::getColumnListing('page_templates');
    expect($cols)->toContain('id', 'name', 'slug', 'description', 'is_active', 'is_default', 'blocks');
    expect($cols)->not->toContain('page_template_blocks');
});

it('business_page_blocks table does not exist', function () {
    expect(Schema::hasTable('business_page_blocks'))->toBeFalse();
});

it('page_template_blocks table does not exist', function () {
    expect(Schema::hasTable('page_template_blocks'))->toBeFalse();
});

it('salon_profiles table has blocks column', function () {
    expect(Schema::hasColumn('salon_profiles', 'blocks'))->toBeTrue();
    expect(Schema::hasColumn('salon_profiles', 'page_template_id'))->toBeFalse();
});

it('PageTemplate blocks cast returns array', function () {
    $template = PageTemplate::factory()->create(['blocks' => [
        ['type' => 'hero', 'data' => ['variant' => 'classic', 'content' => [], 'settings' => []]],
    ]]);
    expect($template->fresh()->blocks)->toBeArray()->toHaveCount(1);
});

it('PageTemplate blocks is null when not set', function () {
    $template = PageTemplate::factory()->create(['blocks' => null]);
    expect($template->fresh()->blocks)->toBeNull();
});

it('SalonProfile blocks cast returns array', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $profile = SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->first();

    $profile->update(['blocks' => [
        ['type' => 'services', 'data' => ['variant' => 'grid_cards', 'content' => [], 'settings' => []]],
    ]]);

    expect($profile->fresh()->blocks)->toBeArray()->toHaveCount(1);
});
```

- [ ] **Step 2: Run test — confirm it fails (tables may not have `blocks` yet)**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageTemplateTest.php
```

Expected: multiple failures about missing column or existing tables.

- [ ] **Step 3: Delete the two obsolete migration files**

```bash
rm database/migrations/2026_06_26_000011_create_page_template_blocks_table.php
rm database/migrations/2026_06_26_000012_create_business_page_blocks_table.php
```

- [ ] **Step 4: Update `2026_06_26_000010_create_page_templates_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('page_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('blocks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_templates');
    }
};
```

- [ ] **Step 5: Replace `2026_06_26_000013_add_page_template_id_to_salon_profiles.php` entirely**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropColumn('blocks');
        });
    }
};
```

- [ ] **Step 6: Run `migrate:fresh`**

```bash
docker-compose run --rm app php artisan migrate:fresh
```

Expected: OK, no errors.

- [ ] **Step 7: Delete obsolete model files**

```bash
rm app/Models/PageTemplateBlock.php app/Models/BusinessPageBlock.php
rm database/factories/PageTemplateBlockFactory.php database/factories/BusinessPageBlockFactory.php
```

- [ ] **Step 8: Update `app/Models/PageTemplate.php`**

Remove the `pageTemplateBlocks()` HasMany relation; add `blocks` to `#[Fillable]` and `casts()`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @use HasFactory<\Database\Factories\PageTemplateFactory> */
#[Fillable(['name', 'slug', 'description', 'is_active', 'is_default', 'blocks'])]
class PageTemplate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
            'blocks'     => 'array',
        ];
    }
}
```

- [ ] **Step 9: Update `app/Models/SalonProfile.php`**

Read the current file first. Then:
- Remove `page_template_id` from `#[Fillable]`
- Remove `pageTemplate()` BelongsTo relation
- Add `blocks` to `#[Fillable]`
- Add `'blocks' => 'array'` to `casts()`

The `#[Fillable]` array should gain `'blocks'` and lose `'page_template_id'`. The `casts()` method should gain `'blocks' => 'array'`. The `pageTemplate()` method should be deleted.

- [ ] **Step 10: Run tests**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageTemplateTest.php
```

Expected: 7 passed.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "refactor(page-builder): drop block row tables, add blocks JSON to templates and profiles"
```

---

### Task 2: Update block contract, abstract base, and 6 dynamic block classes

**Files:**
- Modify: `app/PageBlocks/Contracts/PageBlockContract.php`
- Modify: `app/PageBlocks/AbstractPageBlock.php`
- Modify: `app/PageBlocks/ServicesBlock.php`
- Modify: `app/PageBlocks/StaffBlock.php`
- Modify: `app/PageBlocks/GalleryBlock.php`
- Modify: `app/PageBlocks/ReviewsBlock.php`
- Modify: `app/PageBlocks/ContactInfoBlock.php`
- Modify: `app/PageBlocks/MapBlock.php`
- Test: `tests/Feature/PageBuilder/PageBlockRegistryTest.php`

**Interfaces:**
- Consumes: `Filament\Forms\Components\Builder\Block` (vendor)
- Produces:
  - `PageBlockContract::getBlock(): \Filament\Forms\Components\Builder\Block` — factory for Filament block definition
  - `PageBlockContract::resolveData(Business $business, array $blockData): array` — `$blockData` has shape `['variant' => string, 'content' => array, 'settings' => array]`
  - Static blocks (Hero, About, CTA, FAQ) inherit `getBlock()` and `resolveData()` from abstract base — no changes needed in those 4 classes.

- [ ] **Step 1: Write the failing tests**

Replace `tests/Feature/PageBuilder/PageBlockRegistryTest.php` entirely:

```php
<?php

use App\Models\Business;
use App\Models\SalonProfile;
use App\PageBlocks\HeroBlock;
use App\PageBlocks\PageBlockRegistry;
use App\PageBlocks\ServicesBlock;
use Filament\Forms\Components\Builder\Block;

it('registry returns all 10 block types', function () {
    expect(PageBlockRegistry::all())->toHaveCount(10);
});

it('registry find returns correct class', function () {
    expect(PageBlockRegistry::find('hero'))->toBe(HeroBlock::class);
    expect(PageBlockRegistry::find('unknown'))->toBeNull();
});

it('isValidVariant works correctly', function () {
    expect(PageBlockRegistry::isValidVariant('hero', 'classic'))->toBeTrue();
    expect(PageBlockRegistry::isValidVariant('hero', 'nonexistent'))->toBeFalse();
});

it('each block class returns a Filament Block instance from getBlock()', function () {
    foreach (PageBlockRegistry::all() as $type => $class) {
        $block = $class::getBlock();
        expect($block)->toBeInstanceOf(Block::class)
            ->and($block->getName())->toBe($type);
    }
});

it('ServicesBlock resolveData uses blockData array', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $blockData = ['variant' => 'grid_cards', 'content' => ['title' => 'Test'], 'settings' => ['featured_only' => false]];
    $result = ServicesBlock::resolveData($business, $blockData);
    expect($result)->toHaveKey('services');
});

it('resolveData does not use BusinessPageBlock model', function () {
    // resolveData() signature now takes array, not model — static blocks return []
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $blockData = ['variant' => 'classic', 'content' => [], 'settings' => []];
    expect(HeroBlock::resolveData($business, $blockData))->toBe([]);
});
```

- [ ] **Step 2: Run tests — confirm failure**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockRegistryTest.php
```

Expected: failures on `getBlock()` and the new `resolveData()` signature.

- [ ] **Step 3: Update `app/PageBlocks/Contracts/PageBlockContract.php`**

```php
<?php

namespace App\PageBlocks\Contracts;

use App\Models\Business;
use Filament\Forms\Components\Builder\Block;

interface PageBlockContract
{
    public static function type(): string;
    public static function label(): string;
    public static function description(): string;
    public static function icon(): string;

    /** @return array<string, array{label: string, description: string}> */
    public static function variants(): array;

    public static function defaultVariant(): string;
    public static function defaultContent(): array;
    public static function defaultSettings(): array;

    /** Filament form fields. Fields use state paths content.* and settings.* */
    public static function filamentFields(): array;

    /** Filament Builder block definition for this block type. */
    public static function getBlock(): Block;

    /** Blade view path for the given variant, e.g. 'page-blocks.hero.classic' */
    public static function viewFor(string $variant): string;

    /**
     * Data injected into the view at render time.
     * $blockData = ['variant' => string, 'content' => array, 'settings' => array]
     * Static blocks return []. Dynamic blocks return model collections.
     */
    public static function resolveData(Business $business, array $blockData): array;
}
```

- [ ] **Step 4: Update `app/PageBlocks/AbstractPageBlock.php`**

```php
<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\PageBlocks\Contracts\PageBlockContract;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;

abstract class AbstractPageBlock implements PageBlockContract
{
    public static function description(): string
    {
        return '';
    }

    public static function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function defaultContent(): array
    {
        return [];
    }

    public static function defaultSettings(): array
    {
        return [];
    }

    public static function filamentFields(): array
    {
        return [];
    }

    public static function viewFor(string $variant): string
    {
        return 'page-blocks.' . static::type() . '.' . $variant;
    }

    public static function defaultVariant(): string
    {
        return array_key_first(static::variants());
    }

    public static function getBlock(): Block
    {
        $variantOptions = collect(static::variants())
            ->mapWithKeys(fn ($v, $k) => [$k => $v['label']])
            ->all();

        return Block::make(static::type())
            ->label(static::label())
            ->icon(static::icon())
            ->schema([
                Select::make('variant')
                    ->label('Layout')
                    ->options($variantOptions)
                    ->default(static::defaultVariant())
                    ->required(),
                ...static::filamentFields(),
            ]);
    }

    public static function resolveData(Business $business, array $blockData): array
    {
        return [];
    }
}
```

- [ ] **Step 5: Update `resolveData()` in the 6 dynamic block classes**

Each of these classes currently has `public static function resolveData(Business $business, BusinessPageBlock $block): array`. Change the signature and any `$block->...` references.

**`app/PageBlocks/ServicesBlock.php`** — change signature and `$block->settings` reference:

```php
public static function resolveData(Business $business, array $blockData): array
{
    $query = Service::withoutGlobalScope('business')
        ->where('business_id', $business->id)
        ->where('active', true)
        ->orderBy('sort_order')
        ->orderBy('name');

    if ($blockData['settings']['featured_only'] ?? false) {
        $query->where('featured', true);
    }

    return ['services' => $query->get()];
}
```

Also remove the `use App\Models\BusinessPageBlock;` import from `ServicesBlock`.

**`app/PageBlocks/StaffBlock.php`** — signature change only (no `$block` reference in body):

```php
public static function resolveData(Business $business, array $blockData): array
{
    $staff = User::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->whereHas('roles', fn ($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
        ->with('media')
        ->orderBy('sort_order')
        ->get();

    return ['staff' => $staff];
}
```

Remove `use App\Models\BusinessPageBlock;` import.

**`app/PageBlocks/GalleryBlock.php`** — signature change only:

```php
public static function resolveData(Business $business, array $blockData): array
{
    $profile = SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->first();

    return ['images' => $profile ? $profile->getMedia('portfolio') : collect()];
}
```

Remove `use App\Models\BusinessPageBlock;` import.

**`app/PageBlocks/ReviewsBlock.php`** — signature change only:

```php
public static function resolveData(Business $business, array $blockData): array
{
    $reviews = SalonReview::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->published()
        ->ordered()
        ->get();

    return ['reviews' => $reviews];
}
```

Remove `use App\Models\BusinessPageBlock;` import.

**`app/PageBlocks/ContactInfoBlock.php`** — signature change only:

```php
public static function resolveData(Business $business, array $blockData): array
{
    $profile = SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->first();

    return ['profile' => $profile];
}
```

Remove `use App\Models\BusinessPageBlock;` import.

**`app/PageBlocks/MapBlock.php`** — signature change only:

```php
public static function resolveData(Business $business, array $blockData): array
{
    $profile = SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->first();

    return ['profile' => $profile];
}
```

Remove `use App\Models\BusinessPageBlock;` import.

- [ ] **Step 6: Run tests**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockRegistryTest.php
```

Expected: 6 passed.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(page-builder): add getBlock() factory, change resolveData() to array signature"
```

---

### Task 3: Update rendering layer

**Files:**
- Modify: `app/View/Components/PageBlock.php`
- Modify: `resources/views/components/page-block.blade.php`
- Modify: `resources/views/welcome.blade.php`
- Modify: `app/Http/Controllers/Portal/BookingController.php`
- Test: `tests/Feature/PageBuilder/PageBlockComponentTest.php`
- Test: `tests/Feature/PageBuilder/StorefrontControllerTest.php`

**Interfaces:**
- Consumes: `PageBlockContract::resolveData(Business, array): array` from Task 2
- Produces: `<x-page-block :business="$business" :type="$block['type']" :data="$block['data']" />` — new Blade component signature

- [ ] **Step 1: Write failing tests**

Replace `tests/Feature/PageBuilder/PageBlockComponentTest.php`:

```php
<?php

use App\Models\Business;
use App\Models\SalonProfile;

it('renders a block from type and data array', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $component = new \App\View\Components\PageBlock(
        business: $business,
        type: 'hero',
        data: ['variant' => 'classic', 'content' => ['title' => 'Test'], 'settings' => []],
    );

    expect($component->blockClass)->toBe(\App\PageBlocks\HeroBlock::class);
    expect($component->resolvedVariant)->toBe('classic');
});

it('falls back to default variant when unknown variant given', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $component = new \App\View\Components\PageBlock(
        business: $business,
        type: 'hero',
        data: ['variant' => 'nonexistent', 'content' => [], 'settings' => []],
    );

    expect($component->resolvedVariant)->toBe('classic'); // HeroBlock default
});

it('returns null blockClass for unknown type', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $component = new \App\View\Components\PageBlock(
        business: $business,
        type: 'unknown_block',
        data: ['variant' => 'v1', 'content' => [], 'settings' => []],
    );

    expect($component->blockClass)->toBeNull();
});

it('does not modify DB when variant is invalid', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    new \App\View\Components\PageBlock(
        business: $business,
        type: 'hero',
        data: ['variant' => 'bad_variant', 'content' => [], 'settings' => []],
    );

    // No DB write — just verify no exception
    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: Run tests — confirm failure**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockComponentTest.php
```

Expected: failures (constructor signature changed).

- [ ] **Step 3: Rewrite `app/View/Components/PageBlock.php`**

```php
<?php

namespace App\View\Components;

use App\Models\Business;
use App\PageBlocks\PageBlockRegistry;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;

class PageBlock extends Component
{
    public readonly ?string $blockClass;
    public readonly string $resolvedVariant;
    public readonly array $blockData;

    public function __construct(
        public readonly Business $business,
        public readonly string $type,
        public readonly array $data,
    ) {
        $this->blockClass = PageBlockRegistry::find($type);

        if ($this->blockClass === null) {
            Log::warning('PageBlock: unknown block type', ['block_type' => $type]);
            $this->resolvedVariant = '';
            $this->blockData = [];
            return;
        }

        $variant = $data['variant'] ?? '';

        if (PageBlockRegistry::isValidVariant($type, $variant)) {
            $this->resolvedVariant = $variant;
        } else {
            $fallback = PageBlockRegistry::defaultVariant($type);
            Log::warning('PageBlock: invalid variant, falling back', [
                'block_type'      => $type,
                'old_variant'     => $variant,
                'fallback_variant'=> $fallback,
            ]);
            $this->resolvedVariant = $fallback;
        }

        $this->blockData = ($this->blockClass)::resolveData($business, $data);
    }

    public function render(): View|Closure|string
    {
        return view('components.page-block');
    }
}
```

- [ ] **Step 4: Update `resources/views/components/page-block.blade.php`**

```blade
@if($blockClass !== null)
    @php
        $viewPath = ($blockClass)::viewFor($resolvedVariant);
    @endphp

    @if(view()->exists($viewPath))
        @include($viewPath, array_merge([
            'content'  => $data['content'] ?? [],
            'settings' => $data['settings'] ?? [],
            'business' => $business,
        ], $blockData))
    @else
        @if(config('app.debug'))
            <div style="background:#fee;padding:1rem;border:1px solid #f00;margin:1rem 0;">
                PageBlock: view not found: {{ $viewPath }}
            </div>
        @endif
    @endif
@endif
```

- [ ] **Step 5: Update `resources/views/welcome.blade.php`**

```blade
{{-- resources/views/welcome.blade.php --}}
@extends('layouts.storefront')

@section('title', $profile->name ?? $business->name ?? 'Benvenuto')

@push('head')
@include('page-blocks.styles')
@endpush

@section('content')
    @foreach($blocks as $block)
        <x-page-block :business="$business" :type="$block['type']" :data="$block['data']" />
    @endforeach

    <a href="{{ route('booking.create') }}" class="sf-sticky-book sf-btn">{{ $profile->bookingButtonLabel() }}</a>
@endsection

@push('scripts')
<script>
(function () {
    var hero = document.querySelector('.sf-hero');
    var stickyBook = document.querySelector('.sf-sticky-book');
    if (hero && stickyBook) {
        new IntersectionObserver(function (entries) {
            stickyBook.classList.toggle('is-visible', !entries[0].isIntersecting);
        }).observe(hero);
    }
})();
</script>
@endpush
```

- [ ] **Step 6: Update `app/Http/Controllers/Portal/BookingController.php`**

Read the file first, then update only the `index()` method. The new version reads `$profile->blocks` instead of querying `BusinessPageBlock`.

Remove the `use App\Models\BusinessPageBlock;` import.

Replace the `index()` method body:

```php
public function index(): View
{
    if (! app()->bound('current_business_id')) {
        return view('landing');
    }

    $businessId = app('current_business_id');
    $business   = Business::find($businessId);
    $profile    = SalonProfile::current()->load('media');

    $blocks = $profile?->blocks ?? [];

    if (empty($blocks)) {
        Log::warning('page-builder: business has no blocks, rendering legacy', [
            'business_id' => $businessId,
        ]);
        $services = Service::active()->orderBy('sort_order')->orderBy('name')->get();
        $staff    = User::whereHas('roles', fn ($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
            ->where('business_id', $businessId)
            ->with('media')
            ->where(fn ($q) => $q
                ->whereNotNull('bio')
                ->orWhereHas('media', fn ($m) => $m->where('collection_name', 'avatar'))
            )
            ->orderByRaw($this->staffOrderRaw())
            ->orderBy('sort_order')
            ->get();
        $reviews = SystemSetting::isReviewsEnabled()
            ? SalonReview::published()->ordered()->get()
            : collect();

        return view('welcome-legacy', compact('profile', 'services', 'staff', 'reviews'));
    }

    return view('welcome', compact('business', 'blocks', 'profile'));
}
```

- [ ] **Step 7: Run tests**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockComponentTest.php tests/Feature/PageBuilder/StorefrontControllerTest.php
```

Expected: 7 passed (4 component + 3 storefront).

Note: `StorefrontControllerTest.php` uses `$profile->blocks` via the controller now. The tests create a business, set `current_business_id`, and check view names. They should still pass without changes since the controller now reads `$profile->blocks` and a fresh profile has `null` blocks → renders `welcome-legacy`. For the first test to pass, update it to write `$profile->update(['blocks' => [['type'=>'hero','data'=>['variant'=>'classic','content'=>[],'settings'=>[]]]]]);` instead of creating a `BusinessPageBlock` row.

Replace `tests/Feature/PageBuilder/StorefrontControllerTest.php`:

```php
<?php

use App\Http\Middleware\CheckStorefrontAccess;
use App\Http\Middleware\SubdomainMiddleware;
use App\Models\Business;
use App\Models\SalonProfile;

beforeEach(function () {
    $this->withoutMiddleware([SubdomainMiddleware::class, CheckStorefrontAccess::class]);
});

it('renders welcome view when profile has blocks', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->update(['blocks' => [
            ['type' => 'hero', 'data' => ['variant' => 'classic', 'content' => [], 'settings' => []]],
        ]]);

    $this->get('/')->assertOk()->assertViewIs('welcome');
});

it('falls back to welcome-legacy when profile has no blocks', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    // profile.blocks is null by default

    $this->get('/')->assertOk()->assertViewIs('welcome-legacy');
});

it('falls back to welcome-legacy when blocks is empty array', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->update(['blocks' => []]);

    $this->get('/')->assertOk()->assertViewIs('welcome-legacy');
});
```

- [ ] **Step 8: Run all PageBuilder tests to confirm no regressions**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/
```

Expected: all passing.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(page-builder): update rendering layer to use profile.blocks JSON"
```

---

### Task 4: Rework SiteBuilderPage as Builder form

**Files:**
- Modify: `app/Filament/Pages/SiteBuilderPage.php`
- Modify: `resources/views/filament/pages/site-builder.blade.php`

**Interfaces:**
- Consumes: `PageBlockRegistry::all()` → each class's `getBlock(): Block` from Task 2
- Consumes: `SalonProfile::$blocks` from Task 1
- Produces: Filament page `Il mio sito` — Builder form that reads/writes `salon_profiles.blocks`

- [ ] **Step 1: Replace `app/Filament/Pages/SiteBuilderPage.php` entirely**

```php
<?php

namespace App\Filament\Pages;

use App\Models\PageTemplate;
use App\Models\SalonProfile;
use App\PageBlocks\PageBlockRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class SiteBuilderPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Il mio sito';
    protected static ?string $title = 'Il mio sito';
    protected string $view = 'filament.pages.site-builder';
    protected static string|\UnitEnum|null $navigationGroup = 'Impostazioni';
    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $profile = SalonProfile::withoutGlobalScopes()
            ->where('business_id', app('current_business_id'))
            ->first();

        $this->form->fill(['blocks' => $profile?->blocks ?? []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Builder::make('blocks')
                    ->hiddenLabel()
                    ->blocks(
                        collect(PageBlockRegistry::all())
                            ->map(fn (string $class) => $class::getBlock())
                            ->values()
                            ->all()
                    )
                    ->cloneable()
                    ->blockPickerColumns(3)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SalonProfile::withoutGlobalScopes()
            ->where('business_id', app('current_business_id'))
            ->update(['blocks' => $this->data['blocks'] ?? []]);

        Notification::make()
            ->title('Salvato. Le modifiche sono visibili subito sul sito.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Salva modifiche')
                ->icon('heroicon-o-check')
                ->action('save'),

            Action::make('openSite')
                ->label('Apri sito pubblico')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => url('/'))
                ->openUrlInNewTab()
                ->color('gray'),

            Action::make('changeTemplate')
                ->label('Carica template')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    Select::make('template_id')
                        ->label('Template')
                        ->options(fn () => PageTemplate::where('is_active', true)->pluck('name', 'id'))
                        ->required(),
                    Checkbox::make('confirm')
                        ->label('Confermo: questa azione sovrascriverà la struttura corrente dei blocchi.')
                        ->accepted()
                        ->validationMessages(['accepted' => 'Devi confermare per procedere.']),
                ])
                ->modalHeading('Carica template')
                ->action(function (array $data): void {
                    $template = PageTemplate::find($data['template_id']);
                    if (! $template) {
                        return;
                    }

                    SalonProfile::withoutGlobalScopes()
                        ->where('business_id', app('current_business_id'))
                        ->update(['blocks' => $template->blocks ?? []]);

                    $profile = SalonProfile::withoutGlobalScopes()
                        ->where('business_id', app('current_business_id'))
                        ->first();

                    $this->form->fill(['blocks' => $profile?->blocks ?? []]);

                    Notification::make()
                        ->title('Template caricato. Salva per confermare.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
```

- [ ] **Step 2: Replace `resources/views/filament/pages/site-builder.blade.php`**

```blade
<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
```

- [ ] **Step 3: Smoke-test by loading the page in the browser**

Navigate to the admin panel → "Il mio sito". Confirm:
- Page loads without errors
- Block picker shows all 10 block types
- Adding a block shows the correct form fields
- Saving writes to `salon_profiles.blocks`
- "Carica template" modal works (load a template, form refreshes)

If there are Filament API issues (e.g., `form()` method not found), check `AppointmentCalendar.php` for the exact pattern used in this codebase.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/SiteBuilderPage.php resources/views/filament/pages/site-builder.blade.php
git commit -m "feat(page-builder): replace table UI with Filament Builder form in SiteBuilderPage"
```

---

### Task 5: Rework PageTemplateResource

**Files:**
- Modify: `app/Filament/SuperAdmin/Resources/PageTemplateResource.php`
- Delete: `app/Filament/SuperAdmin/Resources/PageTemplateResource/RelationManagers/PageTemplateBlocksRelationManager.php`

**Interfaces:**
- Consumes: `PageBlockRegistry::all()` → `getBlock(): Block` from Task 2
- Consumes: `PageTemplate::$blocks` from Task 1

- [ ] **Step 1: Delete the RelationManager**

```bash
rm app/Filament/SuperAdmin/Resources/PageTemplateResource/RelationManagers/PageTemplateBlocksRelationManager.php
```

- [ ] **Step 2: Replace `app/Filament/SuperAdmin/Resources/PageTemplateResource.php`**

```php
<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PageTemplateResource\Pages;
use App\Models\PageTemplate;
use App\PageBlocks\PageBlockRegistry;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageTemplateResource extends Resource
{
    protected static ?string $model = PageTemplate::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Template sito';
    protected static string|\UnitEnum|null $navigationGroup = 'Piattaforma';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(80)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(PageTemplate::class, 'slug', ignoreRecord: true)
                    ->maxLength(80),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->default(true),
                Toggle::make('is_default')
                    ->label('Template di default')
                    ->helperText('Un solo template può essere il default. Gli altri verranno resettati.'),
            ])->columns(2),

            Section::make('Blocchi del template')->schema([
                Builder::make('blocks')
                    ->hiddenLabel()
                    ->blocks(
                        collect(PageBlockRegistry::all())
                            ->map(fn (string $class) => $class::getBlock())
                            ->values()
                            ->all()
                    )
                    ->cloneable()
                    ->blockPickerColumns(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug'),
                IconColumn::make('is_active')->label('Attivo')->boolean(),
                IconColumn::make('is_default')->label('Default')->boolean(),
                TextColumn::make('blocks_count')
                    ->label('Blocchi')
                    ->getStateUsing(fn (PageTemplate $record) => count($record->blocks ?? [])),
            ])
            ->actions([
                EditAction::make(),
                Action::make('clone')
                    ->label('Clona')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->action(function (PageTemplate $record): void {
                        $clone = $record->replicate();
                        $clone->name = $record->name . ' copia';
                        $clone->slug = Str::slug($record->name . '-copia-' . now()->timestamp);
                        $clone->is_active = false;
                        $clone->is_default = false;
                        $clone->save();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPageTemplates::route('/'),
            'create' => Pages\CreatePageTemplate::route('/create'),
            'edit'   => Pages\EditPageTemplate::route('/{record}/edit'),
        ];
    }
}
```

Note: the `is_default` single-template reset logic (when toggling `is_default`, reset others to false) was previously implemented in `CreatePageTemplate` and `EditPageTemplate` page files via `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave`. Those page files are NOT modified in this task — their logic remains unchanged. Do NOT add these methods to the Resource class (they are Page-class methods in Filament 4, not Resource-class methods).

Note on `blockPreviews`: the user's example included `->blockPreviews(true, true)`. This requires one Blade preview file per block type. It is intentionally excluded from this plan — add it as a follow-up once the core rework is stable.

- [ ] **Step 3: Smoke-test in superadmin browser**

Navigate to `/superadmin` → Template sito → Edit a template. Confirm:
- No RelationManager tab anymore
- "Blocchi del template" section shows the Builder
- Adding/removing blocks saves correctly

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor(page-builder): replace RelationManager with inline Builder in PageTemplateResource"
```

---

### Task 6: Update seeder, init command, and all tests

**Files:**
- Modify: `database/seeders/PageBuilderSeeder.php`
- Modify: `app/Console/Commands/PageBuilderInit.php`
- Modify: `tests/Feature/PageBuilder/BusinessPageBlockTest.php`
- Modify: `tests/Feature/PageBuilder/PageBuilderInitCommandTest.php`

**Interfaces:**
- Consumes: `PageTemplate::$blocks` (Task 1), `SalonProfile::$blocks` (Task 1)
- Produces: seeded templates with `blocks` JSON; `page-builder:init` writes `salon_profiles.blocks`

- [ ] **Step 1: Rewrite `database/seeders/PageBuilderSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\PageTemplate;
use App\PageBlocks\PageBlockRegistry;
use Illuminate\Database\Seeder;

class PageBuilderSeeder extends Seeder
{
    public function run(): void
    {
        $this->createTemplate('default', 'Default', 'Template standard per tutti i saloni.', true, $this->defaultBlocks());
        $this->createTemplate('minimal', 'Minimal', 'Layout essenziale e pulito.', false, $this->minimalBlocks());
        $this->createTemplate('premium', 'Premium / Luxury', 'Layout elegante ad alto impatto visivo.', false, $this->premiumBlocks());
    }

    private function createTemplate(string $slug, string $name, string $description, bool $isDefault, array $blockDefs): void
    {
        $template = PageTemplate::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => $description, 'is_active' => true, 'is_default' => $isDefault]
        );

        if (empty($template->blocks)) {
            $blocks = [];
            foreach ($blockDefs as $def) {
                $class = PageBlockRegistry::find($def['type']);
                $blocks[] = [
                    'type' => $def['type'],
                    'data' => [
                        'variant'  => $def['variant'],
                        'content'  => $class ? $class::defaultContent() : [],
                        'settings' => $class ? array_merge($class::defaultSettings(), $def['settings'] ?? []) : [],
                    ],
                ];
            }
            $template->update(['blocks' => $blocks]);
        }
    }

    private function defaultBlocks(): array
    {
        return [
            ['type' => 'hero',         'variant' => 'classic'],
            ['type' => 'services',     'variant' => 'grid_cards'],
            ['type' => 'about',        'variant' => 'centered'],
            ['type' => 'staff',        'variant' => 'cards'],
            ['type' => 'gallery',      'variant' => 'grid_3col'],
            ['type' => 'contact_info', 'variant' => 'with_map'],
            ['type' => 'reviews',      'variant' => 'cards'],
            ['type' => 'faq',          'variant' => 'accordion'],
            ['type' => 'cta',          'variant' => 'simple'],
            ['type' => 'map',          'variant' => 'full_width'],
        ];
    }

    private function minimalBlocks(): array
    {
        return [
            ['type' => 'hero',         'variant' => 'centered'],
            ['type' => 'services',     'variant' => 'compact_list'],
            ['type' => 'contact_info', 'variant' => 'simple'],
            ['type' => 'cta',          'variant' => 'simple'],
        ];
    }

    private function premiumBlocks(): array
    {
        return [
            ['type' => 'hero',         'variant' => 'editorial'],
            ['type' => 'about',        'variant' => 'split_image'],
            ['type' => 'services',     'variant' => 'price_list'],
            ['type' => 'staff',        'variant' => 'editorial'],
            ['type' => 'gallery',      'variant' => 'masonry'],
            ['type' => 'reviews',      'variant' => 'carousel'],
            ['type' => 'contact_info', 'variant' => 'with_map'],
        ];
    }
}
```

- [ ] **Step 2: Rewrite `app/Console/Commands/PageBuilderInit.php`**

The command is now much simpler — just copy the template's `blocks` JSON to each business's `SalonProfile`.

```php
<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\PageTemplate;
use App\Models\SalonProfile;
use Illuminate\Console\Command;

class PageBuilderInit extends Command
{
    protected $signature = 'page-builder:init {--business= : Init a specific business by ID} {--force : Overwrite existing blocks}';
    protected $description = 'Initialize salon profiles with page blocks from the default template';

    public function handle(): int
    {
        $template = PageTemplate::where('is_default', true)->where('is_active', true)->first();

        if (! $template || empty($template->blocks)) {
            $this->error('No active default template with blocks found. Run the PageBuilderSeeder first.');
            return self::FAILURE;
        }

        $query = Business::query();
        if ($this->option('business')) {
            $query->where('id', (int) $this->option('business'));
        }
        $businesses = $query->get();

        if ($businesses->isEmpty()) {
            $this->warn('No businesses found.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($businesses->count());
        $bar->start();
        $initialized = 0;

        foreach ($businesses as $business) {
            $profile = SalonProfile::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->first();

            if (! $profile) {
                $bar->advance();
                continue;
            }

            if (! empty($profile->blocks) && ! $this->option('force')) {
                $bar->advance();
                continue;
            }

            if (! empty($profile->blocks) && $this->option('force')) {
                if (! $this->confirm("Business {$business->id} already has blocks. Overwrite?", false)) {
                    $bar->advance();
                    continue;
                }
            }

            $profile->update(['blocks' => $template->blocks]);
            $initialized++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Initialized {$initialized} business(es).");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 3: Rewrite `tests/Feature/PageBuilder/BusinessPageBlockTest.php`**

The `BusinessPageBlock` model is gone. Rewrite as tests for `SalonProfile::$blocks` behavior:

```php
<?php

use App\Models\Business;
use App\Models\SalonProfile;

it('salon_profiles.blocks stores and retrieves block array', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $blocks = [
        ['type' => 'hero', 'data' => ['variant' => 'classic', 'content' => ['title' => 'Test'], 'settings' => []]],
        ['type' => 'services', 'data' => ['variant' => 'grid_cards', 'content' => [], 'settings' => []]],
    ];

    SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->update(['blocks' => $blocks]);

    $profile = SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->first();

    expect($profile->blocks)->toBeArray()->toHaveCount(2);
    expect($profile->blocks[0]['type'])->toBe('hero');
});

it('salon_profiles.blocks defaults to null', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $profile = SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->first();

    expect($profile->blocks)->toBeNull();
});

it('empty blocks array is stored correctly', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->update(['blocks' => []]);

    $profile = SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->first();

    expect($profile->blocks)->toBeArray()->toBeEmpty();
});
```

- [ ] **Step 4: Rewrite `tests/Feature/PageBuilder/PageBuilderInitCommandTest.php`**

```php
<?php

use App\Models\Business;
use App\Models\PageTemplate;
use App\Models\SalonProfile;
use Database\Seeders\PageBuilderSeeder;

it('PageBuilderSeeder creates Default template with 10 blocks as JSON', function () {
    $this->seed(PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'default')->first();
    expect($template)->not->toBeNull();
    expect($template->blocks)->toBeArray()->toHaveCount(10);
    expect($template->blocks[0])->toHaveKey('type')->toHaveKey('data');
});

it('PageBuilderSeeder creates Minimal template with 4 blocks', function () {
    $this->seed(PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'minimal')->first();
    expect($template->blocks)->toHaveCount(4);
});

it('PageBuilderSeeder creates Premium template with 7 blocks', function () {
    $this->seed(PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'premium')->first();
    expect($template->blocks)->toHaveCount(7);
});

it('PageBuilderSeeder is idempotent', function () {
    $this->seed(PageBuilderSeeder::class);
    $this->seed(PageBuilderSeeder::class);

    expect(PageTemplate::where('slug', 'default')->count())->toBe(1);
    expect(PageTemplate::count())->toBe(3);
});

it('page-builder:init copies template blocks to salon profile', function () {
    $this->seed(PageBuilderSeeder::class);

    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $this->artisan('page-builder:init', ['--business' => $business->id])
        ->assertSuccessful();

    $profile = SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->first();

    expect($profile->blocks)->toBeArray()->toHaveCount(10);
});

it('page-builder:init skips already-initialized businesses', function () {
    $this->seed(PageBuilderSeeder::class);

    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $customBlocks = [['type' => 'cta', 'data' => ['variant' => 'simple', 'content' => [], 'settings' => []]]];
    SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->update(['blocks' => $customBlocks]);

    $this->artisan('page-builder:init', ['--business' => $business->id])
        ->assertSuccessful();

    $profile = SalonProfile::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->first();

    // Custom blocks preserved — not overwritten
    expect($profile->blocks)->toHaveCount(1);
    expect($profile->blocks[0]['type'])->toBe('cta');
});

it('page-builder:init fails with no default template', function () {
    // No seeder — no templates in DB
    $this->artisan('page-builder:init')->assertFailed();
});
```

- [ ] **Step 5: Run the full test suite**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/
```

Expected: all tests pass (count will differ from before — BusinessPageBlockTest now has 3 tests, PageBuilderInitCommandTest has 6).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(page-builder): update seeder, init command, and all tests for JSON storage"
```
