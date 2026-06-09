<?php

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
