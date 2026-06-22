<?php

namespace App\Filament\Pages;

use App\Models\Business;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class BillingPage extends Page
{
    protected static ?string $navigationLabel = 'Abbonamento';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $slug = 'abbonamento';
    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.billing';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getBusiness(): Business
    {
        return once(fn () => Business::findOrFail(Business::currentId()));
    }

    public function mount(): void
    {
        if (request()->query('checkout') === 'success') {
            Notification::make()
                ->title('Abbonamento attivato con successo!')
                ->success()
                ->send();
        }

        if (request()->query('checkout') === 'cancelled') {
            Notification::make()
                ->title('Pagamento annullato.')
                ->warning()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $business = $this->getBusiness();

        if (! Auth::user()?->isAdmin()) {
            return [];
        }

        $status = $business->subscriptionStatus();

        return match ($status) {
            'trial', 'expired' => [
                Action::make('subscribe')
                    ->label($status === 'trial' ? 'Attiva abbonamento' : 'Abbonati ora — €29/mese')
                    ->color('primary')
                    ->icon('heroicon-o-credit-card')
                    ->action(function () use ($business) {
                        $session = $business->newSubscription('default', config('cashier.price_id'))
                            ->checkout([
                                'success_url' => route('filament.admin.pages.abbonamento', ['tenant' => $business->subdomain]) . '?checkout=success',
                                'cancel_url'  => route('filament.admin.pages.abbonamento', ['tenant' => $business->subdomain]) . '?checkout=cancelled',
                            ]);
                        $this->redirect($session->url, navigate: false);
                    }),
            ],
            'active' => [
                Action::make('cancel')
                    ->label('Annulla abbonamento')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Annulla abbonamento')
                    ->modalDescription('L\'abbonamento rimarrà attivo fino alla fine del periodo corrente. Sei sicuro?')
                    ->action(function () use ($business) {
                        $subscription = $business->subscription('default');
                        $subscription->cancel();
                        $endsAt = $subscription->fresh()?->ends_at?->format('d/m/Y');
                        Notification::make()
                            ->title("Abbonamento annullato. Accesso garantito fino al {$endsAt}.")
                            ->warning()
                            ->send();
                    }),
            ],
            'grace_period' => [
                Action::make('resume')
                    ->label('Riattiva abbonamento')
                    ->color('success')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () use ($business) {
                        $business->subscription('default')->resume();
                        Notification::make()
                            ->title('Abbonamento riattivato!')
                            ->success()
                            ->send();
                    }),
            ],
            default => [],
        };
    }
}
