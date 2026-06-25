<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\StripeConnectAccount;
use App\Services\StripeConnectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StripeConnectController extends Controller
{
    public function __construct(private readonly StripeConnectService $connectService) {}

    public function start(Request $request): RedirectResponse
    {
        $business = Business::findOrFail(Business::currentId());
        $account  = $this->connectService->createAccount($business);

        $url = $this->connectService->createAccountLink(
            $account,
            route('stripe.connect.callback'),
            route('stripe.connect.refresh'),
        );

        return redirect($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $account = StripeConnectAccount::where('business_id', Business::currentId())->first();

        if ($account && $account->stripe_account_id) {
            $this->connectService->syncFromStripe($account);
        }

        return redirect('/admin')
            ->with('status', 'Configurazione completata. Stripe sta verificando i tuoi dati.');
    }

    public function refresh(Request $request): RedirectResponse
    {
        return $this->start($request);
    }

    public function dashboardLink(Request $request): RedirectResponse
    {
        $account = StripeConnectAccount::where('business_id', Business::currentId())
            ->whereNotNull('stripe_account_id')
            ->firstOrFail();

        $url = $this->connectService->createDashboardLink($account);

        return redirect($url);
    }
}
