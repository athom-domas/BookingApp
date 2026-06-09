<?php

use App\Exceptions\ProductOrderException;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\ProductOrderService;

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

it('throws when product is inactive', function () {
    $product = Product::factory()->create(['stock' => 10, 'active' => false]);
    $user    = User::factory()->create();

    expect(fn () => $this->service->createOrder($user->id, [
        ['product_id' => $product->id, 'quantity' => 1],
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
        'status'                   => 'pending',
        'payment_method'           => 'stripe',
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
