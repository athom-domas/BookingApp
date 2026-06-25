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
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getAccounts()
    {
        return StripeConnectAccount::with('business')->latest()->get();
    }

    public function syncAccount(int $id): void
    {
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
