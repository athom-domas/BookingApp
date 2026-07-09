<?php

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

    $this->actingAs($customer)->get('/prodotti')
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
        ->post('/prodotti/carrello', ['product_id' => $product->id, 'quantity' => 2])
        ->assertRedirect('/prodotti');

    $this->actingAs($customer)->get('/prodotti')
        ->assertSessionHas('product_cart', [$product->id => 2]);
});

it('does not add more than available stock to cart', function () {
    $product  = Product::factory()->create(['stock' => 3]);
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->post('/prodotti/carrello', ['product_id' => $product->id, 'quantity' => 10])
        ->assertRedirect();

    $this->actingAs($customer)->get('/prodotti')
        ->assertSessionMissing('product_cart.' . $product->id);
});

it('shows the checkout page with cart contents', function () {
    $product  = Product::factory()->create(['name' => 'Balsamo Test', 'stock' => 5, 'price' => 15.00]);
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->withSession(['product_cart' => [$product->id => 2]])
        ->get('/checkout')
        ->assertOk()
        ->assertSee('Balsamo Test')
        ->assertSee('30,00');
});

it('redirects to products page when cart is empty on checkout', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->get('/checkout')
        ->assertRedirect('/prodotti');
});

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
