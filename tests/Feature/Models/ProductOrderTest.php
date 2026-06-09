<?php

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
