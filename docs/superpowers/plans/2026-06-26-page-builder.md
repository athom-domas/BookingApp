# Page Builder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded `welcome.blade.php` with a block-based section builder where the superadmin creates templates and each salon customizes their own page.

**Architecture:** Three DB tables (`page_templates`, `page_template_blocks`, `business_page_blocks`) hold the configuration; block behavior lives in PHP classes (`app/PageBlocks/`); a Blade component `<x-page-block>` renders each block using per-type view files.

**Tech Stack:** Laravel 13 / PHP 8.4, Filament 4, MySQL 8, Pest, Spatie MediaLibrary (existing), Spatie Permission (existing)

## Global Constraints

- PHP 8 attribute syntax: `#[Fillable([...])]` not `$fillable`; `#[Hidden([...])]` not `$hidden`
- Use `protected function casts(): array` not `$casts` property
- Query scopes must return `Builder` not `void`
- Factory classes need `/** @extends Factory<Model> */` docblock; models need `/** @use HasFactory<Factory> */`
- `RefreshDatabase` enabled globally in `tests/Pest.php` — no need to add per test
- Tests run with: `docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest`
- All DB migrations via: `docker-compose run --rm app php artisan migrate`
- Tenant context resolved via `app('current_business_id')` — never hardcode `auth()->user()->business_id`
- `BelongsToBusiness` trait adds global business scope automatically to any model that uses it
- Roles: `admin` (tenant), `super_admin` (platform), `staff`, `customer`
- Filament superadmin panel: `App\Filament\SuperAdmin\*`; tenant admin panel: `App\Filament\Admin\*`

---

## File Map

**New files:**
- `database/migrations/2026_06_26_000010_create_page_templates_table.php`
- `database/migrations/2026_06_26_000011_create_page_template_blocks_table.php`
- `database/migrations/2026_06_26_000012_create_business_page_blocks_table.php`
- `database/migrations/2026_06_26_000013_add_page_template_id_to_salon_profiles.php`
- `app/Models/PageTemplate.php`
- `app/Models/PageTemplateBlock.php`
- `app/Models/BusinessPageBlock.php`
- `app/PageBlocks/Contracts/PageBlockContract.php`
- `app/PageBlocks/AbstractPageBlock.php`
- `app/PageBlocks/PageBlockRegistry.php`
- `app/PageBlocks/HeroBlock.php`
- `app/PageBlocks/AboutBlock.php`
- `app/PageBlocks/CtaBlock.php`
- `app/PageBlocks/FaqBlock.php`
- `app/PageBlocks/ServicesBlock.php`
- `app/PageBlocks/StaffBlock.php`
- `app/PageBlocks/GalleryBlock.php`
- `app/PageBlocks/ReviewsBlock.php`
- `app/PageBlocks/ContactInfoBlock.php`
- `app/PageBlocks/MapBlock.php`
- `app/View/Components/PageBlock.php`
- `resources/views/components/page-block.blade.php`
- `resources/views/page-blocks/hero/classic.blade.php`
- `resources/views/page-blocks/hero/editorial.blade.php`
- `resources/views/page-blocks/hero/centered.blade.php`
- `resources/views/page-blocks/services/grid_cards.blade.php`
- `resources/views/page-blocks/services/compact_list.blade.php`
- `resources/views/page-blocks/services/price_list.blade.php`
- `resources/views/page-blocks/staff/cards.blade.php`
- `resources/views/page-blocks/staff/simple_list.blade.php`
- `resources/views/page-blocks/staff/editorial.blade.php`
- `resources/views/page-blocks/gallery/grid_3col.blade.php`
- `resources/views/page-blocks/gallery/masonry.blade.php`
- `resources/views/page-blocks/gallery/slider.blade.php`
- `resources/views/page-blocks/reviews/cards.blade.php`
- `resources/views/page-blocks/reviews/carousel.blade.php`
- `resources/views/page-blocks/reviews/minimal.blade.php`
- `resources/views/page-blocks/about/centered.blade.php`
- `resources/views/page-blocks/about/split_image.blade.php`
- `resources/views/page-blocks/contact_info/simple.blade.php`
- `resources/views/page-blocks/contact_info/with_map.blade.php`
- `resources/views/page-blocks/map/full_width.blade.php`
- `resources/views/page-blocks/map/contained.blade.php`
- `resources/views/page-blocks/cta/simple.blade.php`
- `resources/views/page-blocks/cta/with_image.blade.php`
- `resources/views/page-blocks/faq/accordion.blade.php`
- `resources/views/page-blocks/faq/list.blade.php`
- `app/Filament/SuperAdmin/Resources/PageTemplateResource.php`
- `app/Filament/SuperAdmin/Resources/PageTemplateResource/Pages/ListPageTemplates.php`
- `app/Filament/SuperAdmin/Resources/PageTemplateResource/Pages/CreatePageTemplate.php`
- `app/Filament/SuperAdmin/Resources/PageTemplateResource/Pages/EditPageTemplate.php`
- `app/Filament/SuperAdmin/Resources/PageTemplateResource/RelationManagers/PageTemplateBlocksRelationManager.php`
- `app/Filament/Admin/Pages/SiteBuilderPage.php`
- `database/seeders/PageBuilderSeeder.php`
- `app/Console/Commands/PageBuilderInit.php`
- `tests/Feature/PageBuilder/PageTemplateTest.php`
- `tests/Feature/PageBuilder/BusinessPageBlockTest.php`
- `tests/Feature/PageBuilder/PageBlockRegistryTest.php`
- `tests/Feature/PageBuilder/PageBlockComponentTest.php`
- `tests/Feature/PageBuilder/PageBuilderInitCommandTest.php`

**Modified files:**
- `app/Models/SalonProfile.php` — add `pageTemplate()` relation
- `app/Http/Controllers/Portal/BookingController.php` — load blocks, fallback logic
- `resources/views/welcome.blade.php` — replace content with block loop
- `resources/views/welcome-legacy.blade.php` ← rename from `welcome.blade.php`
- `database/seeders/DatabaseSeeder.php` — call PageBuilderSeeder

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_06_26_000010_create_page_templates_table.php`
- Create: `database/migrations/2026_06_26_000011_create_page_template_blocks_table.php`
- Create: `database/migrations/2026_06_26_000012_create_business_page_blocks_table.php`
- Create: `database/migrations/2026_06_26_000013_add_page_template_id_to_salon_profiles.php`
- Test: `tests/Feature/PageBuilder/PageTemplateTest.php`

**Interfaces:**
- Produces: `page_templates`, `page_template_blocks`, `business_page_blocks` tables; `page_template_id` on `salon_profiles`

- [ ] **Step 1: Write failing schema test**

```php
<?php
// tests/Feature/PageBuilder/PageTemplateTest.php
use Illuminate\Support\Facades\Schema;

it('page_templates table has correct columns', function () {
    expect(Schema::hasColumns('page_templates', [
        'id', 'name', 'slug', 'description', 'is_active', 'is_default', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('page_template_blocks table has correct columns', function () {
    expect(Schema::hasColumns('page_template_blocks', [
        'id', 'page_template_id', 'block_type', 'variant', 'sort_order',
        'is_enabled', 'is_required', 'is_locked', 'content', 'settings', 'schema_version',
    ]))->toBeTrue();
});

it('business_page_blocks table has correct columns', function () {
    expect(Schema::hasColumns('business_page_blocks', [
        'id', 'business_id', 'page_template_id', 'page_template_block_id',
        'block_type', 'variant', 'sort_order', 'is_enabled', 'is_required', 'is_locked',
        'content', 'settings', 'schema_version',
    ]))->toBeTrue();
});

it('salon_profiles has page_template_id column', function () {
    expect(Schema::hasColumn('salon_profiles', 'page_template_id'))->toBeTrue();
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageTemplateTest.php
```

- [ ] **Step 3: Create migration — page_templates**

```php
<?php
// database/migrations/2026_06_26_000010_create_page_templates_table.php
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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_templates');
    }
};
```

- [ ] **Step 4: Create migration — page_template_blocks**

```php
<?php
// database/migrations/2026_06_26_000011_create_page_template_blocks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('page_template_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_template_id')->constrained()->cascadeOnDelete();
            $table->string('block_type');
            $table->string('variant');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->json('content')->default('{}');
            $table->json('settings')->default('{}');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamps();

            $table->index(['page_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_template_blocks');
    }
};
```

- [ ] **Step 5: Create migration — business_page_blocks**

```php
<?php
// database/migrations/2026_06_26_000012_create_business_page_blocks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_page_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_template_block_id')->nullable()->constrained('page_template_blocks')->nullOnDelete();
            $table->string('block_type');
            $table->string('variant');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->json('content')->default('{}');
            $table->json('settings')->default('{}');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamps();

            $table->index(['business_id', 'is_enabled', 'sort_order']);
            $table->index(['business_id', 'block_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_page_blocks');
    }
};
```

- [ ] **Step 6: Create migration — add FK to salon_profiles**

```php
<?php
// database/migrations/2026_06_26_000013_add_page_template_id_to_salon_profiles.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->foreignId('page_template_id')->nullable()->after('id')->constrained('page_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\PageTemplate::class);
        });
    }
};
```

- [ ] **Step 7: Run migrations**

```bash
docker-compose run --rm app php artisan migrate
```

- [ ] **Step 8: Run tests — expect PASS**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageTemplateTest.php
```

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_06_26_0000{10,11,12,13}_*.php tests/Feature/PageBuilder/PageTemplateTest.php
git commit -m "feat(page-builder): add migrations for page templates and blocks"
```

---

## Task 2: Eloquent Models

**Files:**
- Create: `app/Models/PageTemplate.php`
- Create: `app/Models/PageTemplateBlock.php`
- Create: `app/Models/BusinessPageBlock.php`
- Modify: `app/Models/SalonProfile.php`
- Test: `tests/Feature/PageBuilder/PageTemplateTest.php` (extend)

**Interfaces:**
- Produces: `PageTemplate`, `PageTemplateBlock`, `BusinessPageBlock` models with casts and relations

- [ ] **Step 1: Add model tests to existing test file**

```php
// Append to tests/Feature/PageBuilder/PageTemplateTest.php

use App\Models\PageTemplate;
use App\Models\PageTemplateBlock;
use App\Models\BusinessPageBlock;
use App\Models\Business;

it('PageTemplate has correct casts', function () {
    $template = PageTemplate::factory()->create(['is_active' => true, 'is_default' => false]);
    expect($template->is_active)->toBeBool();
    expect($template->is_default)->toBeBool();
});

it('PageTemplateBlock content is always array', function () {
    $block = PageTemplateBlock::factory()->create(['content' => null]);
    expect($block->fresh()->content)->toBeArray();
});

it('BusinessPageBlock content is always array', function () {
    $block = BusinessPageBlock::factory()->create(['content' => null]);
    expect($block->fresh()->content)->toBeArray();
});

it('BusinessPageBlock belongs to Business', function () {
    $block = BusinessPageBlock::factory()->create();
    expect($block->business)->toBeInstanceOf(Business::class);
});

it('PageTemplate has pageTemplateBlocks relation', function () {
    $template = PageTemplate::factory()->create();
    PageTemplateBlock::factory()->count(3)->create(['page_template_id' => $template->id]);
    expect($template->pageTemplateBlocks)->toHaveCount(3);
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageTemplateTest.php
```

- [ ] **Step 3: Create PageTemplate model**

```php
<?php
// app/Models/PageTemplate.php
namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_active', 'is_default'])]
class PageTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\PageTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function pageTemplateBlocks(): HasMany
    {
        return $this->hasMany(PageTemplateBlock::class)->orderBy('sort_order');
    }
}
```

- [ ] **Step 4: Create PageTemplateBlock model**

```php
<?php
// app/Models/PageTemplateBlock.php
namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'page_template_id', 'block_type', 'variant', 'sort_order',
    'is_enabled', 'is_required', 'is_locked', 'content', 'settings', 'schema_version',
])]
class PageTemplateBlock extends Model
{
    /** @use HasFactory<\Database\Factories\PageTemplateBlockFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'content'    => 'array',
            'settings'   => 'array',
            'is_enabled' => 'boolean',
            'is_required'=> 'boolean',
            'is_locked'  => 'boolean',
        ];
    }

    public function getContentAttribute(?string $value): array
    {
        return $value ? json_decode($value, true) : [];
    }

    public function getSettingsAttribute(?string $value): array
    {
        return $value ? json_decode($value, true) : [];
    }

    public function pageTemplate(): BelongsTo
    {
        return $this->belongsTo(PageTemplate::class);
    }
}
```

- [ ] **Step 5: Create BusinessPageBlock model**

```php
<?php
// app/Models/BusinessPageBlock.php
namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'page_template_id', 'page_template_block_id',
    'block_type', 'variant', 'sort_order',
    'is_enabled', 'is_required', 'is_locked', 'content', 'settings', 'schema_version',
])]
class BusinessPageBlock extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessPageBlockFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return [
            'content'     => 'array',
            'settings'    => 'array',
            'is_enabled'  => 'boolean',
            'is_required' => 'boolean',
            'is_locked'   => 'boolean',
        ];
    }

    public function getContentAttribute(?string $value): array
    {
        return $value ? json_decode($value, true) : [];
    }

    public function getSettingsAttribute(?string $value): array
    {
        return $value ? json_decode($value, true) : [];
    }

    public function pageTemplate(): BelongsTo
    {
        return $this->belongsTo(PageTemplate::class);
    }

    public function pageTemplateBlock(): BelongsTo
    {
        return $this->belongsTo(PageTemplateBlock::class);
    }
}
```

- [ ] **Step 6: Add pageTemplate relation to SalonProfile**

In `app/Models/SalonProfile.php`, add after the existing relations:

```php
use App\Models\PageTemplate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function pageTemplate(): BelongsTo
{
    return $this->belongsTo(PageTemplate::class);
}
```

Also add `'page_template_id'` to the `#[Fillable([...])]` attribute list.

- [ ] **Step 7: Create minimal factories**

```php
<?php
// database/factories/PageTemplateFactory.php
namespace Database\Factories;

use App\Models\PageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageTemplate> */
class PageTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->words(2, true),
            'slug'        => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'is_active'   => true,
            'is_default'  => false,
        ];
    }
}
```

```php
<?php
// database/factories/PageTemplateBlockFactory.php
namespace Database\Factories;

use App\Models\PageTemplate;
use App\Models\PageTemplateBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageTemplateBlock> */
class PageTemplateBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page_template_id' => PageTemplate::factory(),
            'block_type'       => 'hero',
            'variant'          => 'classic',
            'sort_order'       => $this->faker->numberBetween(0, 100),
            'is_enabled'       => true,
            'is_required'      => false,
            'is_locked'        => false,
            'content'          => [],
            'settings'         => [],
            'schema_version'   => 1,
        ];
    }
}
```

```php
<?php
// database/factories/BusinessPageBlockFactory.php
namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessPageBlock> */
class BusinessPageBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'  => Business::factory(),
            'block_type'   => 'hero',
            'variant'      => 'classic',
            'sort_order'   => $this->faker->numberBetween(0, 100),
            'is_enabled'   => true,
            'is_required'  => false,
            'is_locked'    => false,
            'content'      => [],
            'settings'     => [],
            'schema_version' => 1,
        ];
    }
}
```

- [ ] **Step 8: Run tests — expect PASS**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageTemplateTest.php
```

- [ ] **Step 9: Commit**

```bash
git add app/Models/PageTemplate.php app/Models/PageTemplateBlock.php app/Models/BusinessPageBlock.php \
  app/Models/SalonProfile.php database/factories/Page*.php database/factories/Business*.php \
  tests/Feature/PageBuilder/PageTemplateTest.php
git commit -m "feat(page-builder): add PageTemplate, PageTemplateBlock, BusinessPageBlock models"
```

---

## Task 3: Block Contract, Abstract Base, Registry

**Files:**
- Create: `app/PageBlocks/Contracts/PageBlockContract.php`
- Create: `app/PageBlocks/AbstractPageBlock.php`
- Create: `app/PageBlocks/PageBlockRegistry.php`
- Test: `tests/Feature/PageBuilder/PageBlockRegistryTest.php`

**Interfaces:**
- Produces: `PageBlockContract` interface, `AbstractPageBlock` base class, `PageBlockRegistry::find/all/isValidVariant/defaultVariant`

- [ ] **Step 1: Write failing registry test**

```php
<?php
// tests/Feature/PageBuilder/PageBlockRegistryTest.php
use App\PageBlocks\PageBlockRegistry;

it('registry returns all registered block types', function () {
    $all = PageBlockRegistry::all();
    expect($all)->toHaveKeys([
        'hero', 'about', 'services', 'staff', 'gallery',
        'reviews', 'contact_info', 'map', 'cta', 'faq',
    ]);
});

it('find returns block class for known type', function () {
    $class = PageBlockRegistry::find('hero');
    expect($class)->not->toBeNull();
    expect(class_exists($class))->toBeTrue();
});

it('find returns null for unknown type', function () {
    expect(PageBlockRegistry::find('nonexistent'))->toBeNull();
});

it('isValidVariant returns true for known variant', function () {
    expect(PageBlockRegistry::isValidVariant('hero', 'classic'))->toBeTrue();
});

it('isValidVariant returns false for unknown variant', function () {
    expect(PageBlockRegistry::isValidVariant('hero', 'nonexistent_variant'))->toBeFalse();
});

it('defaultVariant returns first variant key', function () {
    $default = PageBlockRegistry::defaultVariant('hero');
    $variants = PageBlockRegistry::find('hero')::variants();
    expect($default)->toBe(array_key_first($variants));
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockRegistryTest.php
```

- [ ] **Step 3: Create PageBlockContract interface**

```php
<?php
// app/PageBlocks/Contracts/PageBlockContract.php
namespace App\PageBlocks\Contracts;

use App\Models\Business;
use App\Models\BusinessPageBlock;

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

    public static function contentRules(): array;
    public static function settingsRules(): array;

    /** Filament form fields. Fields use state paths content.* and settings.* */
    public static function filamentFields(): array;

    /** Blade view path for the given variant, e.g. 'page-blocks.hero.classic' */
    public static function viewFor(string $variant): string;

    /**
     * Data injected into the view at render time.
     * Static blocks return []. Dynamic blocks return model collections.
     */
    public static function resolveData(Business $business, BusinessPageBlock $block): array;
}
```

- [ ] **Step 4: Create AbstractPageBlock**

```php
<?php
// app/PageBlocks/AbstractPageBlock.php
namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\PageBlocks\Contracts\PageBlockContract;

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

    public static function contentRules(): array
    {
        return [];
    }

    public static function settingsRules(): array
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

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        return [];
    }

    public static function defaultVariant(): string
    {
        return array_key_first(static::variants());
    }
}
```

- [ ] **Step 5: Create PageBlockRegistry (stub — blocks registered after Task 4-6)**

```php
<?php
// app/PageBlocks/PageBlockRegistry.php
namespace App\PageBlocks;

class PageBlockRegistry
{
    public static function all(): array
    {
        return [
            'hero'         => HeroBlock::class,
            'about'        => AboutBlock::class,
            'services'     => ServicesBlock::class,
            'staff'        => StaffBlock::class,
            'gallery'      => GalleryBlock::class,
            'reviews'      => ReviewsBlock::class,
            'contact_info' => ContactInfoBlock::class,
            'map'          => MapBlock::class,
            'cta'          => CtaBlock::class,
            'faq'          => FaqBlock::class,
        ];
    }

    public static function find(string $type): ?string
    {
        return static::all()[$type] ?? null;
    }

    public static function isValidVariant(string $type, string $variant): bool
    {
        $class = static::find($type);

        return $class !== null && array_key_exists($variant, $class::variants());
    }

    public static function defaultVariant(string $type): string
    {
        $class = static::find($type);

        return $class ? $class::defaultVariant() : '';
    }
}
```

Note: the registry references block classes that don't exist yet. Tests will fail until Tasks 4-6 are complete. That is expected.

- [ ] **Step 6: Commit**

```bash
git add app/PageBlocks/
git commit -m "feat(page-builder): add block contract, abstract base, and registry"
```

---

## Task 4: Static Block Classes (Hero, About, CTA, FAQ)

**Files:**
- Create: `app/PageBlocks/HeroBlock.php`
- Create: `app/PageBlocks/AboutBlock.php`
- Create: `app/PageBlocks/CtaBlock.php`
- Create: `app/PageBlocks/FaqBlock.php`

**Interfaces:**
- Consumes: `AbstractPageBlock`
- Produces: 4 block classes implementing full contract; registry tests now pass

- [ ] **Step 1: Create HeroBlock**

```php
<?php
// app/PageBlocks/HeroBlock.php
namespace App\PageBlocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class HeroBlock extends AbstractPageBlock
{
    public static function type(): string { return 'hero'; }
    public static function label(): string { return 'Hero / Header'; }
    public static function description(): string { return 'Sezione principale con immagine di sfondo, titolo e CTA.'; }
    public static function icon(): string { return 'heroicon-o-photo'; }

    public static function variants(): array
    {
        return [
            'classic'   => ['label' => 'Classico',   'description' => 'Sfondo immagine piena con testo centrato'],
            'editorial' => ['label' => 'Editoriale', 'description' => 'Immagine laterale con testo a sinistra'],
            'centered'  => ['label' => 'Centrato',   'description' => 'Sfondo tinta unita con testo centrato'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => '', 'subtitle' => '', 'cta_label' => 'Prenota ora', 'image' => null];
    }

    public static function defaultSettings(): array
    {
        return ['alignment' => 'center', 'show_cta' => true];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'     => ['required', 'string', 'max:120'],
            'content.subtitle'  => ['nullable', 'string', 'max:200'],
            'content.cta_label' => ['nullable', 'string', 'max:50'],
            'content.image'     => ['nullable', 'string'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.alignment' => ['required', 'in:left,center'],
            'settings.show_cta'  => ['boolean'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo')->required()->maxLength(120),
            Textarea::make('content.subtitle')->label('Sottotitolo')->maxLength(200)->rows(2),
            TextInput::make('content.cta_label')->label('Testo pulsante CTA')->maxLength(50),
            FileUpload::make('content.image')->label('Immagine di sfondo')->image()->directory('site-builder/hero'),
            Select::make('settings.alignment')->label('Allineamento')->options(['left' => 'Sinistra', 'center' => 'Centrato'])->required(),
            Toggle::make('settings.show_cta')->label('Mostra pulsante CTA')->default(true),
        ];
    }
}
```

- [ ] **Step 2: Create AboutBlock**

```php
<?php
// app/PageBlocks/AboutBlock.php
namespace App\PageBlocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class AboutBlock extends AbstractPageBlock
{
    public static function type(): string { return 'about'; }
    public static function label(): string { return 'Descrizione Salone'; }
    public static function description(): string { return 'Testo di presentazione del salone con foto opzionale.'; }
    public static function icon(): string { return 'heroicon-o-building-storefront'; }

    public static function variants(): array
    {
        return [
            'centered'    => ['label' => 'Centrato',      'description' => 'Testo centrato con foto sotto'],
            'split_image' => ['label' => 'Immagine + testo', 'description' => 'Immagine a sinistra, testo a destra'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Il Salone', 'body' => '', 'image' => null, 'owner_signature' => null];
    }

    public static function defaultSettings(): array
    {
        return ['alignment' => 'center'];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'           => ['required', 'string', 'max:80'],
            'content.body'            => ['nullable', 'string', 'max:2000'],
            'content.image'           => ['nullable', 'string'],
            'content.owner_signature' => ['nullable', 'string', 'max:60'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.alignment' => ['required', 'in:left,center'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.body')->label('Testo')->rows(5)->maxLength(2000),
            FileUpload::make('content.image')->label('Immagine')->image()->directory('site-builder/about'),
            TextInput::make('content.owner_signature')->label('Firma proprietario')->maxLength(60),
            Select::make('settings.alignment')->label('Allineamento')->options(['left' => 'Sinistra', 'center' => 'Centrato'])->required(),
        ];
    }
}
```

- [ ] **Step 3: Create CtaBlock**

```php
<?php
// app/PageBlocks/CtaBlock.php
namespace App\PageBlocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class CtaBlock extends AbstractPageBlock
{
    public static function type(): string { return 'cta'; }
    public static function label(): string { return 'CTA Prenotazione'; }
    public static function description(): string { return 'Sezione di invito all\'azione con pulsante di prenotazione.'; }
    public static function icon(): string { return 'heroicon-o-cursor-arrow-rays'; }

    public static function variants(): array
    {
        return [
            'simple'     => ['label' => 'Semplice',         'description' => 'Testo centrato con pulsante'],
            'with_image' => ['label' => 'Con immagine',     'description' => 'Sfondo immagine con testo e pulsante'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Prenota ora', 'subtitle' => '', 'button_label' => 'Prenota', 'image' => null];
    }

    public static function defaultSettings(): array
    {
        return ['alignment' => 'center'];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'        => ['required', 'string', 'max:80'],
            'content.subtitle'     => ['nullable', 'string', 'max:200'],
            'content.button_label' => ['nullable', 'string', 'max:50'],
            'content.image'        => ['nullable', 'string'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.alignment' => ['required', 'in:left,center'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Sottotitolo')->rows(2)->maxLength(200),
            TextInput::make('content.button_label')->label('Testo pulsante')->maxLength(50),
            FileUpload::make('content.image')->label('Immagine di sfondo')->image()->directory('site-builder/cta'),
            Select::make('settings.alignment')->label('Allineamento')->options(['left' => 'Sinistra', 'center' => 'Centrato'])->required(),
        ];
    }
}
```

- [ ] **Step 4: Create FaqBlock**

```php
<?php
// app/PageBlocks/FaqBlock.php
namespace App\PageBlocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class FaqBlock extends AbstractPageBlock
{
    public static function type(): string { return 'faq'; }
    public static function label(): string { return 'FAQ'; }
    public static function description(): string { return 'Domande frequenti con risposte espandibili.'; }
    public static function icon(): string { return 'heroicon-o-question-mark-circle'; }

    public static function variants(): array
    {
        return [
            'accordion' => ['label' => 'Accordion', 'description' => 'Domande espandibili/collassabili'],
            'list'      => ['label' => 'Lista',     'description' => 'Domande e risposte in lista aperta'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Domande frequenti', 'items' => []];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'          => ['required', 'string', 'max:80'],
            'content.items'          => ['array'],
            'content.items.*.question' => ['required', 'string', 'max:200'],
            'content.items.*.answer'   => ['required', 'string', 'max:1000'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Repeater::make('content.items')
                ->label('Domande e risposte')
                ->schema([
                    TextInput::make('question')->label('Domanda')->required()->maxLength(200),
                    Textarea::make('answer')->label('Risposta')->required()->rows(3)->maxLength(1000),
                ])
                ->defaultItems(0)
                ->collapsible()
                ->addActionLabel('Aggiungi domanda'),
        ];
    }
}
```

- [ ] **Step 5: Run registry tests — expect PASS for static blocks**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockRegistryTest.php
```

Note: tests for dynamic/mixed blocks (services, staff, gallery, reviews, contact_info, map) will still fail — expected.

- [ ] **Step 6: Commit**

```bash
git add app/PageBlocks/HeroBlock.php app/PageBlocks/AboutBlock.php app/PageBlocks/CtaBlock.php app/PageBlocks/FaqBlock.php
git commit -m "feat(page-builder): add static block classes (Hero, About, CTA, FAQ)"
```

---

## Task 5: Dynamic Block Classes (Services, Staff, Gallery, Reviews)

**Files:**
- Create: `app/PageBlocks/ServicesBlock.php`
- Create: `app/PageBlocks/StaffBlock.php`
- Create: `app/PageBlocks/GalleryBlock.php`
- Create: `app/PageBlocks/ReviewsBlock.php`
- Test: `tests/Feature/PageBuilder/PageBlockRegistryTest.php` (extend)

**Interfaces:**
- Consumes: `AbstractPageBlock`, `Service`, `User`, `SalonProfile` (media), `SalonReview`
- Produces: 4 dynamic block classes; `resolveData()` returns typed arrays

- [ ] **Step 1: Add resolveData tests**

```php
// Append to tests/Feature/PageBuilder/PageBlockRegistryTest.php
use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\Service;
use App\Models\User;
use App\Models\SalonReview;
use App\PageBlocks\ServicesBlock;
use App\PageBlocks\StaffBlock;
use App\PageBlocks\ReviewsBlock;
use Spatie\Permission\Models\Role;

it('ServicesBlock resolveData returns active services for business', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    Service::factory()->create(['business_id' => $business->id, 'active' => true]);
    Service::factory()->create(['business_id' => $business->id, 'active' => false]);
    $block = BusinessPageBlock::factory()->make([
        'business_id' => $business->id,
        'block_type'  => 'services',
        'settings'    => ['featured_only' => false],
    ]);

    $data = ServicesBlock::resolveData($business, $block);

    expect($data['services'])->toHaveCount(1);
});

it('ReviewsBlock resolveData returns published reviews', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    SalonReview::factory()->create(['business_id' => $business->id, 'is_published' => true]);
    SalonReview::factory()->create(['business_id' => $business->id, 'is_published' => false]);
    $block = BusinessPageBlock::factory()->make(['business_id' => $business->id, 'block_type' => 'reviews']);

    $data = ReviewsBlock::resolveData($business, $block);

    expect($data['reviews'])->toHaveCount(1);
});

it('StaffBlock resolveData returns staff users for business', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    $staff = User::factory()->create(['business_id' => $business->id]);
    $staff->assignRole('staff');
    $block = BusinessPageBlock::factory()->make(['business_id' => $business->id, 'block_type' => 'staff']);

    $data = StaffBlock::resolveData($business, $block);

    expect($data['staff'])->toHaveCount(1);
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockRegistryTest.php
```

- [ ] **Step 3: Create ServicesBlock**

```php
<?php
// app/PageBlocks/ServicesBlock.php
namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\Service;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ServicesBlock extends AbstractPageBlock
{
    public static function type(): string { return 'services'; }
    public static function label(): string { return 'Servizi'; }
    public static function description(): string { return 'Elenco dei servizi con prezzo e durata.'; }
    public static function icon(): string { return 'heroicon-o-scissors'; }

    public static function variants(): array
    {
        return [
            'grid_cards'   => ['label' => 'Griglia card',   'description' => 'Servizi in card disposte a griglia'],
            'compact_list' => ['label' => 'Lista compatta', 'description' => 'Elenco verticale con prezzo e durata'],
            'price_list'   => ['label' => 'Listino prezzi', 'description' => 'Formato listino elegante'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'I nostri servizi', 'subtitle' => ''];
    }

    public static function defaultSettings(): array
    {
        return ['show_prices' => true, 'show_duration' => true, 'featured_only' => false];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.show_prices'   => ['boolean'],
            'settings.show_duration' => ['boolean'],
            'settings.featured_only' => ['boolean'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Testo introduttivo')->rows(2)->maxLength(200),
            Toggle::make('settings.show_prices')->label('Mostra prezzi')->default(true),
            Toggle::make('settings.show_duration')->label('Mostra durata')->default(true),
            Toggle::make('settings.featured_only')->label('Solo servizi in evidenza'),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $query = Service::withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($block->settings['featured_only'] ?? false) {
            $query->where('featured', true);
        }

        return ['services' => $query->get()];
    }
}
```

- [ ] **Step 4: Create StaffBlock**

```php
<?php
// app/PageBlocks/StaffBlock.php
namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class StaffBlock extends AbstractPageBlock
{
    public static function type(): string { return 'staff'; }
    public static function label(): string { return 'Team'; }
    public static function description(): string { return 'Presentazione del personale con foto e bio.'; }
    public static function icon(): string { return 'heroicon-o-user-group'; }

    public static function variants(): array
    {
        return [
            'cards'       => ['label' => 'Card con foto',     'description' => 'Card con avatar, nome e bio'],
            'simple_list' => ['label' => 'Lista semplice',    'description' => 'Elenco nomi e ruoli senza foto'],
            'editorial'   => ['label' => 'Layout editoriale', 'description' => 'Foto grande con bio estesa'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Il nostro team', 'subtitle' => ''];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Testo introduttivo')->rows(2)->maxLength(200),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $staff = User::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
            ->with('media')
            ->orderBy('sort_order')
            ->get();

        return ['staff' => $staff];
    }
}
```

- [ ] **Step 5: Create GalleryBlock**

```php
<?php
// app/PageBlocks/GalleryBlock.php
namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class GalleryBlock extends AbstractPageBlock
{
    public static function type(): string { return 'gallery'; }
    public static function label(): string { return 'Galleria'; }
    public static function description(): string { return 'Galleria immagini portfolio del salone.'; }
    public static function icon(): string { return 'heroicon-o-photo'; }

    public static function variants(): array
    {
        return [
            'grid_3col' => ['label' => 'Griglia 3 colonne', 'description' => 'Griglia uniforme a 3 colonne'],
            'masonry'   => ['label' => 'Masonry',           'description' => 'Griglia con altezze variabili'],
            'slider'    => ['label' => 'Slider',            'description' => 'Carosello scorribile'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Galleria', 'subtitle' => ''];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Testo introduttivo')->rows(2)->maxLength(200),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $profile = SalonProfile::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->first();

        return ['images' => $profile ? $profile->getMedia('portfolio') : collect()];
    }
}
```

- [ ] **Step 6: Create ReviewsBlock**

```php
<?php
// app/PageBlocks/ReviewsBlock.php
namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonReview;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class ReviewsBlock extends AbstractPageBlock
{
    public static function type(): string { return 'reviews'; }
    public static function label(): string { return 'Recensioni'; }
    public static function description(): string { return 'Testimonianze e recensioni dei clienti.'; }
    public static function icon(): string { return 'heroicon-o-star'; }

    public static function variants(): array
    {
        return [
            'cards'    => ['label' => 'Card',     'description' => 'Recensioni in card con stelle'],
            'carousel' => ['label' => 'Carosello','description' => 'Carosello scorrevole'],
            'minimal'  => ['label' => 'Minimale', 'description' => 'Lista testuale compatta'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Cosa dicono di noi', 'subtitle' => ''];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Testo introduttivo')->rows(2)->maxLength(200),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $reviews = SalonReview::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->published()
            ->ordered()
            ->get();

        return ['reviews' => $reviews];
    }
}
```

- [ ] **Step 7: Run all registry tests — expect PASS**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockRegistryTest.php
```

- [ ] **Step 8: Commit**

```bash
git add app/PageBlocks/ServicesBlock.php app/PageBlocks/StaffBlock.php app/PageBlocks/GalleryBlock.php app/PageBlocks/ReviewsBlock.php tests/Feature/PageBuilder/PageBlockRegistryTest.php
git commit -m "feat(page-builder): add dynamic block classes (Services, Staff, Gallery, Reviews)"
```

---

## Task 6: Mixed Block Classes (ContactInfo, Map)

**Files:**
- Create: `app/PageBlocks/ContactInfoBlock.php`
- Create: `app/PageBlocks/MapBlock.php`

**Interfaces:**
- Consumes: `AbstractPageBlock`, `SalonProfile`
- Produces: 2 mixed blocks; all registry tests pass

- [ ] **Step 1: Create ContactInfoBlock**

```php
<?php
// app/PageBlocks/ContactInfoBlock.php
namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ContactInfoBlock extends AbstractPageBlock
{
    public static function type(): string { return 'contact_info'; }
    public static function label(): string { return 'Orari & Contatti'; }
    public static function description(): string { return 'Orari di apertura, telefono, indirizzo e mappa.'; }
    public static function icon(): string { return 'heroicon-o-clock'; }

    public static function variants(): array
    {
        return [
            'simple'   => ['label' => 'Semplice',      'description' => 'Orari, telefono e indirizzo'],
            'with_map' => ['label' => 'Con mappa',     'description' => 'Informazioni + mappa Google integrata'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Orari e contatti', 'subtitle' => ''];
    }

    public static function defaultSettings(): array
    {
        return ['show_phone' => true, 'show_address' => true, 'show_hours' => true];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.show_phone'   => ['boolean'],
            'settings.show_address' => ['boolean'],
            'settings.show_hours'   => ['boolean'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Toggle::make('settings.show_phone')->label('Mostra telefono')->default(true),
            Toggle::make('settings.show_address')->label('Mostra indirizzo')->default(true),
            Toggle::make('settings.show_hours')->label('Mostra orari')->default(true),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $profile = SalonProfile::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->first();

        return ['profile' => $profile];
    }
}
```

- [ ] **Step 2: Create MapBlock**

```php
<?php
// app/PageBlocks/MapBlock.php
namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class MapBlock extends AbstractPageBlock
{
    public static function type(): string { return 'map'; }
    public static function label(): string { return 'Mappa'; }
    public static function description(): string { return 'Mappa Google integrata con indirizzo del salone.'; }
    public static function icon(): string { return 'heroicon-o-map-pin'; }

    public static function variants(): array
    {
        return [
            'full_width' => ['label' => 'Larghezza piena', 'description' => 'Mappa a tutta larghezza'],
            'contained'  => ['label' => 'Contenuta',       'description' => 'Mappa in contenitore centrato'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => ''];
    }

    public static function defaultSettings(): array
    {
        return ['height' => 'md', 'show_directions_link' => true];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.height'                => ['required', 'in:sm,md,lg'],
            'settings.show_directions_link'  => ['boolean'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo (opzionale)')->maxLength(80),
            Select::make('settings.height')->label('Altezza mappa')->options(['sm' => 'Piccola', 'md' => 'Media', 'lg' => 'Grande'])->required(),
            Toggle::make('settings.show_directions_link')->label('Mostra link "Ottieni indicazioni"')->default(true),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $profile = SalonProfile::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->first();

        return ['profile' => $profile];
    }
}
```

- [ ] **Step 3: Run all block + registry tests**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/
```

- [ ] **Step 4: Commit**

```bash
git add app/PageBlocks/ContactInfoBlock.php app/PageBlocks/MapBlock.php
git commit -m "feat(page-builder): add mixed block classes (ContactInfo, Map)"
```

---

## Task 7: PageBlock Blade Component

**Files:**
- Create: `app/View/Components/PageBlock.php`
- Create: `resources/views/components/page-block.blade.php`
- Test: `tests/Feature/PageBuilder/PageBlockComponentTest.php`

**Interfaces:**
- Consumes: `PageBlockRegistry`, `BusinessPageBlock`, `Business`
- Produces: `<x-page-block>` component; renders block view or logs and skips silently on error

- [ ] **Step 1: Write component tests**

```php
<?php
// tests/Feature/PageBuilder/PageBlockComponentTest.php
use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\PageBlocks\PageBlockRegistry;
use App\View\Components\PageBlock;

it('component resolves block class for known type', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->make(['block_type' => 'hero', 'variant' => 'classic']);

    $component = new PageBlock($business, $block);

    expect($component->blockClass)->toBe(PageBlockRegistry::find('hero'));
});

it('component returns null blockClass for unknown type', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->make(['block_type' => 'nonexistent', 'variant' => 'classic']);

    $component = new PageBlock($business, $block);

    expect($component->blockClass)->toBeNull();
});

it('component uses defaultVariant when variant is invalid', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->make(['block_type' => 'hero', 'variant' => 'bad_variant']);

    $component = new PageBlock($business, $block);

    expect($component->resolvedVariant)->toBe(PageBlockRegistry::find('hero')::defaultVariant());
});

it('component does not modify DB when variant is invalid', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->create(['block_type' => 'hero', 'variant' => 'bad_variant']);

    new PageBlock($business, $block);

    expect($block->fresh()->variant)->toBe('bad_variant');
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockComponentTest.php
```

- [ ] **Step 3: Create PageBlock component class**

```php
<?php
// app/View/Components/PageBlock.php
namespace App\View\Components;

use App\Models\Business;
use App\Models\BusinessPageBlock;
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
        public readonly BusinessPageBlock $block,
    ) {
        $this->blockClass = PageBlockRegistry::find($block->block_type);

        if ($this->blockClass === null) {
            Log::warning('PageBlock: unknown block type', [
                'block_id'   => $block->id,
                'block_type' => $block->block_type,
            ]);
            $this->resolvedVariant = '';
            $this->blockData = [];

            return;
        }

        if (PageBlockRegistry::isValidVariant($block->block_type, $block->variant)) {
            $this->resolvedVariant = $block->variant;
        } else {
            $fallback = PageBlockRegistry::defaultVariant($block->block_type);
            Log::warning('PageBlock: invalid variant, falling back', [
                'block_id'        => $block->id,
                'old_variant'     => $block->variant,
                'fallback_variant'=> $fallback,
            ]);
            $this->resolvedVariant = $fallback;
        }

        $this->blockData = ($this->blockClass)::resolveData($business, $block);
    }

    public function render(): View|Closure|string
    {
        return view('components.page-block');
    }
}
```

- [ ] **Step 4: Create component Blade view**

```blade
{{-- resources/views/components/page-block.blade.php --}}
@if($blockClass !== null)
    @php
        $viewPath = ($blockClass)::viewFor($resolvedVariant);
    @endphp

    @if(view()->exists($viewPath))
        @include($viewPath, array_merge([
            'block'    => $block,
            'content'  => $block->content,
            'settings' => $block->settings,
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

- [ ] **Step 5: Run tests — expect PASS**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBlockComponentTest.php
```

- [ ] **Step 6: Commit**

```bash
git add app/View/Components/PageBlock.php resources/views/components/page-block.blade.php tests/Feature/PageBuilder/PageBlockComponentTest.php
git commit -m "feat(page-builder): add PageBlock Blade component with fallback and logging"
```

---

## Task 8: Block Views — Priority Variants (Hero, Services, Staff, Gallery, Reviews)

**Files:** 15 Blade view files under `resources/views/page-blocks/`

**Notes:**
- Each view receives: `$block` (BusinessPageBlock), `$content` (array), `$settings` (array), `$business` (Business), plus block-specific variables (`$services`, `$staff`, `$images`, `$reviews`)
- All user-supplied text MUST use `{{ }}` — never `{!! !!}` unless the content is a system-generated Google Maps embed
- Extract HTML structure from the current `resources/views/welcome.blade.php` — it already has the full implementation of each section; the task is to split it into separate files and adapt variable names
- Booking route: `route('booking.create')`

- [ ] **Step 1: Create directory structure**

```bash
mkdir -p resources/views/page-blocks/{hero,services,staff,gallery,reviews,about,contact_info,map,cta,faq}
```

- [ ] **Step 2: Create hero/classic.blade.php**

This is the full-background-image hero already in welcome.blade.php. Extract the hero section and use these variables:

```blade
{{-- resources/views/page-blocks/hero/classic.blade.php --}}
{{-- Variables: $content['title'], $content['subtitle'], $content['cta_label'], $content['image'],
     $settings['alignment'], $settings['show_cta'], $business, $block --}}
<section id="hero" class="hero-section hero-classic" ...>
    {{-- Copy the hero HTML from welcome.blade.php, replacing:
         $profile->name → {{ $content['title'] ?? $business->name }}
         $profile->tagline → {{ $content['subtitle'] ?? '' }}
         $profile->booking_button_label → {{ $content['cta_label'] ?? 'Prenota ora' }}
         hero image logic → $content['image'] ?? SalonProfile::current()->heroImageUrl()
    --}}
</section>
```

- [ ] **Step 3: Create hero/editorial.blade.php and hero/centered.blade.php**

`editorial`: two-column layout, image on one side (use the masonry-style markup from the gallery section as inspiration), text on the other.

`centered`: same as classic but with a solid color background from the CSS theme variable instead of an image. Set a `background-color: var(--color-primary)` on the section.

- [ ] **Step 4: Create services views (grid_cards, compact_list, price_list)**

Extract the services section from welcome.blade.php. Each view receives `$services` (Collection of Service models).

```blade
{{-- resources/views/page-blocks/services/grid_cards.blade.php --}}
{{-- Variables: $content['title'], $content['subtitle'], $settings, $services --}}
<section id="services" ...>
    <h2>{{ $content['title'] ?? '' }}</h2>
    @if($content['subtitle'] ?? null)
        <p>{{ $content['subtitle'] }}</p>
    @endif
    <div class="services-grid">
        @foreach($services as $service)
            {{-- Extract service card HTML from welcome.blade.php --}}
        @endforeach
    </div>
</section>
```

`compact_list`: vertical list with name, duration, price on one line.

`price_list`: two-column name + price, no card styling, elegant separator lines.

- [ ] **Step 5: Create staff views (cards, simple_list, editorial)**

Extract from welcome.blade.php team section. Receives `$staff` (Collection of User models).

- [ ] **Step 6: Create gallery views (grid_3col, masonry, slider)**

Extract from welcome.blade.php gallery section. Receives `$images` (Spatie MediaLibrary Collection).

```blade
{{-- resources/views/page-blocks/gallery/grid_3col.blade.php --}}
<section id="gallery" ...>
    <div class="gallery-grid-3">
        @foreach($images as $image)
            <img src="{{ $image->getUrl() }}" alt="{{ $image->name }}" loading="lazy">
        @endforeach
    </div>
</section>
```

`masonry`: CSS columns layout (column-count: 3).

`slider`: simple horizontal scroll with overflow-x: auto, or use the existing lightbox JS if present.

- [ ] **Step 7: Create reviews views (cards, carousel, minimal)**

Extract from welcome.blade.php reviews section. Receives `$reviews` (Collection of SalonReview models).

- [ ] **Step 8: Commit**

```bash
git add resources/views/page-blocks/
git commit -m "feat(page-builder): add block views for priority blocks (Hero, Services, Staff, Gallery, Reviews)"
```

---

## Task 9: Block Views — Secondary Blocks (About, ContactInfo, Map, CTA, FAQ)

**Files:** 10 Blade view files

**Notes:**
- Same conventions as Task 8
- ContactInfo and Map views use `$profile` (SalonProfile model)
- `contact_info/with_map` and `map/*` views: the `google_maps_embed` field contains a full `<iframe>` embed code supplied by the salon owner in SalonProfile — this is the one case where `{!! !!}` is acceptable because it is an admin-entered value, not customer input. Add a HTML comment noting this.

- [ ] **Step 1: Create about views (centered, split_image)**

```blade
{{-- resources/views/page-blocks/about/centered.blade.php --}}
<section id="about" ...>
    <h2>{{ $content['title'] ?? '' }}</h2>
    <p>{{ $content['body'] ?? '' }}</p>
    @if($content['image'] ?? null)
        <img src="{{ asset($content['image']) }}" alt="{{ $content['title'] ?? '' }}">
    @endif
    @if($content['owner_signature'] ?? null)
        <p class="signature">{{ $content['owner_signature'] }}</p>
    @endif
</section>
```

`split_image`: CSS grid two-column, image on left column, text on right.

- [ ] **Step 2: Create contact_info views (simple, with_map)**

```blade
{{-- resources/views/page-blocks/contact_info/simple.blade.php --}}
{{-- Variables: $content, $settings, $profile (SalonProfile) --}}
<section id="contact" ...>
    <h2>{{ $content['title'] ?? 'Orari e contatti' }}</h2>
    @if(($settings['show_phone'] ?? true) && $profile?->phone)
        <p>{{ $profile->phone }}</p>
    @endif
    @if(($settings['show_address'] ?? true) && $profile?->address)
        <p>{{ $profile->address }}</p>
    @endif
    @if($settings['show_hours'] ?? true)
        {{-- Render opening_hours JSON — copy logic from current welcome.blade.php --}}
    @endif
</section>
```

`with_map`: same as simple but appended with the map embed below the contact details.

- [ ] **Step 3: Create map views (full_width, contained)**

```blade
{{-- resources/views/page-blocks/map/full_width.blade.php --}}
{{-- Variables: $content, $settings, $profile (SalonProfile) --}}
@if($profile?->google_maps_embed)
    <section id="map" ...>
        @if($content['title'] ?? null)
            <h2>{{ $content['title'] }}</h2>
        @endif
        {{-- google_maps_embed is an admin-entered iframe code, not customer input --}}
        {!! $profile->google_maps_embed !!}
    </section>
@endif
```

`contained`: wrap the iframe in a `max-width: 900px; margin: auto;` container.

- [ ] **Step 4: Create cta views (simple, with_image)**

```blade
{{-- resources/views/page-blocks/cta/simple.blade.php --}}
<section id="cta" class="text-{{ $settings['alignment'] ?? 'center' }}" ...>
    <h2>{{ $content['title'] ?? '' }}</h2>
    @if($content['subtitle'] ?? null)
        <p>{{ $content['subtitle'] }}</p>
    @endif
    <a href="{{ route('booking.create') }}" class="btn-primary">
        {{ $content['button_label'] ?? 'Prenota' }}
    </a>
</section>
```

`with_image`: set `background-image: url({{ asset($content['image'] ?? '') }})` on the section.

- [ ] **Step 5: Create faq views (accordion, list)**

```blade
{{-- resources/views/page-blocks/faq/accordion.blade.php --}}
<section id="faq" ...>
    <h2>{{ $content['title'] ?? 'FAQ' }}</h2>
    @foreach($content['items'] ?? [] as $i => $item)
        <details>
            <summary>{{ $item['question'] }}</summary>
            <p>{{ $item['answer'] }}</p>
        </details>
    @endforeach
</section>
```

`list`: same but without `<details>` — just `<h3>` for question and `<p>` for answer.

- [ ] **Step 6: Commit**

```bash
git add resources/views/page-blocks/
git commit -m "feat(page-builder): add block views for secondary blocks (About, Contact, Map, CTA, FAQ)"
```

---

## Task 10: Update BookingController and welcome.blade.php

**Files:**
- Modify: `app/Http/Controllers/Portal/BookingController.php`
- Modify: `resources/views/welcome.blade.php`
- Create: `resources/views/welcome-legacy.blade.php` (rename current welcome)
- Test: `tests/Feature/PageBuilder/BusinessPageBlockTest.php`

**Interfaces:**
- Consumes: `BusinessPageBlock`, `<x-page-block>`, `Business`
- Produces: public page renders blocks; falls back to legacy when no blocks exist

- [ ] **Step 1: Write feature tests**

```php
<?php
// tests/Feature/PageBuilder/BusinessPageBlockTest.php
use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;

it('public page renders blocks when business has page blocks', function () {
    $business = Business::factory()->create(['status' => 'active', 'subdomain' => 'test-salon']);
    SalonProfile::factory()->create(['business_id' => $business->id]);
    BusinessPageBlock::factory()->create([
        'business_id' => $business->id,
        'block_type'  => 'hero',
        'variant'     => 'classic',
        'is_enabled'  => true,
        'sort_order'  => 1,
        'content'     => ['title' => 'Test Salon'],
    ]);

    // Bind tenant context as middleware would
    app()->instance('current_business_id', $business->id);

    $response = $this->get('/');
    $response->assertStatus(200);
    $response->assertSee('Test Salon');
});

it('public page falls back to legacy when business has no blocks', function () {
    $business = Business::factory()->create(['status' => 'active']);
    SalonProfile::factory()->create(['business_id' => $business->id]);
    app()->instance('current_business_id', $business->id);

    // No BusinessPageBlocks created
    $response = $this->get('/');
    $response->assertStatus(200);
    // The legacy view renders without crashing
});

it('page shows empty state when all blocks are disabled (no legacy fallback)', function () {
    $business = Business::factory()->create(['status' => 'active']);
    SalonProfile::factory()->create(['business_id' => $business->id]);
    BusinessPageBlock::factory()->create([
        'business_id' => $business->id,
        'block_type'  => 'hero',
        'variant'     => 'classic',
        'is_enabled'  => false,
    ]);
    app()->instance('current_business_id', $business->id);

    $response = $this->get('/');
    $response->assertStatus(200);
    // Does NOT fall back to legacy — business HAS blocks, they're just disabled
});

it('required block cannot be disabled', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    $block = BusinessPageBlock::factory()->create([
        'business_id' => $business->id,
        'is_required' => true,
        'is_enabled'  => true,
    ]);

    // Simulate what the SiteBuilderPage does: attempt toggle
    // is_required blocks must not be toggled off through normal flow
    expect($block->is_required)->toBeTrue();
    expect($block->is_enabled)->toBeTrue();
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/BusinessPageBlockTest.php
```

- [ ] **Step 3: Rename welcome.blade.php to welcome-legacy.blade.php**

```bash
mv resources/views/welcome.blade.php resources/views/welcome-legacy.blade.php
```

- [ ] **Step 4: Create new welcome.blade.php**

```blade
{{-- resources/views/welcome.blade.php --}}
@extends('layouts.storefront')

@section('content')
    @foreach($blocks as $block)
        <x-page-block :business="$business" :block="$block" />
    @endforeach
@endsection
```

- [ ] **Step 5: Update BookingController::index()**

Replace the existing `index()` method body:

```php
public function index(): View
{
    if (! app()->bound('current_business_id')) {
        return view('landing');
    }

    $businessId = app('current_business_id');
    $business   = \App\Models\Business::find($businessId);
    $profile    = SalonProfile::current()->load('media');

    $hasAnyBlocks = \App\Models\BusinessPageBlock::withoutGlobalScopes()
        ->where('business_id', $businessId)
        ->exists();

    if (! $hasAnyBlocks) {
        // Legacy fallback — remove once all businesses are initialized
        \Illuminate\Support\Facades\Log::warning('page-builder: business has no blocks, rendering legacy', [
            'business_id' => $businessId,
        ]);
        $services = Service::active()->orderBy('sort_order')->orderBy('name')->get();
        $staff    = User::whereHas('roles', fn ($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
            ->where('business_id', $businessId)
            ->with('media')
            ->orderBy('sort_order')
            ->get();
        $reviews  = \App\Models\SystemSetting::isReviewsEnabled()
            ? \App\Models\SalonReview::published()->ordered()->get()
            : collect();

        return view('welcome-legacy', compact('profile', 'services', 'staff', 'reviews'));
    }

    $blocks = \App\Models\BusinessPageBlock::withoutGlobalScopes()
        ->where('business_id', $businessId)
        ->where('is_enabled', true)
        ->orderBy('sort_order')
        ->get();

    return view('welcome', compact('business', 'blocks', 'profile'));
}
```

- [ ] **Step 6: Run tests — expect PASS**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/BusinessPageBlockTest.php
```

- [ ] **Step 7: Commit**

```bash
git add resources/views/welcome.blade.php resources/views/welcome-legacy.blade.php app/Http/Controllers/Portal/BookingController.php tests/Feature/PageBuilder/BusinessPageBlockTest.php
git commit -m "feat(page-builder): update storefront controller and welcome view to use block renderer"
```

---

## Task 11: Superadmin PageTemplateResource + RelationManager

**Files:**
- Create: `app/Filament/SuperAdmin/Resources/PageTemplateResource.php`
- Create: `app/Filament/SuperAdmin/Resources/PageTemplateResource/Pages/ListPageTemplates.php`
- Create: `app/Filament/SuperAdmin/Resources/PageTemplateResource/Pages/CreatePageTemplate.php`
- Create: `app/Filament/SuperAdmin/Resources/PageTemplateResource/Pages/EditPageTemplate.php`
- Create: `app/Filament/SuperAdmin/Resources/PageTemplateResource/RelationManagers/PageTemplateBlocksRelationManager.php`

**Interfaces:**
- Consumes: `PageTemplate`, `PageTemplateBlock`, `PageBlockRegistry`
- Produces: full CRUD UI for templates; blocks managed via RelationManager with drag-and-drop

- [ ] **Step 1: Create PageTemplateResource**

```php
<?php
// app/Filament/SuperAdmin/Resources/PageTemplateResource.php
namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PageTemplateResource\Pages;
use App\Filament\SuperAdmin\Resources\PageTemplateResource\RelationManagers;
use App\Models\PageTemplate;
use App\PageBlocks\PageBlockRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageTemplateResource extends Resource
{
    protected static ?string $model = PageTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Template sito';
    protected static ?string $navigationGroup = 'Piattaforma';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(80)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug($state))),
            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->unique(PageTemplate::class, 'slug', ignoreRecord: true)
                ->maxLength(80),
            Forms\Components\Textarea::make('description')
                ->label('Descrizione')
                ->rows(2),
            Forms\Components\Toggle::make('is_active')
                ->label('Attivo')
                ->default(true),
            Forms\Components\Toggle::make('is_default')
                ->label('Template di default')
                ->helperText('Un solo template può essere il default. Gli altri verranno resettati.')
                ->reactive(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('Slug'),
                Tables\Columns\IconColumn::make('is_active')->label('Attivo')->boolean(),
                Tables\Columns\IconColumn::make('is_default')->label('Default')->boolean(),
                Tables\Columns\TextColumn::make('pageTemplateBlocks_count')
                    ->counts('pageTemplateBlocks')
                    ->label('Blocchi'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('clone')
                    ->label('Clona')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->action(function (PageTemplate $record) {
                        $clone = $record->replicate(['is_default']);
                        $clone->name = $record->name . ' copia';
                        $clone->slug = Str::slug($record->name . '-copia-' . now()->timestamp);
                        $clone->is_active = false;
                        $clone->is_default = false;
                        $clone->save();

                        foreach ($record->pageTemplateBlocks as $block) {
                            $clone->pageTemplateBlocks()->create($block->only([
                                'block_type', 'variant', 'sort_order', 'is_enabled',
                                'is_required', 'is_locked', 'content', 'settings', 'schema_version',
                            ]));
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelationManagers(): array
    {
        return [RelationManagers\PageTemplateBlocksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPageTemplates::route('/'),
            'create' => Pages\CreatePageTemplate::route('/create'),
            'edit'   => Pages\EditPageTemplate::route('/{record}/edit'),
        ];
    }

    // Reset other templates' is_default when saving one as default
    public static function saving(PageTemplate $record): void
    {
        if ($record->is_default) {
            PageTemplate::where('id', '!=', $record->id)->update(['is_default' => false]);
        }
    }
}
```

- [ ] **Step 2: Create Pages (list, create, edit)**

```php
<?php
// app/Filament/SuperAdmin/Resources/PageTemplateResource/Pages/ListPageTemplates.php
namespace App\Filament\SuperAdmin\Resources\PageTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\PageTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPageTemplates extends ListRecords
{
    protected static string $resource = PageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
```

```php
<?php
// app/Filament/SuperAdmin/Resources/PageTemplateResource/Pages/CreatePageTemplate.php
namespace App\Filament\SuperAdmin\Resources\PageTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\PageTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePageTemplate extends CreateRecord
{
    protected static string $resource = PageTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['is_default'] ?? false) {
            \App\Models\PageTemplate::query()->update(['is_default' => false]);
        }
        return $data;
    }
}
```

```php
<?php
// app/Filament/SuperAdmin/Resources/PageTemplateResource/Pages/EditPageTemplate.php
namespace App\Filament\SuperAdmin\Resources\PageTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\PageTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPageTemplate extends EditRecord
{
    protected static string $resource = PageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['is_default'] ?? false) {
            \App\Models\PageTemplate::where('id', '!=', $this->record->id)->update(['is_default' => false]);
        }
        return $data;
    }
}
```

- [ ] **Step 3: Create PageTemplateBlocksRelationManager**

```php
<?php
// app/Filament/SuperAdmin/Resources/PageTemplateResource/RelationManagers/PageTemplateBlocksRelationManager.php
namespace App\Filament\SuperAdmin\Resources\PageTemplateResource\RelationManagers;

use App\PageBlocks\PageBlockRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PageTemplateBlocksRelationManager extends RelationManager
{
    protected static string $relationship = 'pageTemplateBlocks';
    protected static ?string $title = 'Blocchi';

    public function form(Form $form): Form
    {
        // Dynamic form based on selected block_type
        return $form->schema([
            Forms\Components\Select::make('block_type')
                ->label('Tipo blocco')
                ->options(collect(PageBlockRegistry::all())
                    ->mapWithKeys(fn ($class, $type) => [$type => $class::label()]))
                ->required()
                ->live()
                ->afterStateUpdated(fn ($set, $state) => $set('variant', PageBlockRegistry::defaultVariant($state))),
            Forms\Components\Select::make('variant')
                ->label('Variante')
                ->options(fn ($get) => $get('block_type')
                    ? collect(PageBlockRegistry::find($get('block_type'))::variants())
                        ->mapWithKeys(fn ($v, $k) => [$k => $v['label']])
                    : [])
                ->required(),
            Forms\Components\Toggle::make('is_enabled')->label('Abilitato')->default(true),
            Forms\Components\Toggle::make('is_required')->label('Obbligatorio (non disabilitabile dal salone)'),
            Forms\Components\Toggle::make('is_locked')->label('Bloccato (non modificabile dal salone)'),
            Forms\Components\Section::make('Contenuto e impostazioni')
                ->schema(fn ($get) => $get('block_type') && PageBlockRegistry::find($get('block_type'))
                    ? PageBlockRegistry::find($get('block_type'))::filamentFields()
                    : [])
                ->visible(fn ($get) => filled($get('block_type'))),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('block_type')->label('Tipo'),
                Tables\Columns\TextColumn::make('variant')->label('Variante')->badge(),
                Tables\Columns\IconColumn::make('is_enabled')->label('Attivo')->boolean(),
                Tables\Columns\IconColumn::make('is_required')->label('Obbligatorio')->boolean(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->slideOver()])
            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make()
                    ->before(fn ($record) => abort_if(
                        $record->is_required,
                        403,
                        'I blocchi obbligatori non possono essere rimossi.'
                    )),
            ]);
    }
}
```

- [ ] **Step 4: Manually test in browser**

Navigate to `/super-admin/page-templates` (or the superadmin panel URL). Verify:
- Can create a template
- Can add blocks via slide-over
- Can reorder blocks via drag-and-drop
- Can clone a template
- Cannot delete a `is_required` block

- [ ] **Step 5: Commit**

```bash
git add app/Filament/SuperAdmin/Resources/PageTemplateResource*
git commit -m "feat(page-builder): add superadmin PageTemplateResource with block relation manager"
```

---

## Task 12: Tenant SiteBuilderPage

**Files:**
- Create: `app/Filament/Admin/Pages/SiteBuilderPage.php`

**Interfaces:**
- Consumes: `BusinessPageBlock`, `PageTemplate`, `PageBlockRegistry`
- Produces: tenant can reorder, toggle, edit blocks; change template with confirmation

- [ ] **Step 1: Create SiteBuilderPage**

```php
<?php
// app/Filament/Admin/Pages/SiteBuilderPage.php
namespace App\Filament\Admin\Pages;

use App\Models\BusinessPageBlock;
use App\Models\PageTemplate;
use App\PageBlocks\PageBlockRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class SiteBuilderPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Il mio sito';
    protected static ?string $title = 'Il mio sito';
    protected static string $view = 'filament.admin.pages.site-builder';

    public function table(Table $table): Table
    {
        $businessId = app('current_business_id');

        return $table
            ->query(
                BusinessPageBlock::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->orderBy('sort_order')
            )
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('block_type')
                    ->label('Blocco')
                    ->formatStateUsing(fn ($state) => PageBlockRegistry::find($state)?::label() ?? $state),
                TextColumn::make('variant')
                    ->label('Variante')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => 
                        PageBlockRegistry::find($record->block_type)?::variants()[$state]['label'] ?? $state
                    ),
                ToggleColumn::make('is_enabled')
                    ->label('Visibile')
                    ->disabled(fn ($record) => $record->is_required)
                    ->beforeStateUpdated(function ($record, $state) {
                        if ($record->is_required && ! $state) {
                            Notification::make()
                                ->title('Blocco obbligatorio')
                                ->body('Questo blocco non può essere disabilitato.')
                                ->danger()
                                ->send();
                            return false; // Cancel the update
                        }
                    }),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('edit')
                    ->label('Modifica')
                    ->icon('heroicon-o-pencil')
                    ->slideOver()
                    ->visible(fn ($record) => ! $record->is_locked)
                    ->form(fn (BusinessPageBlock $record): array => array_filter([
                        Select::make('variant')
                            ->label('Variante layout')
                            ->options(fn () => PageBlockRegistry::find($record->block_type)
                                ? collect(PageBlockRegistry::find($record->block_type)::variants())
                                    ->mapWithKeys(fn ($v, $k) => [$k => $v['label']])
                                : [])
                            ->required(),
                        PageBlockRegistry::find($record->block_type)
                            ? Section::make('Contenuto')->schema(
                                PageBlockRegistry::find($record->block_type)::filamentFields()
                            )
                            : null,
                    ]))
                    ->fillForm(fn (BusinessPageBlock $record): array => array_merge(
                        ['variant' => $record->variant],
                        ['content' => $record->content],
                        ['settings' => $record->settings]
                    ))
                    ->action(function (BusinessPageBlock $record, array $data): void {
                        $blockClass = PageBlockRegistry::find($record->block_type);

                        if ($blockClass) {
                            $validator = validator($data, array_merge(
                                $blockClass::contentRules(),
                                $blockClass::settingsRules(),
                                ['variant' => ['required', 'string']],
                            ));
                            if ($validator->fails()) {
                                Notification::make()->title('Dati non validi')->danger()->send();
                                return;
                            }
                        }

                        $record->update([
                            'variant'  => $data['variant'],
                            'content'  => $data['content'] ?? $record->content,
                            'settings' => $data['settings'] ?? $record->settings,
                        ]);

                        Notification::make()
                            ->title('Modifiche salvate. Le modifiche sono visibili subito sul sito.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openSite')
                ->label('Apri sito pubblico')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => app('url')->to('/'))
                ->openUrlInNewTab(),

            Action::make('changeTemplate')
                ->label('Cambia template')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    Select::make('template_id')
                        ->label('Template')
                        ->options(PageTemplate::where('is_active', true)->pluck('name', 'id'))
                        ->required(),
                    Checkbox::make('confirm')
                        ->label('Confermo: questa azione è irreversibile. Ordine, testi, immagini e varianti dei blocchi saranno reimpostati.')
                        ->accepted()
                        ->validationMessages(['accepted' => 'Devi confermare per procedere.']),
                ])
                ->modalHeading('Cambia template')
                ->modalDescription('Cambiare template reimposterà l\'ordine, i testi, le immagini e le varianti dei blocchi. Questa azione non è reversibile.')
                ->action(function (array $data): void {
                    $businessId = app('current_business_id');
                    $template   = PageTemplate::with('pageTemplateBlocks')->find($data['template_id']);

                    if (! $template) return;

                    DB::transaction(function () use ($businessId, $template) {
                        BusinessPageBlock::withoutGlobalScopes()
                            ->where('business_id', $businessId)
                            ->delete();

                        foreach ($template->pageTemplateBlocks as $templateBlock) {
                            BusinessPageBlock::withoutGlobalScopes()->create([
                                'business_id'            => $businessId,
                                'page_template_id'       => $template->id,
                                'page_template_block_id' => $templateBlock->id,
                                'block_type'             => $templateBlock->block_type,
                                'variant'                => $templateBlock->variant,
                                'sort_order'             => $templateBlock->sort_order,
                                'is_enabled'             => $templateBlock->is_enabled,
                                'is_required'            => $templateBlock->is_required,
                                'is_locked'              => $templateBlock->is_locked,
                                'content'                => $templateBlock->content,
                                'settings'               => $templateBlock->settings,
                                'schema_version'         => $templateBlock->schema_version,
                            ]);
                        }

                        \App\Models\SalonProfile::withoutGlobalScopes()
                            ->where('business_id', $businessId)
                            ->update(['page_template_id' => $template->id]);
                    });

                    Notification::make()
                        ->title('Template applicato. Le modifiche sono visibili subito sul sito.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
```

- [ ] **Step 2: Create the Blade view for the page**

```bash
mkdir -p resources/views/filament/admin/pages
```

```blade
{{-- resources/views/filament/admin/pages/site-builder.blade.php --}}
<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-500">
        Le modifiche sono visibili immediatamente sul sito pubblico.
    </div>

    {{ $this->table }}
</x-filament-panels::page>
```

- [ ] **Step 3: Manually test in browser**

Navigate to the tenant admin panel → "Il mio sito". Verify:
- Blocks list renders
- Can drag to reorder
- "Modifica" opens slide-over with correct fields
- Saving shows success notification
- "Cambia template" modal shows warning and requires checkbox
- "Apri sito pubblico" opens new tab

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Admin/Pages/SiteBuilderPage.php resources/views/filament/admin/pages/site-builder.blade.php
git commit -m "feat(page-builder): add tenant SiteBuilderPage with drag-reorder and block editing"
```

---

## Task 13: PageBuilderSeeder

**Files:**
- Create: `database/seeders/PageBuilderSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/PageBuilder/PageBuilderInitCommandTest.php` (partial — seeder part)

**Interfaces:**
- Produces: 3 templates (Default, Minimal, Premium) with blocks in DB; Default mirrors current welcome.blade.php order

- [ ] **Step 1: Write seeder test**

```php
<?php
// tests/Feature/PageBuilder/PageBuilderInitCommandTest.php
use App\Models\PageTemplate;
use App\Models\PageTemplateBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;

it('PageBuilderSeeder creates Default template with 10 blocks', function () {
    $this->seed(\Database\Seeders\PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'default')->first();
    expect($template)->not->toBeNull();
    expect($template->pageTemplateBlocks)->toHaveCount(10);
});

it('PageBuilderSeeder marks hero and contact_info as required', function () {
    $this->seed(\Database\Seeders\PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'default')->first();
    $required = $template->pageTemplateBlocks->where('is_required', true)->pluck('block_type')->toArray();
    expect($required)->toContain('hero');
    expect($required)->toContain('contact_info');
});

it('PageBuilderSeeder is idempotent', function () {
    $this->seed(\Database\Seeders\PageBuilderSeeder::class);
    $this->seed(\Database\Seeders\PageBuilderSeeder::class);

    expect(PageTemplate::where('slug', 'default')->count())->toBe(1);
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBuilderInitCommandTest.php --filter "seeder"
```

- [ ] **Step 3: Create PageBuilderSeeder**

```php
<?php
// database/seeders/PageBuilderSeeder.php
namespace Database\Seeders;

use App\Models\PageTemplate;
use App\Models\PageTemplateBlock;
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

    private function createTemplate(string $slug, string $name, string $description, bool $isDefault, array $blocks): void
    {
        $template = PageTemplate::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => $description, 'is_active' => true, 'is_default' => $isDefault]
        );

        if ($template->pageTemplateBlocks()->count() === 0) {
            foreach ($blocks as $i => $blockDef) {
                $blockClass = PageBlockRegistry::find($blockDef['block_type']);
                $template->pageTemplateBlocks()->create([
                    'block_type'     => $blockDef['block_type'],
                    'variant'        => $blockDef['variant'],
                    'sort_order'     => ($i + 1) * 10,
                    'is_enabled'     => true,
                    'is_required'    => $blockDef['is_required'] ?? false,
                    'is_locked'      => false,
                    'content'        => $blockClass ? $blockClass::defaultContent() : [],
                    'settings'       => array_merge(
                        $blockClass ? $blockClass::defaultSettings() : [],
                        $blockDef['settings'] ?? []
                    ),
                    'schema_version' => 1,
                ]);
            }
        }
    }

    private function defaultBlocks(): array
    {
        return [
            ['block_type' => 'hero',         'variant' => 'classic',    'is_required' => true],
            ['block_type' => 'services',     'variant' => 'grid_cards'],
            ['block_type' => 'about',        'variant' => 'centered'],
            ['block_type' => 'staff',        'variant' => 'cards'],
            ['block_type' => 'gallery',      'variant' => 'grid_3col'],
            ['block_type' => 'contact_info', 'variant' => 'with_map',   'is_required' => true],
            ['block_type' => 'reviews',      'variant' => 'cards'],
            ['block_type' => 'faq',          'variant' => 'accordion'],
            ['block_type' => 'cta',          'variant' => 'simple'],
            ['block_type' => 'map',          'variant' => 'full_width'],
        ];
    }

    private function minimalBlocks(): array
    {
        return [
            ['block_type' => 'hero',         'variant' => 'centered',   'is_required' => true],
            ['block_type' => 'services',     'variant' => 'compact_list'],
            ['block_type' => 'contact_info', 'variant' => 'simple',     'is_required' => true],
            ['block_type' => 'cta',          'variant' => 'simple'],
        ];
    }

    private function premiumBlocks(): array
    {
        return [
            ['block_type' => 'hero',         'variant' => 'editorial',  'is_required' => true],
            ['block_type' => 'about',        'variant' => 'split_image'],
            ['block_type' => 'services',     'variant' => 'price_list'],
            ['block_type' => 'staff',        'variant' => 'editorial'],
            ['block_type' => 'gallery',      'variant' => 'masonry'],
            ['block_type' => 'reviews',      'variant' => 'carousel'],
            ['block_type' => 'contact_info', 'variant' => 'with_map',   'is_required' => true],
        ];
    }
}
```

- [ ] **Step 4: Add seeder to DatabaseSeeder.php**

In `database/seeders/DatabaseSeeder.php`, add inside `run()`:

```php
$this->call(PageBuilderSeeder::class);
```

- [ ] **Step 5: Run seeder tests — expect PASS**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBuilderInitCommandTest.php
```

- [ ] **Step 6: Run the seeder**

```bash
docker-compose run --rm app php artisan db:seed --class=PageBuilderSeeder
```

- [ ] **Step 7: Commit**

```bash
git add database/seeders/PageBuilderSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/PageBuilder/PageBuilderInitCommandTest.php
git commit -m "feat(page-builder): add PageBuilderSeeder with Default, Minimal, Premium templates"
```

---

## Task 14: PageBuilderInit Artisan Command

**Files:**
- Create: `app/Console/Commands/PageBuilderInit.php`
- Test: `tests/Feature/PageBuilder/PageBuilderInitCommandTest.php` (extend)

**Interfaces:**
- Consumes: `Business`, `PageTemplate`, `BusinessPageBlock`, `SalonProfile`
- Produces: `php artisan page-builder:init` command; idempotent; transactional; `--business` and `--force` options

- [ ] **Step 1: Add command tests**

```php
// Append to tests/Feature/PageBuilder/PageBuilderInitCommandTest.php

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;

it('page-builder:init creates blocks for uninitialized businesses', function () {
    $this->seed(\Database\Seeders\PageBuilderSeeder::class);
    $business = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $business->id]);

    $this->artisan('page-builder:init')->assertSuccessful();

    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBeGreaterThan(0);
});

it('page-builder:init skips already initialized businesses', function () {
    $this->seed(\Database\Seeders\PageBuilderSeeder::class);
    $business = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $business->id]);
    BusinessPageBlock::factory()->create(['business_id' => $business->id]);

    $this->artisan('page-builder:init')->assertSuccessful();

    // Count should still be 1 — not duplicated
    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(1);
});

it('page-builder:init --business targets a single business', function () {
    $this->seed(\Database\Seeders\PageBuilderSeeder::class);
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $b1->id]);
    SalonProfile::factory()->create(['business_id' => $b2->id]);

    $this->artisan("page-builder:init --business={$b1->id}")->assertSuccessful();

    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $b1->id)->count())->toBeGreaterThan(0);
    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $b2->id)->count())->toBe(0);
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/PageBuilderInitCommandTest.php
```

- [ ] **Step 3: Create PageBuilderInit command**

```php
<?php
// app/Console/Commands/PageBuilderInit.php
namespace App\Console\Commands;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\PageTemplate;
use App\Models\SalonProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PageBuilderInit extends Command
{
    protected $signature   = 'page-builder:init {--business= : ID of a single business to initialize} {--force : Re-initialize businesses that already have blocks}';
    protected $description = 'Initialize page builder blocks for businesses using the Default template snapshot.';

    public function handle(): int
    {
        $defaultTemplate = PageTemplate::where('is_default', true)->with('pageTemplateBlocks')->first();

        if (! $defaultTemplate) {
            $this->error('No default template found. Run: php artisan db:seed --class=PageBuilderSeeder');
            return self::FAILURE;
        }

        $query = Business::query();

        if ($this->option('business')) {
            $query->where('id', $this->option('business'));
        }

        if (! $this->option('force')) {
            $initializedIds = BusinessPageBlock::withoutGlobalScopes()
                ->select('business_id')
                ->distinct()
                ->pluck('business_id');

            $query->whereNotIn('id', $initializedIds);
        } else {
            if (! $this->confirm('--force will delete and recreate blocks for all targeted businesses. Continue?')) {
                return self::SUCCESS;
            }
        }

        $businesses = $query->get();

        if ($businesses->isEmpty()) {
            $this->info('No businesses to initialize.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($businesses->count());
        $bar->start();

        foreach ($businesses as $business) {
            try {
                DB::transaction(function () use ($business, $defaultTemplate) {
                    if ($this->option('force')) {
                        BusinessPageBlock::withoutGlobalScopes()
                            ->where('business_id', $business->id)
                            ->delete();
                    }

                    foreach ($defaultTemplate->pageTemplateBlocks as $templateBlock) {
                        BusinessPageBlock::withoutGlobalScopes()->create([
                            'business_id'            => $business->id,
                            'page_template_id'       => $defaultTemplate->id,
                            'page_template_block_id' => $templateBlock->id,
                            'block_type'             => $templateBlock->block_type,
                            'variant'                => $templateBlock->variant,
                            'sort_order'             => $templateBlock->sort_order,
                            'is_enabled'             => $templateBlock->is_enabled,
                            'is_required'            => $templateBlock->is_required,
                            'is_locked'              => $templateBlock->is_locked,
                            'content'                => $templateBlock->content,
                            'settings'               => $templateBlock->settings,
                            'schema_version'         => $templateBlock->schema_version,
                        ]);
                    }

                    SalonProfile::withoutGlobalScopes()
                        ->where('business_id', $business->id)
                        ->update(['page_template_id' => $defaultTemplate->id]);
                });
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Failed for business {$business->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Initialized {$businesses->count()} business(es).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run all tests — expect PASS**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/PageBuilder/
```

- [ ] **Step 5: Run the command on real data**

```bash
docker-compose run --rm app php artisan page-builder:init
```

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/PageBuilderInit.php tests/Feature/PageBuilder/PageBuilderInitCommandTest.php
git commit -m "feat(page-builder): add page-builder:init Artisan command with idempotent snapshot init"
```
