<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\ProductOrderException;
use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\SystemSetting;
use App\Services\ProductOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\StripeClient;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductOrderService $service,
        private readonly StripeClient $stripe,
    ) {}

    public function index(): View
    {
        $products = Product::inSale()->with('media')->orderBy('name')->get();
        $cart     = session('product_cart', []);

        $cartItems = collect($cart)->map(function (int $qty, int $productId) use ($products) {
            $product = $products->firstWhere('id', $productId);
            return $product ? ['product' => $product, 'quantity' => $qty] : null;
        })->filter()->values();

        return view('shop.index', compact('products', 'cartItems'));
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

        return redirect()->route('shop.index');
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
            return redirect()->route('shop.index');
        }

        $products  = Product::whereIn('id', array_keys($cart))->with('media')->get()->keyBy('id');
        $cartItems = collect($cart)->map(fn ($qty, $id) => [
            'product'  => $products->get($id),
            'quantity' => $qty,
        ])->filter(fn ($item) => $item['product'] !== null)->values();

        if ($cartItems->isEmpty()) {
            session()->forget('product_cart');
            return redirect()->route('shop.index');
        }

        $total       = $cartItems->sum(fn ($item) => $item['product']->price * $item['quantity']);
        $paymentMode = SystemSetting::getPaymentMode();

        return view('shop.checkout', compact('cartItems', 'total', 'paymentMode'));
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $cart = session('product_cart', []);

        if (empty($cart)) {
            return redirect()->route('shop.index');
        }

        $rules = ['notes' => ['nullable', 'string', 'max:1000']];
        $paymentMode = SystemSetting::getPaymentMode();

        if ($paymentMode === 'both') {
            $rules['payment_method'] = ['required', 'in:stripe,cash'];
        }

        $validated     = $request->validate($rules);
        $paymentMethod = match ($paymentMode) {
            'online'   => 'stripe',
            'in_salon' => 'cash',
            default    => $validated['payment_method'],
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
            return redirect()->route('shop.payment', $order)->with('stripe_client_secret', $clientSecret);
        }

        return redirect()->route('shop.confirmation', $order);
    }

    public function payment(Request $request, int $orderId): View|RedirectResponse
    {
        $order = ProductOrder::where('user_id', $request->user()->id)
            ->with('items.product')
            ->findOrFail($orderId);

        if ($order->payment_status === 'paid') {
            return redirect()->route('shop.confirmation', $order);
        }

        $clientSecret    = session('stripe_client_secret');
        $stripePublicKey = IntegrationSetting::getStripePublicKey() ?? config('services.stripe.public');

        return view('shop.payment', compact('order', 'clientSecret', 'stripePublicKey'));
    }

    public function confirmStripePayment(Request $request, int $orderId): RedirectResponse
    {
        $order = ProductOrder::where('user_id', $request->user()->id)->findOrFail($orderId);

        if ($order->payment_status === 'paid') {
            return redirect()->route('shop.confirmation', $order);
        }

        if ($order->stripe_payment_intent_id) {
            try {
                $pi = $this->stripe->paymentIntents->retrieve($order->stripe_payment_intent_id);
                if ($pi->status === 'succeeded') {
                    $this->service->confirmStripePayment($order->stripe_payment_intent_id);
                    return redirect()->route('shop.confirmation', $order);
                }
            } catch (\Exception $e) {
                // Stripe API error — fall through
            }
        }

        return redirect()->route('shop.payment', $order)
            ->with('status', 'Pagamento in elaborazione. Attendi la conferma.');
    }

    public function confirmation(Request $request, int $orderId): View
    {
        $order = ProductOrder::where('user_id', $request->user()->id)
            ->with('items.product')
            ->findOrFail($orderId);

        return view('shop.confirmation', compact('order'));
    }
}
