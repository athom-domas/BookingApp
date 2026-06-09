# Product Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere la gestione prodotti retail (catalogo, acquisto con ritiro in salone, stock, notifiche scorte basse) al gestionale multi-tenant.

**Architecture:** Modelli standalone `Product`/`ProductOrder`/`ProductOrderItem` con `BelongsToBusiness` trait per la multi-tenancy. Pagamento tramite la stessa `payment_mode` già usata per gli appuntamenti. Notifica soglia scorte via job/email. Nessuna modifica al modello `Payment` esistente.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Spatie MediaLibrary, Stripe, Pest

---

## File Structure

**New files:**
- `database/migrations/2026_06_09_100000_create_products_table.php`
- `database/migrations/2026_06_09_100001_create_product_orders_table.php`
- `database/migrations/2026_06_09_100002_create_product_order_items_table.php`
- `database/migrations/2026_06_09_100003_add_low_stock_notify_to_system_settings.php`
- `app/Models/Product.php`
- `app/Models/ProductOrder.php`
- `app/Models/ProductOrderItem.php`
- `app/Exceptions/ProductOrderException.php`
- `app/Services/ProductOrderService.php`
- `app/Jobs/SendLowStockNotificationJob.php`
- `app/Mail/LowStockNotificationMail.php`
- `app/Filament/Resources/ProductResource.php`
- `app/Filament/Resources/ProductResource/Pages/ListProducts.php`
- `app/Filament/Resources/ProductResource/Pages/CreateProduct.php`
- `app/Filament/Resources/ProductResource/Pages/EditProduct.php`
- `app/Filament/Resources/ProductOrderResource.php`
- `app/Filament/Resources/ProductOrderResource/Pages/ListProductOrders.php`
- `app/Filament/Resources/ProductOrderResource/Pages/ViewProductOrder.php`
- `app/Http/Controllers/Portal/ProductController.php`
- `app/Http/Controllers/Portal/ProductOrderController.php`
- `database/factories/ProductFactory.php`
- `database/factories/ProductOrderFactory.php`
- `database/factories/ProductOrderItemFactory.php`
- `resources/views/portal/products/index.blade.php`
- `resources/views/portal/products/checkout.blade.php`
- `resources/views/portal/products/payment.blade.php`
- `resources/views/portal/products/confirmation.blade.php`
- `resources/views/portal/orders/index.blade.php`
- `resources/views/emails/low-stock-notification.blade.php`
- `tests/Feature/Models/ProductTest.php`
- `tests/Feature/Models/ProductOrderTest.php`
- `tests/Feature/Services/ProductOrderServiceTest.php`
- `tests/Feature/Jobs/SendLowStockNotificationJobTest.php`
- `tests/Feature/Portal/ProductPortalTest.php`

**Modified files:**
- `app/Models/SystemSetting.php` — add `low_stock_notify_user_ids` to fillable, casts, and static accessor
- `app/Http/Controllers/StripeWebhookController.php` — route product_order webhook events to `ProductOrderService`
- `app/Filament/Pages/SystemSettings.php` — add "Notifiche scorte" section
- `routes/web.php` — add portal product + order routes
- `resources/views/layouts/app.blade.php` — add "Prodotti" nav link

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_06_09_100000_create_products_table.php`
- Create: `database/migrations/2026_06_09_100001_create_product_orders_table.php`
- Create: `database/migrations/2026_06_09_100002_create_product_order_items_table.php`
- Create: `database/migrations/2026_06_09_100003_add_low_stock_notify_to_system_settings.php`

- [ ] **Step 1: Create products table migration**

```php
<?php
// database/migrations/2026_06_09_100000_create_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->boolean('in_sale')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

- [ ] **Step 2: Create product_orders table migration**

```php
<?php
// database/migrations/2026_06_09_100001_create_product_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('payment_method');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('payment_status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
};
```

- [ ] **Step 3: Create product_order_items table migration**

```php
<?php
// database/migrations/2026_06_09_100002_create_product_order_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('product_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_order_items');
    }
};
```

- [ ] **Step 4: Add low_stock_notify_user_ids to system_settings**

```php
<?php
// database/migrations/2026_06_09_100003_add_low_stock_notify_to_system_settings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->json('low_stock_notify_user_ids')->nullable()->after('loyalty_reward_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('low_stock_notify_user_ids');
        });
    }
};
```

- [ ] **Step 5: Run migrations**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: 4 new tables/columns created with no errors.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_09_10000*.php
git commit -m "feat: add product management migrations"
```

---

## Task 2: Product Model, Factory, and Tests

**Files:**
- Create: `app/Models/Product.php`
- Create: `database/factories/ProductFactory.php`
- Create: `tests/Feature/Models/ProductTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Models/ProductTest.php

use App\Models\Product;

it('has active scope', function () {
    Product::factory()->create(['active' => true]);
    Product::factory()->create(['active' => false]);

    expect(Product::active()->count())->toBe(1);
});

it('has inSale scope returning only active and in_sale products', function () {
    Product::factory()->create(['in_sale' => true, 'active' => true]);
    Product::factory()->create(['in_sale' => true, 'active' => false]);
    Product::factory()->create(['in_sale' => false, 'active' => true]);

    expect(Product::inSale()->count())->toBe(1);
});

it('has belowThreshold scope', function () {
    Product::factory()->create(['stock' => 5, 'low_stock_threshold' => 10]);
    Product::factory()->create(['stock' => 15, 'low_stock_threshold' => 10]);
    Product::factory()->create(['stock' => 5, 'low_stock_threshold' => null]);

    expect(Product::belowThreshold()->count())->toBe(1);
});

it('isAvailable returns true only when active, in_sale and stock > 0', function () {
    $available = Product::factory()->make(['active' => true, 'in_sale' => true, 'stock' => 1]);
    $noStock   = Product::factory()->make(['active' => true, 'in_sale' => true, 'stock' => 0]);
    $inactive  = Product::factory()->make(['active' => false, 'in_sale' => true, 'stock' => 5]);

    expect($available->isAvailable())->toBeTrue();
    expect($noStock->isAvailable())->toBeFalse();
    expect($inactive->isAvailable())->toBeFalse();
});

it('isBelowThreshold returns true when stock is at or below threshold', function () {
    $below     = Product::factory()->make(['stock' => 3, 'low_stock_threshold' => 5]);
    $atLimit   = Product::factory()->make(['stock' => 5, 'low_stock_threshold' => 5]);
    $above     = Product::factory()->make(['stock' => 6, 'low_stock_threshold' => 5]);
    $noThresh  = Product::factory()->make(['stock' => 1, 'low_stock_threshold' => null]);

    expect($below->isBelowThreshold())->toBeTrue();
    expect($atLimit->isBelowThreshold())->toBeTrue();
    expect($above->isBelowThreshold())->toBeFalse();
    expect($noThresh->isBelowThreshold())->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/ProductTest.php
```

Expected: FAIL — class `App\Models\Product` not found.

- [ ] **Step 3: Create Product model**

```php
<?php
// app/Models/Product.php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['business_id', 'name', 'description', 'price', 'stock', 'low_stock_threshold', 'in_sale', 'active'])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToBusiness, HasFactory, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'price'               => 'decimal:2',
            'stock'               => 'integer',
            'low_stock_threshold' => 'integer',
            'in_sale'             => 'boolean',
            'active'              => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(10);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeInSale(Builder $query): Builder
    {
        return $query->where('in_sale', true)->where('active', true);
    }

    public function scopeBelowThreshold(Builder $query): Builder
    {
        return $query->whereNotNull('low_stock_threshold')
            ->whereColumn('stock', '<=', 'low_stock_threshold');
    }

    public function isAvailable(): bool
    {
        return $this->active && $this->in_sale && $this->stock > 0;
    }

    public function isBelowThreshold(): bool
    {
        return $this->low_stock_threshold !== null && $this->stock <= $this->low_stock_threshold;
    }
}
```

- [ ] **Step 4: Create ProductFactory**

```php
<?php
// database/factories/ProductFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'        => 1,
            'name'               => fake()->words(3, true),
            'description'        => fake()->sentence(),
            'price'              => fake()->randomFloat(2, 5, 100),
            'stock'              => fake()->numberBetween(0, 50),
            'low_stock_threshold' => null,
            'in_sale'            => true,
            'active'             => true,
        ];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/ProductTest.php
```

Expected: 5 passing.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Product.php database/factories/ProductFactory.php tests/Feature/Models/ProductTest.php
git commit -m "feat: add Product model with scopes and media support"
```

---

## Task 3: ProductOrder + ProductOrderItem Models, Factories, Tests

**Files:**
- Create: `app/Models/ProductOrder.php`
- Create: `app/Models/ProductOrderItem.php`
- Create: `database/factories/ProductOrderFactory.php`
- Create: `database/factories/ProductOrderItemFactory.php`
- Create: `tests/Feature/Models/ProductOrderTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Models/ProductOrderTest.php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;

it('has items relationship', function () {
    $order = ProductOrder::factory()->create();
    $item  = ProductOrderItem::factory()->create(['order_id' => $order->id]);

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->id)->toBe($item->id);
});

it('computes total from items', function () {
    $order = ProductOrder::factory()->create();
    ProductOrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 2, 'unit_price' => 10.00]);
    ProductOrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'unit_price' => 5.50]);

    expect($order->load('items')->total)->toBe(25.5);
});

it('isCancellable returns true for pending and confirmed', function () {
    $pending   = ProductOrder::factory()->make(['status' => 'pending']);
    $confirmed = ProductOrder::factory()->make(['status' => 'confirmed']);
    $ready     = ProductOrder::factory()->make(['status' => 'ready']);
    $completed = ProductOrder::factory()->make(['status' => 'completed']);

    expect($pending->isCancellable())->toBeTrue();
    expect($confirmed->isCancellable())->toBeTrue();
    expect($ready->isCancellable())->toBeFalse();
    expect($completed->isCancellable())->toBeFalse();
});

it('belongs to user', function () {
    $user  = User::factory()->create();
    $order = ProductOrder::factory()->create(['user_id' => $user->id]);

    expect($order->user->id)->toBe($user->id);
});

it('item has subtotal accessor', function () {
    $item = ProductOrderItem::factory()->make(['quantity' => 3, 'unit_price' => 12.00]);

    expect($item->subtotal)->toBe(36.0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/ProductOrderTest.php
```

Expected: FAIL — class `App\Models\ProductOrder` not found.

- [ ] **Step 3: Create ProductOrder model**

```php
<?php
// app/Models/ProductOrder.php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'user_id', 'status', 'payment_method', 'stripe_payment_intent_id', 'payment_status', 'notes'])]
class ProductOrder extends Model
{
    /** @use HasFactory<\Database\Factories\ProductOrderFactory> */
    use BelongsToBusiness, HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class, 'order_id');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum(fn ($item) => (float) $item->unit_price * $item->quantity);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }
}
```

- [ ] **Step 4: Create ProductOrderItem model**

```php
<?php
// app/Models/ProductOrderItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'quantity', 'unit_price'])]
class ProductOrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\ProductOrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }
}
```

- [ ] **Step 5: Create ProductOrderFactory**

```php
<?php
// database/factories/ProductOrderFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductOrder>
 */
class ProductOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'    => 1,
            'user_id'        => \App\Models\User::factory(),
            'status'         => 'confirmed',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ];
    }
}
```

- [ ] **Step 6: Create ProductOrderItemFactory**

```php
<?php
// database/factories/ProductOrderItemFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductOrderItem>
 */
class ProductOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id'   => \App\Models\ProductOrder::factory(),
            'product_id' => \App\Models\Product::factory(),
            'quantity'   => fake()->numberBetween(1, 5),
            'unit_price' => fake()->randomFloat(2, 5, 100),
        ];
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/ProductOrderTest.php
```

Expected: 5 passing.

- [ ] **Step 8: Commit**

```bash
git add app/Models/ProductOrder.php app/Models/ProductOrderItem.php \
        database/factories/ProductOrderFactory.php database/factories/ProductOrderItemFactory.php \
        tests/Feature/Models/ProductOrderTest.php
git commit -m "feat: add ProductOrder and ProductOrderItem models"
```

---

## Task 4: SystemSetting — Add low_stock_notify_user_ids

**Files:**
- Modify: `app/Models/SystemSetting.php`

- [ ] **Step 1: Add `low_stock_notify_user_ids` to `#[Fillable]`**

In `app/Models/SystemSetting.php`, change:
```php
#[Fillable([
    'business_id',
    'slot_generation_weeks', 'slot_granularity_minutes', 'timezone',
    'booking_max_days_ahead', 'cancellation_deadline_hours',
    'reminder_count', 'reminder_1_hours', 'reminder_2_hours', 'payment_mode',
    'reviews_enabled',
    'loyalty_enabled', 'loyalty_points_per_euro', 'loyalty_reward_threshold', 'loyalty_reward_percentage',
])]
```
to:
```php
#[Fillable([
    'business_id',
    'slot_generation_weeks', 'slot_granularity_minutes', 'timezone',
    'booking_max_days_ahead', 'cancellation_deadline_hours',
    'reminder_count', 'reminder_1_hours', 'reminder_2_hours', 'payment_mode',
    'reviews_enabled',
    'loyalty_enabled', 'loyalty_points_per_euro', 'loyalty_reward_threshold', 'loyalty_reward_percentage',
    'low_stock_notify_user_ids',
])]
```

- [ ] **Step 2: Add cast in `casts()` method**

In the `casts()` method, add:
```php
'low_stock_notify_user_ids' => 'array',
```

- [ ] **Step 3: Add static accessor method**

Add this static method after `getLoyaltyRewardPercentage()`:
```php
public static function getLowStockNotifyUserIds(): array
{
    return self::current()->low_stock_notify_user_ids ?? [];
}
```

- [ ] **Step 4: Run the SystemSetting model tests to confirm no regression**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/SystemSettingTest.php
```

Expected: all passing.

- [ ] **Step 5: Commit**

```bash
git add app/Models/SystemSetting.php
git commit -m "feat: add low_stock_notify_user_ids to SystemSetting"
```

---

## Task 5: ProductOrderException + ProductOrderService

**Files:**
- Create: `app/Exceptions/ProductOrderException.php`
- Create: `app/Services/ProductOrderService.php`
- Create: `tests/Feature/Services/ProductOrderServiceTest.php`

- [ ] **Step 1: Create exception class**

```php
<?php
// app/Exceptions/ProductOrderException.php

namespace App\Exceptions;

use RuntimeException;

class ProductOrderException extends RuntimeException {}
```

- [ ] **Step 2: Write failing service tests**

```php
<?php
// tests/Feature/Services/ProductOrderServiceTest.php

use App\Exceptions\ProductOrderException;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\ProductOrderService;
use Stripe\StripeClient;

beforeEach(function () {
    $this->service = app(ProductOrderService::class);
});

it('creates a cash order and decrements stock', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 20.00]);
    $user    = User::factory()->create();

    $order = $this->service->createOrder($user->id, [
        ['product_id' => $product->id, 'quantity' => 3],
    ], 'cash');

    expect($order->status)->toBe('confirmed');
    expect($order->payment_method)->toBe('cash');
    expect($order->payment_status)->toBe('pending');
    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->unit_price)->toBe('20.00');
    expect($product->fresh()->stock)->toBe(7);
});

it('creates a stripe order in pending status', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $user    = User::factory()->create();

    $order = $this->service->createOrder($user->id, [
        ['product_id' => $product->id, 'quantity' => 1],
    ], 'stripe');

    expect($order->status)->toBe('pending');
    expect($order->payment_method)->toBe('stripe');
});

it('throws when stock is insufficient', function () {
    $product = Product::factory()->create(['stock' => 2]);
    $user    = User::factory()->create();

    expect(fn () => $this->service->createOrder($user->id, [
        ['product_id' => $product->id, 'quantity' => 5],
    ], 'cash'))->toThrow(ProductOrderException::class);
});

it('throws when product does not exist', function () {
    $user = User::factory()->create();

    expect(fn () => $this->service->createOrder($user->id, [
        ['product_id' => 99999, 'quantity' => 1],
    ], 'cash'))->toThrow(ProductOrderException::class);
});

it('confirms stripe payment and updates order status', function () {
    $order = ProductOrder::factory()->create([
        'status'                  => 'pending',
        'payment_method'          => 'stripe',
        'stripe_payment_intent_id' => 'pi_test123',
    ]);

    $this->service->confirmStripePayment('pi_test123');

    $order->refresh();
    expect($order->status)->toBe('confirmed');
    expect($order->payment_status)->toBe('paid');
});

it('cancels order and restores stock', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $user    = User::factory()->create();

    $order = $this->service->createOrder($user->id, [
        ['product_id' => $product->id, 'quantity' => 2],
    ], 'cash');

    expect($product->fresh()->stock)->toBe(3);

    $this->service->cancelOrder($order);

    expect($order->fresh()->status)->toBe('cancelled');
    expect($product->fresh()->stock)->toBe(5);
});

it('does not cancel an order that is already ready', function () {
    $order = ProductOrder::factory()->create(['status' => 'ready']);

    $this->service->cancelOrder($order);

    expect($order->fresh()->status)->toBe('ready');
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/ProductOrderServiceTest.php
```

Expected: FAIL — class `App\Services\ProductOrderService` not found.

- [ ] **Step 4: Create ProductOrderService**

```php
<?php
// app/Services/ProductOrderService.php

namespace App\Services;

use App\Exceptions\ProductOrderException;
use App\Jobs\SendLowStockNotificationJob;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class ProductOrderService
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function createOrder(int $userId, array $items, string $paymentMethod, ?string $notes = null): ProductOrder
    {
        return DB::transaction(function () use ($userId, $items, $paymentMethod, $notes) {
            $productIds = array_column($items, 'product_id');
            $products   = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || ! $product->active) {
                    throw new ProductOrderException('Prodotto non disponibile: ' . ($product?->name ?? 'ID ' . $item['product_id']));
                }
                if ($product->stock < $item['quantity']) {
                    throw new ProductOrderException('Stock insufficiente per: ' . $product->name);
                }
            }

            $order = ProductOrder::create([
                'user_id'        => $userId,
                'status'         => $paymentMethod === 'cash' ? 'confirmed' : 'pending',
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'notes'          => $notes,
            ]);

            foreach ($items as $item) {
                $product       = $products->get($item['product_id']);
                $previousStock = $product->stock;

                ProductOrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price,
                ]);

                $product->decrement('stock', $item['quantity']);
                $product->refresh();

                $this->maybeSendLowStockNotification($product, $previousStock);
            }

            return $order->load('items.product');
        });
    }

    public function createStripePaymentIntent(ProductOrder $order): string
    {
        $amountCents = (int) round($order->total * 100);

        $paymentIntent = $this->stripe->paymentIntents->create([
            'amount'                    => $amountCents,
            'currency'                  => 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata'                  => [
                'payable_type' => 'product_order',
                'payable_id'   => $order->id,
                'business_id'  => $order->business_id,
            ],
        ]);

        $order->update(['stripe_payment_intent_id' => $paymentIntent->id]);

        return $paymentIntent->client_secret;
    }

    public function confirmStripePayment(string $paymentIntentId): void
    {
        $order = ProductOrder::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if (! $order) {
            return;
        }

        $order->update(['status' => 'confirmed', 'payment_status' => 'paid']);
    }

    public function handleFailedStripePayment(string $paymentIntentId): void
    {
        $order = ProductOrder::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if (! $order) {
            return;
        }

        $this->cancelOrder($order);
    }

    public function handleStripeWebhook(array $payload): void
    {
        $type            = $payload['type'] ?? '';
        $paymentIntentId = $payload['data']['object']['id'] ?? null;

        if (! $paymentIntentId) {
            return;
        }

        if ($type === 'payment_intent.succeeded') {
            $this->confirmStripePayment($paymentIntentId);
        } elseif (in_array($type, ['payment_intent.payment_failed', 'payment_intent.canceled'])) {
            $this->handleFailedStripePayment($paymentIntentId);
        }
    }

    public function cancelOrder(ProductOrder $order): void
    {
        if (! $order->isCancellable()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $order->loadMissing('items');

            foreach ($order->items as $item) {
                Product::where('id', $item->product_id)->increment('stock', $item->quantity);
            }

            $order->update(['status' => 'cancelled', 'payment_status' => 'cancelled']);
        });
    }

    private function maybeSendLowStockNotification(Product $product, int $previousStock): void
    {
        if ($product->low_stock_threshold === null) {
            return;
        }

        $notifyUserIds = SystemSetting::getLowStockNotifyUserIds();
        if (empty($notifyUserIds)) {
            return;
        }

        if ($previousStock > $product->low_stock_threshold && $product->stock <= $product->low_stock_threshold) {
            SendLowStockNotificationJob::dispatch($product, $notifyUserIds);
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/ProductOrderServiceTest.php
```

Expected: 7 passing.

- [ ] **Step 6: Commit**

```bash
git add app/Exceptions/ProductOrderException.php app/Services/ProductOrderService.php \
        tests/Feature/Services/ProductOrderServiceTest.php
git commit -m "feat: add ProductOrderService with order creation, cancellation and Stripe handling"
```

---

## Task 6: Low Stock Notification Job + Mail + Email View

**Files:**
- Create: `app/Jobs/SendLowStockNotificationJob.php`
- Create: `app/Mail/LowStockNotificationMail.php`
- Create: `resources/views/emails/low-stock-notification.blade.php`
- Create: `tests/Feature/Jobs/SendLowStockNotificationJobTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Jobs/SendLowStockNotificationJobTest.php

use App\Jobs\SendLowStockNotificationJob;
use App\Mail\LowStockNotificationMail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('sends an email to each notified user', function () {
    Mail::fake();

    $product = Product::factory()->create(['stock' => 3, 'low_stock_threshold' => 5, 'name' => 'Shampoo Argan']);
    $admin   = User::factory()->create(['email' => 'admin@test.com']);
    $staff   = User::factory()->create(['email' => 'staff@test.com']);

    (new SendLowStockNotificationJob($product, [$admin->id, $staff->id]))->handle();

    Mail::assertSent(LowStockNotificationMail::class, 2);
    Mail::assertSent(LowStockNotificationMail::class, fn ($mail) => $mail->hasTo('admin@test.com'));
    Mail::assertSent(LowStockNotificationMail::class, fn ($mail) => $mail->hasTo('staff@test.com'));
});

it('sends nothing when user ids list is empty', function () {
    Mail::fake();

    $product = Product::factory()->create();

    (new SendLowStockNotificationJob($product, []))->handle();

    Mail::assertNothingSent();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Jobs/SendLowStockNotificationJobTest.php
```

Expected: FAIL — class `App\Jobs\SendLowStockNotificationJob` not found.

- [ ] **Step 3: Create job**

```php
<?php
// app/Jobs/SendLowStockNotificationJob.php

namespace App\Jobs;

use App\Mail\LowStockNotificationMail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLowStockNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly array $userIds,
    ) {}

    public function handle(): void
    {
        if (empty($this->userIds)) {
            return;
        }

        app()->instance('current_business_id', $this->product->business_id);

        $users = User::whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            Mail::send(new LowStockNotificationMail($this->product, $user));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendLowStockNotificationJob failed', [
            'product_id' => $this->product->id,
            'error'      => $e->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Create mail class**

```php
<?php
// app/Mail/LowStockNotificationMail.php

namespace App\Mail;

use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->recipient->email,
            subject: 'Scorte basse: ' . $this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.low-stock-notification');
    }
}
```

- [ ] **Step 5: Create email view**

```blade
{{-- resources/views/emails/low-stock-notification.blade.php --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Scorte basse: {{ $product->name }}</title>
</head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #d97706;">⚠️ Scorte basse: {{ $product->name }}</h2>
    <p>Ciao {{ $recipient->name }},</p>
    <p>Il prodotto <strong>{{ $product->name }}</strong> ha raggiunto la soglia minima di scorte.</p>
    <table style="border-collapse: collapse; width: 100%; margin: 20px 0;">
        <tr>
            <td style="padding: 8px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold;">Scorte attuali</td>
            <td style="padding: 8px; border: 1px solid #e5e7eb; color: #dc2626; font-weight: bold;">{{ $product->stock }} pezzi</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold;">Soglia impostata</td>
            <td style="padding: 8px; border: 1px solid #e5e7eb;">{{ $product->low_stock_threshold }} pezzi</td>
        </tr>
    </table>
    <p>Accedi al pannello di amministrazione per aggiornare le scorte.</p>
</body>
</html>
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Jobs/SendLowStockNotificationJobTest.php
```

Expected: 2 passing.

- [ ] **Step 7: Commit**

```bash
git add app/Jobs/SendLowStockNotificationJob.php app/Mail/LowStockNotificationMail.php \
        resources/views/emails/low-stock-notification.blade.php \
        tests/Feature/Jobs/SendLowStockNotificationJobTest.php
git commit -m "feat: add low stock notification job and mail"
```

---

## Task 7: Stripe Webhook Routing for Product Orders

**Files:**
- Modify: `app/Http/Controllers/StripeWebhookController.php`

The webhook controller must route events with `metadata.payable_type === 'product_order'` to `ProductOrderService::handleStripeWebhook()`, and all others to the existing `PaymentService::handleStripeWebhook()`.

- [ ] **Step 1: Update StripeWebhookController**

Replace the contents of `app/Http/Controllers/StripeWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Services\ProductOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly ProductOrderService $productOrderService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = \App\Models\IntegrationSetting::getStripeWebhookSecret() ?? config('services.stripe.webhook_secret');

        if (! $secret) {
            return response()->json(['message' => 'Stripe webhook secret is not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        $businessId = $event->data->object?->metadata?->business_id ?? null;
        if ($businessId) {
            app()->instance('current_business_id', (int) $businessId);
        }

        $payableType = $event->data->object?->metadata?->payable_type ?? null;

        if ($payableType === 'product_order') {
            $this->productOrderService->handleStripeWebhook($event->toArray());
        } else {
            $this->paymentService->handleStripeWebhook($event->toArray());
        }

        return response()->json(['received' => true]);
    }
}
```

- [ ] **Step 2: Run full test suite to verify no regressions**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: all previously passing tests still pass.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/StripeWebhookController.php
git commit -m "feat: route product_order Stripe webhook events to ProductOrderService"
```

---

## Task 8: ProductResource (Filament Admin)

**Files:**
- Create: `app/Filament/Resources/ProductResource.php`
- Create: `app/Filament/Resources/ProductResource/Pages/ListProducts.php`
- Create: `app/Filament/Resources/ProductResource/Pages/CreateProduct.php`
- Create: `app/Filament/Resources/ProductResource/Pages/EditProduct.php`

- [ ] **Step 1: Create ProductResource**

```php
<?php
// app/Filament/Resources/ProductResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ProductOrderItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'prodotto';
    protected static ?string $pluralModelLabel = 'prodotti';

    public static function canViewAny(): bool
    {
        return ! auth()->user()?->isStaff();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (! (auth()->user()?->isAdmin() ?? false)) {
            return false;
        }
        return ! ProductOrderItem::where('product_id', $record->id)->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informazioni')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(3)
                        ->columnSpanFull(),

                    TextInput::make('price')
                        ->label('Prezzo (€)')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01),

                    SpatieMediaLibraryFileUpload::make('photo')
                        ->label('Foto prodotto')
                        ->collection('photo')
                        ->image()
                        ->maxSize(4096)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Scorte')
                ->schema([
                    TextInput::make('stock')
                        ->label('Scorte disponibili')
                        ->required()
                        ->numeric()
                        ->integer()
                        ->minValue(0),

                    TextInput::make('low_stock_threshold')
                        ->label('Soglia scorte basse')
                        ->helperText('Ricevi una notifica quando le scorte scendono a questo livello. Lascia vuoto per disabilitare.')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->nullable(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Visibilità')
                ->schema([
                    Toggle::make('in_sale')
                        ->label('In vendita')
                        ->helperText('Mostra il prodotto nella pagina clienti')
                        ->default(false),

                    Toggle::make('active')
                        ->label('Attivo')
                        ->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('Foto')
                    ->collection('photo')
                    ->conversion('thumb')
                    ->width(48)
                    ->height(48),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('stock')
                    ->label('Scorte')
                    ->sortable()
                    ->badge()
                    ->color(fn (Product $record): string => $record->isBelowThreshold() ? 'danger' : 'success'),

                ToggleColumn::make('in_sale')
                    ->label('In vendita'),

                ToggleColumn::make('active')
                    ->label('Attivo'),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Attivo')->boolean()
                    ->trueLabel('Solo attivi')->falseLabel('Solo inattivi'),
                TernaryFilter::make('in_sale')->label('In vendita')->boolean()
                    ->trueLabel('In vendita')->falseLabel('Non in vendita'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Product $record, DeleteAction $action) {
                        if (ProductOrderItem::where('product_id', $record->id)->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Impossibile eliminare')
                                ->body('Il prodotto ha ordini associati. Disattivalo invece di eliminarlo.')
                                ->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 2: Create page stubs**

```php
<?php
// app/Filament/Resources/ProductResource/Pages/ListProducts.php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

```php
<?php
// app/Filament/Resources/ProductResource/Pages/CreateProduct.php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
```

```php
<?php
// app/Filament/Resources/ProductResource/Pages/EditProduct.php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

- [ ] **Step 3: Verify no syntax errors by running the full suite**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: all tests pass (Filament resources are auto-discovered, no new test failures).

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/ProductResource.php \
        app/Filament/Resources/ProductResource/
git commit -m "feat: add ProductResource for Filament admin panel"
```

---

## Task 9: ProductOrderResource (Filament Admin)

**Files:**
- Create: `app/Filament/Resources/ProductOrderResource.php`
- Create: `app/Filament/Resources/ProductOrderResource/Pages/ListProductOrders.php`
- Create: `app/Filament/Resources/ProductOrderResource/Pages/ViewProductOrder.php`

- [ ] **Step 1: Create ProductOrderResource**

```php
<?php
// app/Filament/Resources/ProductOrderResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductOrderResource\Pages;
use App\Models\ProductOrder;
use App\Services\ProductOrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;

class ProductOrderResource extends Resource
{
    protected static ?string $model = ProductOrder::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 5;
    protected static ?string $modelLabel = 'ordine prodotti';
    protected static ?string $pluralModelLabel = 'ordini prodotti';

    public static function canViewAny(): bool
    {
        return ! auth()->user()?->isStaff();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist->schema([
            \Filament\Infolists\Components\Section::make('Riepilogo ordine')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('user.name')->label('Cliente'),
                    \Filament\Infolists\Components\TextEntry::make('created_at')->label('Data')->dateTime('d/m/Y H:i'),
                    \Filament\Infolists\Components\TextEntry::make('status')->label('Stato')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'pending'   => 'warning',
                            'confirmed' => 'info',
                            'ready'     => 'success',
                            'completed' => 'gray',
                            'cancelled' => 'danger',
                            default     => 'gray',
                        }),
                    \Filament\Infolists\Components\TextEntry::make('payment_method')->label('Metodo pagamento')
                        ->formatStateUsing(fn ($state) => $state === 'stripe' ? 'Online (Stripe)' : 'In salone (contanti)'),
                    \Filament\Infolists\Components\TextEntry::make('payment_status')->label('Stato pagamento'),
                    \Filament\Infolists\Components\TextEntry::make('notes')->label('Note')->placeholder('—'),
                ])
                ->columns(2),

            \Filament\Infolists\Components\Section::make('Articoli')
                ->schema([
                    \Filament\Infolists\Components\RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('product.name')->label('Prodotto'),
                            \Filament\Infolists\Components\TextEntry::make('quantity')->label('Quantità'),
                            \Filament\Infolists\Components\TextEntry::make('unit_price')->label('Prezzo unitario')->money('EUR'),
                            \Filament\Infolists\Components\TextEntry::make('subtotal')->label('Subtotale')->money('EUR'),
                        ])
                        ->columns(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('status')->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'info',
                        'ready'     => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),
                TextColumn::make('payment_method')->label('Pagamento')
                    ->formatStateUsing(fn ($state) => $state === 'stripe' ? 'Stripe' : 'Contanti'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Stato')
                    ->options([
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'ready'     => 'Pronto',
                        'completed' => 'Completato',
                        'cancelled' => 'Cancellato',
                    ]),
            ])
            ->actions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductOrders::route('/'),
            'view'  => Pages\ViewProductOrder::route('/{record}'),
        ];
    }
}
```

- [ ] **Step 2: Create page stubs**

```php
<?php
// app/Filament/Resources/ProductOrderResource/Pages/ListProductOrders.php

namespace App\Filament\Resources\ProductOrderResource\Pages;

use App\Filament\Resources\ProductOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListProductOrders extends ListRecords
{
    protected static string $resource = ProductOrderResource::class;
}
```

```php
<?php
// app/Filament/Resources/ProductOrderResource/Pages/ViewProductOrder.php

namespace App\Filament\Resources\ProductOrderResource\Pages;

use App\Filament\Resources\ProductOrderResource;
use App\Models\ProductOrder;
use App\Services\ProductOrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProductOrder extends ViewRecord
{
    protected static string $resource = ProductOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('advance')
                ->label(fn () => match ($this->record->status) {
                    'confirmed' => 'Segna come pronto',
                    'ready'     => 'Segna come completato',
                    default     => 'Avanza stato',
                })
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn () => in_array($this->record->status, ['confirmed', 'ready']))
                ->action(function () {
                    $next = match ($this->record->status) {
                        'confirmed' => 'ready',
                        'ready'     => 'completed',
                        default     => null,
                    };
                    if ($next) {
                        $this->record->update(['status' => $next]);
                        Notification::make()->success()->title('Stato aggiornato')->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Action::make('cancel')
                ->label('Cancella ordine')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->isCancellable())
                ->requiresConfirmation()
                ->action(function () {
                    app(ProductOrderService::class)->cancelOrder($this->record);
                    Notification::make()->success()->title('Ordine cancellato')->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
```

- [ ] **Step 3: Run full test suite**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/ProductOrderResource.php \
        app/Filament/Resources/ProductOrderResource/
git commit -m "feat: add ProductOrderResource for Filament admin panel"
```

---

## Task 10: SystemSettings Filament Page — Low Stock Notification Section

**Files:**
- Modify: `app/Filament/Pages/SystemSettings.php`

- [ ] **Step 1: Add `low_stock_notify_user_ids` to the `mount()` fill array**

In the `mount()` method, add to the `$this->form->fill([...])` array:
```php
'low_stock_notify_user_ids' => $setting->low_stock_notify_user_ids ?? [],
```

- [ ] **Step 2: Add the "Notifiche scorte" section to the `form()` method**

After the "Fedeltà" section (before `->statePath('data')`), add:

```php
Section::make('Notifiche scorte basse')
    ->schema([
        \Filament\Forms\Components\Select::make('low_stock_notify_user_ids')
            ->label('Notifica a')
            ->helperText('Utenti che ricevono un\'email quando le scorte di un prodotto scendono sotto la soglia impostata.')
            ->multiple()
            ->options(function () {
                return \App\Models\User::where('business_id', \App\Models\Business::currentId())
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'staff'])->where('guard_name', 'web'))
                    ->orderBy('name')
                    ->pluck('name', 'id');
            })
            ->columnSpanFull(),
    ]),
```

- [ ] **Step 3: Run full test suite**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/SystemSettings.php
git commit -m "feat: add low stock notification recipients to SystemSettings"
```

---

## Task 11: Portal — Product Catalog + Cart

**Files:**
- Create: `app/Http/Controllers/Portal/ProductController.php`
- Create: `resources/views/portal/products/index.blade.php`

The cart is stored in the session as `product_cart` = `['product_id' => quantity, ...]`.

- [ ] **Step 1: Write failing portal product tests**

```php
<?php
// tests/Feature/Portal/ProductPortalTest.php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('shows only in_sale and active products on the catalog page', function () {
    $visible   = Product::factory()->create(['name' => 'Shampoo Visible', 'in_sale' => true, 'active' => true]);
    $hidden    = Product::factory()->create(['name' => 'Hidden Product', 'in_sale' => false, 'active' => true]);
    $inactive  = Product::factory()->create(['name' => 'Inactive Product', 'in_sale' => true, 'active' => false]);
    $customer  = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)->get('/portal/products')
        ->assertOk()
        ->assertSee('Shampoo Visible')
        ->assertDontSee('Hidden Product')
        ->assertDontSee('Inactive Product');
});

it('adds a product to the cart', function () {
    $product  = Product::factory()->create(['stock' => 5]);
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->post('/portal/cart', ['product_id' => $product->id, 'quantity' => 2])
        ->assertRedirect('/portal/products');

    $this->actingAs($customer)->get('/portal/products')
        ->assertSessionHas('product_cart', [$product->id => 2]);
});

it('does not add more than available stock to cart', function () {
    $product  = Product::factory()->create(['stock' => 3]);
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->post('/portal/cart', ['product_id' => $product->id, 'quantity' => 10])
        ->assertRedirect();

    $this->actingAs($customer)->get('/portal/products')
        ->assertSessionMissing('product_cart.' . $product->id);
});

it('shows the checkout page with cart contents', function () {
    $product  = Product::factory()->create(['name' => 'Balsamo Test', 'stock' => 5, 'price' => 15.00]);
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->withSession(['product_cart' => [$product->id => 2]])
        ->get('/portal/products/checkout')
        ->assertOk()
        ->assertSee('Balsamo Test')
        ->assertSee('30,00');
});

it('redirects to products page when cart is empty on checkout', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->get('/portal/products/checkout')
        ->assertRedirect('/portal/products');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Portal/ProductPortalTest.php
```

Expected: FAIL — route not found / controller not found.

- [ ] **Step 3: Create ProductController**

```php
<?php
// app/Http/Controllers/Portal/ProductController.php

namespace App\Http\Controllers\Portal;

use App\Exceptions\ProductOrderException;
use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Services\ProductOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly ProductOrderService $service) {}

    public function index(): View
    {
        $products = Product::inSale()->with('media')->orderBy('name')->get();
        $cart     = session('product_cart', []);

        $cartItems = collect($cart)->map(function (int $qty, int $productId) use ($products) {
            $product = $products->firstWhere('id', $productId);
            return $product ? ['product' => $product, 'quantity' => $qty] : null;
        })->filter()->values();

        return view('portal.products.index', compact('products', 'cartItems'));
    }

    public function cartUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $product = Product::find($validated['product_id']);

        if (! $product || ! $product->isAvailable() || $product->stock < $validated['quantity']) {
            return back()->withErrors(['cart' => 'Quantità non disponibile per questo prodotto.']);
        }

        $cart = session('product_cart', []);
        $cart[$product->id] = $validated['quantity'];
        session(['product_cart' => $cart]);

        return redirect()->route('portal.products.index');
    }

    public function cartRemove(int $productId): RedirectResponse
    {
        $cart = session('product_cart', []);
        unset($cart[$productId]);
        session(['product_cart' => $cart]);

        return back();
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $cart = session('product_cart', []);

        if (empty($cart)) {
            return redirect()->route('portal.products.index');
        }

        $products  = Product::whereIn('id', array_keys($cart))->with('media')->get()->keyBy('id');
        $cartItems = collect($cart)->map(fn ($qty, $id) => [
            'product'  => $products->get($id),
            'quantity' => $qty,
        ])->filter(fn ($item) => $item['product'] !== null)->values();

        if ($cartItems->isEmpty()) {
            session()->forget('product_cart');
            return redirect()->route('portal.products.index');
        }

        $total       = $cartItems->sum(fn ($item) => $item['product']->price * $item['quantity']);
        $paymentMode = SystemSetting::getPaymentMode();

        return view('portal.products.checkout', compact('cartItems', 'total', 'paymentMode'));
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $cart = session('product_cart', []);

        if (empty($cart)) {
            return redirect()->route('portal.products.index');
        }

        $rules = ['notes' => ['nullable', 'string', 'max:1000']];
        $paymentMode = SystemSetting::getPaymentMode();

        if ($paymentMode === 'both') {
            $rules['payment_method'] = ['required', 'in:stripe,cash'];
        }

        $validated     = $request->validate($rules);
        $paymentMethod = match ($paymentMode) {
            'online'    => 'stripe',
            'in_salon'  => 'cash',
            default     => $validated['payment_method'],
        };

        $items = collect($cart)->map(fn ($qty, $id) => ['product_id' => (int) $id, 'quantity' => $qty])->values()->all();

        try {
            $order = $this->service->createOrder(
                $request->user()->id,
                $items,
                $paymentMethod,
                $validated['notes'] ?? null,
            );
        } catch (ProductOrderException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        session()->forget('product_cart');

        if ($paymentMethod === 'stripe') {
            $clientSecret = $this->service->createStripePaymentIntent($order);
            return redirect()->route('portal.products.payment', $order)->with('stripe_client_secret', $clientSecret);
        }

        return redirect()->route('portal.products.confirmation', $order);
    }

    public function payment(Request $request, int $orderId): View|RedirectResponse
    {
        $order = \App\Models\ProductOrder::where('user_id', $request->user()->id)
            ->with('items.product')
            ->findOrFail($orderId);

        if ($order->payment_status === 'paid') {
            return redirect()->route('portal.products.confirmation', $order);
        }

        $clientSecret    = session('stripe_client_secret');
        $stripePublicKey = IntegrationSetting::getStripePublicKey() ?? config('services.stripe.public');

        return view('portal.products.payment', compact('order', 'clientSecret', 'stripePublicKey'));
    }

    public function confirmStripePayment(Request $request, int $orderId): RedirectResponse
    {
        $order = \App\Models\ProductOrder::where('user_id', $request->user()->id)->findOrFail($orderId);

        if ($order->payment_method === 'stripe' && $order->stripe_payment_intent_id) {
            $this->service->confirmStripePayment($order->stripe_payment_intent_id);
        }

        return redirect()->route('portal.products.confirmation', $order);
    }

    public function confirmation(Request $request, int $orderId): View
    {
        $order = \App\Models\ProductOrder::where('user_id', $request->user()->id)
            ->with('items.product')
            ->findOrFail($orderId);

        return view('portal.products.confirmation', compact('order'));
    }
}
```

- [ ] **Step 4: Create catalog view**

```blade
{{-- resources/views/portal/products/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Prodotti')

@section('content')
<section class="space-y-8">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Prodotti</h1>
            <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Acquista i prodotti del salone con ritiro in sede.</p>
        </div>
        @if ($cartItems->isNotEmpty())
            <a href="{{ route('portal.products.checkout') }}" class="btn-primary inline-block rounded-md px-5 py-2.5 text-sm font-semibold text-center text-white">
                Vai al checkout ({{ $cartItems->count() }})
            </a>
        @endif
    </div>

    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($products->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">Nessun prodotto disponibile al momento.</p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                @php $inCart = $cartItems->firstWhere('product.id', $product->id); @endphp
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                    @if ($product->hasMedia('photo'))
                        <img src="{{ $product->getFirstMediaUrl('photo', 'thumb') }}" alt="{{ $product->name }}"
                             class="h-48 w-full object-cover">
                    @else
                        <div class="h-48 w-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <span class="text-gray-400 text-sm">Nessuna foto</span>
                        </div>
                    @endif
                    <div class="p-5 space-y-3">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $product->name }}</h3>
                            @if ($product->description)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $product->description }}</p>
                            @endif
                        </div>
                        <p class="text-lg font-semibold text-gray-950 dark:text-gray-50">
                            {{ number_format($product->price, 2, ',', '.') }} €
                        </p>
                        @if ($product->stock === 0)
                            <span class="inline-block rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                Esaurito
                            </span>
                        @else
                            <form method="POST" action="{{ route('portal.cart.update') }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <div class="flex items-center gap-3">
                                    <input type="number" name="quantity" value="{{ $inCart['quantity'] ?? 1 }}"
                                           min="1" max="{{ $product->stock }}"
                                           class="w-20 rounded-md border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                    <button type="submit" class="btn-primary rounded-md px-4 py-1.5 text-sm font-semibold text-white">
                                        {{ $inCart ? 'Aggiorna' : 'Aggiungi' }}
                                    </button>
                                </div>
                            </form>
                            @if ($inCart)
                                <form method="POST" action="{{ route('portal.cart.remove', $product->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline dark:text-red-400">
                                        Rimuovi dal carrello
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection
```

- [ ] **Step 5: Run portal tests to verify they pass**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Portal/ProductPortalTest.php
```

Expected: 5 passing. (Routes must already be registered — see Task 14. If routes are not yet added, add them before running this step.)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Portal/ProductController.php \
        resources/views/portal/products/index.blade.php
git commit -m "feat: add portal product catalog and cart management"
```

---

## Task 12: Portal — Checkout, Payment, and Confirmation Views

**Files:**
- Create: `resources/views/portal/products/checkout.blade.php`
- Create: `resources/views/portal/products/payment.blade.php`
- Create: `resources/views/portal/products/confirmation.blade.php`

- [ ] **Step 1: Create checkout view**

```blade
{{-- resources/views/portal/products/checkout.blade.php --}}
@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<section class="space-y-8 max-w-2xl mx-auto">
    <div>
        <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Checkout</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Riepilogo e conferma ordine.</p>
    </div>

    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6 space-y-4">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Riepilogo ordine</h2>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    <th class="pb-2 font-medium">Prodotto</th>
                    <th class="pb-2 font-medium text-center">Qtà</th>
                    <th class="pb-2 font-medium text-right">Prezzo</th>
                    <th class="pb-2 font-medium text-right">Subtotale</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($cartItems as $item)
                    <tr>
                        <td class="py-3 text-gray-900 dark:text-gray-100">{{ $item['product']->name }}</td>
                        <td class="py-3 text-center text-gray-600 dark:text-gray-400">{{ $item['quantity'] }}</td>
                        <td class="py-3 text-right text-gray-600 dark:text-gray-400">{{ number_format($item['product']->price, 2, ',', '.') }} €</td>
                        <td class="py-3 text-right font-medium text-gray-900 dark:text-gray-100">
                            {{ number_format($item['product']->price * $item['quantity'], 2, ',', '.') }} €
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td colspan="3" class="pt-3 font-semibold text-right text-gray-900 dark:text-gray-100">Totale</td>
                    <td class="pt-3 text-right font-bold text-gray-950 dark:text-gray-50">
                        {{ number_format($total, 2, ',', '.') }} €
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <form method="POST" action="{{ route('portal.products.order') }}" class="space-y-6">
        @csrf

        @if ($paymentMode === 'both')
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6 space-y-4">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100">Metodo di pagamento</h2>
                <div class="space-y-2">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="payment_method" value="stripe" checked
                               class="text-primary-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Online con carta (Stripe)</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="payment_method" value="cash"
                               class="text-primary-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Al ritiro in salone (contanti)</span>
                    </label>
                </div>
            </div>
        @elseif ($paymentMode === 'online')
            <input type="hidden" name="payment_method" value="stripe">
            <p class="text-sm text-gray-600 dark:text-gray-400">Pagamento online con carta.</p>
        @else
            <input type="hidden" name="payment_method" value="cash">
            <p class="text-sm text-gray-600 dark:text-gray-400">Pagamento al ritiro in salone.</p>
        @endif

        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Note (opzionale)</label>
            <textarea name="notes" rows="2"
                      class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                      placeholder="Informazioni per il ritiro..."></textarea>
        </div>

        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('portal.products.index') }}" class="text-sm text-gray-600 hover:underline dark:text-gray-400">
                ← Torna ai prodotti
            </a>
            <button type="submit" class="btn-primary rounded-md px-6 py-2.5 text-sm font-semibold text-white">
                Conferma ordine
            </button>
        </div>
    </form>
</section>
@endsection
```

- [ ] **Step 2: Create payment view (Stripe Elements)**

```blade
{{-- resources/views/portal/products/payment.blade.php --}}
@extends('layouts.app')

@section('title', 'Pagamento')

@section('content')
<section class="space-y-8 max-w-lg mx-auto">
    <div>
        <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Pagamento</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
            Totale ordine: <strong>{{ number_format($order->total, 2, ',', '.') }} €</strong>
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6">
        <div id="payment-element" class="mb-6"></div>
        <div id="payment-message" class="hidden mb-4 text-sm text-red-600 dark:text-red-400"></div>
        <button id="submit-btn"
                class="btn-primary w-full rounded-md px-6 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
            Paga {{ number_format($order->total, 2, ',', '.') }} €
        </button>
    </div>
</section>

<script src="https://js.stripe.com/v3/"></script>
<script>
    const stripe = Stripe('{{ $stripePublicKey }}');
    const elements = stripe.elements({ clientSecret: '{{ $clientSecret }}' });
    const paymentElement = elements.create('payment');
    paymentElement.mount('#payment-element');

    document.getElementById('submit-btn').addEventListener('click', async function () {
        this.disabled = true;
        document.getElementById('payment-message').classList.add('hidden');

        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: '{{ route('portal.products.stripe-confirm', $order->id) }}',
            },
        });

        if (error) {
            document.getElementById('payment-message').textContent = error.message;
            document.getElementById('payment-message').classList.remove('hidden');
            this.disabled = false;
        }
    });
</script>
@endsection
```

- [ ] **Step 3: Create confirmation view**

```blade
{{-- resources/views/portal/products/confirmation.blade.php --}}
@extends('layouts.app')

@section('title', 'Ordine confermato')

@section('content')
<section class="space-y-8 max-w-2xl mx-auto text-center">
    <div>
        <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
            <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1 class="mt-4 font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Ordine confermato!</h1>
        <p class="mt-2 text-gray-500 dark:text-gray-400">
            @if ($order->payment_method === 'cash')
                Il tuo ordine è stato ricevuto. Passa in salone per ritirarlo e pagare.
            @else
                Pagamento ricevuto. Passa in salone per ritirare i tuoi prodotti.
            @endif
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6 text-left space-y-4">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Riepilogo ordine #{{ $order->id }}</h2>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($order->items as $item)
                    <tr>
                        <td class="py-3 text-gray-900 dark:text-gray-100">{{ $item->product?->name ?? 'Prodotto' }}</td>
                        <td class="py-3 text-center text-gray-600 dark:text-gray-400">× {{ $item->quantity }}</td>
                        <td class="py-3 text-right font-medium text-gray-900 dark:text-gray-100">
                            {{ number_format($item->subtotal, 2, ',', '.') }} €
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td colspan="2" class="pt-3 font-semibold text-right text-gray-900 dark:text-gray-100">Totale</td>
                    <td class="pt-3 text-right font-bold text-gray-950 dark:text-gray-50">
                        {{ number_format($order->total, 2, ',', '.') }} €
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex justify-center gap-4">
        <a href="{{ route('portal.products.index') }}" class="text-sm text-gray-600 hover:underline dark:text-gray-400">
            Continua gli acquisti
        </a>
        <a href="{{ route('portal.orders.index') }}" class="btn-primary rounded-md px-5 py-2 text-sm font-semibold text-white">
            I miei ordini
        </a>
    </div>
</section>
@endsection
```

- [ ] **Step 4: Run the full test suite**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add resources/views/portal/products/
git commit -m "feat: add portal checkout, payment, and confirmation views"
```

---

## Task 13: Portal — Order History

**Files:**
- Create: `app/Http/Controllers/Portal/ProductOrderController.php`
- Create: `resources/views/portal/orders/index.blade.php`

- [ ] **Step 1: Create ProductOrderController**

```php
<?php
// app/Http/Controllers/Portal/ProductOrderController.php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = ProductOrder::where('user_id', $request->user()->id)
            ->with('items.product')
            ->latest()
            ->get();

        return view('portal.orders.index', compact('orders'));
    }
}
```

- [ ] **Step 2: Create order history view**

```blade
{{-- resources/views/portal/orders/index.blade.php --}}
@extends('layouts.app')

@section('title', 'I miei ordini')

@section('content')
<section class="space-y-8">
    <div>
        <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">I miei ordini</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Storico acquisti prodotti.</p>
    </div>

    @if ($orders->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">Nessun ordine ancora.</p>
    @else
        <div class="space-y-4">
            @foreach ($orders as $order)
                @php
                    $statusLabels = [
                        'pending'   => 'In attesa di pagamento',
                        'confirmed' => 'Confermato',
                        'ready'     => 'Pronto per il ritiro',
                        'completed' => 'Completato',
                        'cancelled' => 'Cancellato',
                    ];
                    $statusColors = [
                        'pending'   => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                        'confirmed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                        'ready'     => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                        'completed' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                        'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                    ];
                @endphp
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-5 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Ordine #{{ $order->id }} · {{ $order->created_at->format('d/m/Y H:i') }}
                            </p>
                            <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                                {{ number_format($order->total, 2, ',', '.') }} €
                            </p>
                        </div>
                        <span class="inline-block rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$order->status] ?? '' }}">
                            {{ $statusLabels[$order->status] ?? $order->status }}
                        </span>
                    </div>
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        @foreach ($order->items as $item)
                            <li>{{ $item->product?->name ?? 'Prodotto' }} × {{ $item->quantity }} — {{ number_format($item->subtotal, 2, ',', '.') }} €</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Portal/ProductOrderController.php \
        resources/views/portal/orders/index.blade.php
git commit -m "feat: add portal order history"
```

---

## Task 14: Routes and Navigation

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Add portal routes to `routes/web.php`**

Inside the authenticated `middleware(['auth', ...])` group where the other portal routes are (the block with `Route::get('/portal', ...)`), add:

```php
use App\Http\Controllers\Portal\ProductController;
use App\Http\Controllers\Portal\ProductOrderController;

// Products
Route::get('/portal/products', [ProductController::class, 'index'])->name('portal.products.index');
Route::post('/portal/cart', [ProductController::class, 'cartUpdate'])->name('portal.cart.update');
Route::delete('/portal/cart/{productId}', [ProductController::class, 'cartRemove'])->name('portal.cart.remove');
Route::get('/portal/products/checkout', [ProductController::class, 'checkout'])->name('portal.products.checkout');
Route::post('/portal/products/checkout', [ProductController::class, 'placeOrder'])->name('portal.products.order');
Route::get('/portal/products/{orderId}/payment', [ProductController::class, 'payment'])->name('portal.products.payment');
Route::get('/portal/products/{orderId}/stripe-confirm', [ProductController::class, 'confirmStripePayment'])->name('portal.products.stripe-confirm');
Route::get('/portal/products/{orderId}/confirmation', [ProductController::class, 'confirmation'])->name('portal.products.confirmation');

// Orders
Route::get('/portal/orders', [ProductOrderController::class, 'index'])->name('portal.orders.index');
```

- [ ] **Step 2: Add "Prodotti" nav link in `resources/views/layouts/app.blade.php`**

In the desktop nav block, after the "Appuntamenti" link:
```blade
<a href="{{ route('portal.products.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Prodotti</a>
```

In the mobile nav block, after the "Appuntamenti" mobile link:
```blade
<a href="{{ route('portal.products.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Prodotti</a>
```

- [ ] **Step 3: Run full test suite**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: all tests pass, including the 5 portal product tests.

- [ ] **Step 4: Commit**

```bash
git add routes/web.php resources/views/layouts/app.blade.php
git commit -m "feat: add portal product and order routes and navigation links"
```

---

## Self-Review Checklist

Spec coverage verified:
- ✅ Admin CRUD for products (name, description, price, stock, threshold, photo, in_sale, active) — Task 8
- ✅ Stock count, decremented on purchase — Task 5
- ✅ Low stock notification (threshold per product, configurable recipients, email) — Tasks 4, 6
- ✅ Stripe webhook routing for product orders — Task 7
- ✅ Portal catalog with Esaurito badge when stock = 0 — Task 11
- ✅ Cart in session — Task 11
- ✅ Checkout with payment method from SystemSetting.payment_mode — Task 12
- ✅ Ritiro in salone (no shipping) — Task 12
- ✅ Order lifecycle (pending → confirmed → ready → completed / cancelled) — Tasks 5, 9
- ✅ Admin can advance order status, cancel, restore stock — Task 9
- ✅ Portal order history — Task 13
- ✅ Deletion guard (only if no orders) — Task 8
- ✅ `getLowStockNotifyUserIds` in SystemSetting — Task 4
- ✅ SystemSettings Filament section for recipients — Task 10
