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
