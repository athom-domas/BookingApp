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
    protected static ?int $navigationSort = 4;

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
        if (! Auth::user()?->isAdmin()) {
            return [];
        }

        $business = $this->getBusiness();
        $status   = $business->subscriptionStatus();

        return match (true) {
            in_array($status, ['trial', 'expired']) => [
                Action::make('subscribeBase')
                    ->label('Attiva Base')
                    ->color('gray')
                    ->icon('heroicon-o-credit-card')
                    ->action(fn () => $this->checkoutRedirect('base')),

                Action::make('subscribePlus')
                    ->label('Attiva Plus')
                    ->color('primary')
                    ->icon('heroicon-o-rocket-launch')
                    ->action(fn () => $this->checkoutRedirect('plus')),
            ],

            $status === 'active' && $business->effectivePlan() === 'base' => [
                Action::make('upgradePlus')
                    ->label('Passa a Plus')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->action(fn () => $this->swapPlan('plus')),

                Action::make('cancel')
                    ->label('Annulla abbonamento')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Annulla abbonamento')
                    ->modalDescription("L'abbonamento rimarrà attivo fino alla fine del periodo corrente.")
                    ->action(fn () => $this->cancelSubscription()),
            ],

            $status === 'active' && $business->effectivePlan() === 'plus' => [
                Action::make('downgradeBase')
                    ->label('Torna a Base')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Torna al piano Base')
                    ->modalDescription('Il downgrade è immediato. WhatsApp AI verrà disattivato subito. Sei sicuro?')
                    ->action(fn () => $this->swapPlan('base')),

                Action::make('cancel')
                    ->label('Annulla abbonamento')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Annulla abbonamento')
                    ->modalDescription("L'abbonamento rimarrà attivo fino alla fine del periodo corrente.")
                    ->action(fn () => $this->cancelSubscription()),
            ],

            $status === 'grace_period' => [
                Action::make('resume')
                    ->label('Riattiva abbonamento')
                    ->color('success')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn () => $this->resumeSubscription()),
            ],

            default => [],
        };
    }

    private function checkoutRedirect(string $plan): void
    {
        $priceId = config("plans.{$plan}.price_id");

        if (! $priceId) {
            Notification::make()
                ->title("Prezzo per il piano {$plan} non ancora configurato.")
                ->danger()
                ->send();
            return;
        }

        $business = $this->getBusiness();
        $session  = $business->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('filament.admin.pages.abbonamento', ['tenant' => $business->subdomain]) . '?checkout=success',
                'cancel_url'  => route('filament.admin.pages.abbonamento', ['tenant' => $business->subdomain]) . '?checkout=cancelled',
            ]);

        $this->redirect($session->url, navigate: false);
    }

    private function swapPlan(string $plan): void
    {
        $priceId = config("plans.{$plan}.price_id");

        if (! $priceId) {
            Notification::make()
                ->title("Prezzo per il piano {$plan} non ancora configurato.")
                ->danger()
                ->send();
            return;
        }

        $business     = $this->getBusiness();
        $subscription = $business->subscription('default');
        if (! $subscription) {
            Notification::make()->title('Nessun abbonamento attivo.')->warning()->send();
            return;
        }
        $subscription->swapAndInvoice($priceId);

        $freshBusiness = $business->fresh();
        if ($freshBusiness->subscribed('default') && ! $freshBusiness->hasIncompletePayment('default')) {
            $business->update(['plan' => $plan]);
            Notification::make()
                ->title('Piano aggiornato con successo.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Pagamento in sospeso — il piano sarà aggiornato a breve.')
                ->warning()
                ->send();
        }
    }

    private function cancelSubscription(): void
    {
        $business     = $this->getBusiness();
        $subscription = $business->subscription('default');
        if (! $subscription) {
            Notification::make()->title('Nessun abbonamento da annullare.')->warning()->send();
            return;
        }
        $subscription->cancel();
        $endsAt = $subscription->fresh()?->ends_at?->format('d/m/Y');
        Notification::make()
            ->title("Abbonamento annullato. Accesso garantito fino al {$endsAt}.")
            ->warning()
            ->send();
    }

    private function resumeSubscription(): void
    {
        $subscription = $this->getBusiness()->subscription('default');
        if (! $subscription) {
            Notification::make()->title('Nessun abbonamento da riattivare.')->warning()->send();
            return;
        }
        $subscription->resume();
        Notification::make()
            ->title('Abbonamento riattivato!')
            ->success()
            ->send();
    }
}
