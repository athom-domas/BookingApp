<?php

namespace App\Services;

use App\Exceptions\ProductOrderException;
use App\Jobs\SendLowStockNotificationJob;
use App\Jobs\SendOrderReceivedNotificationJob;
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
        $lowStockProducts = [];

        $order = DB::transaction(function () use ($userId, $items, $paymentMethod, $notes, &$lowStockProducts) {
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

                if ($product->low_stock_threshold !== null
                    && $previousStock > $product->low_stock_threshold
                    && $product->stock <= $product->low_stock_threshold) {
                    $lowStockProducts[] = $product->id;
                }
            }

            return $order->load('items.product');
        });

        // Dispatch notifications after transaction commits
        $lowStockUserIds = SystemSetting::getLowStockNotifyUserIds();
        if (! empty($lowStockUserIds) && ! empty($lowStockProducts)) {
            foreach ($lowStockProducts as $productId) {
                $product = Product::withoutGlobalScopes()->find($productId);
                if ($product) {
                    SendLowStockNotificationJob::dispatch($product, $lowStockUserIds);
                }
            }
        }

        $orderUserIds = SystemSetting::getOrderNotifyUserIds();
        if (! empty($orderUserIds)) {
            SendOrderReceivedNotificationJob::dispatch($order, $orderUserIds);
        }

        return $order;
    }

    public function createStripePaymentIntent(ProductOrder $order): string
    {
        $order->loadMissing('items');
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
            $locked = ProductOrder::where('id', $order->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isCancellable()) {
                return;
            }

            $locked->loadMissing('items');

            foreach ($locked->items as $item) {
                Product::where('id', $item->product_id)->increment('stock', $item->quantity);
            }

            $refundStatus = 'cancelled';

            if ($locked->payment_status === 'paid' && $locked->stripe_payment_intent_id) {
                try {
                    $this->stripe->refunds->create([
                        'payment_intent' => $locked->stripe_payment_intent_id,
                    ]);
                    $refundStatus = 'refunded';
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Stripe refund failed', [
                        'order_id' => $locked->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            $locked->update(['status' => 'cancelled', 'payment_status' => $refundStatus]);
        });
    }

}
