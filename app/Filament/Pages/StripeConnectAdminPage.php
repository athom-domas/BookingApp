<?php

namespace App\Filament\Pages;

use App\Models\StripeConnectAccount;
use App\Services\StripeConnectService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class StripeConnectAdminPage extends Page
{
    protected string $view = 'filament.pages.stripe-connect-admin';

    protected static ?string $navigationLabel = 'Stripe Connect';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';
    protected static string|\UnitEnum|null $navigationGroup = 'Piattaforma';
    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        if (! auth()->user()?->isAdmin()) {
            return false;
        }
        $superAdminIds = array_filter(array_map('intval', explode(',', config('services.stripe.super_admin_user_ids', ''))));
        if (empty($superAdminIds)) {
            return false;
        }
        return in_array(auth()->id(), $superAdminIds, true);
    }

    public function getAccounts()
    {
        return StripeConnectAccount::with('business')->latest()->get();
    }

    public function syncAccount(int $id): void
    {
        if (! static::canAccess()) {
            abort(403);
        }

        $account = StripeConnectAccount::findOrFail($id);
        app(StripeConnectService::class)->syncFromStripe($account);

        Notification::make()
            ->title('Account sincronizzato')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
