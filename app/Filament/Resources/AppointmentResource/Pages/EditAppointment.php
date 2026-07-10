<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Exceptions\BookingException;
use App\Filament\Resources\AppointmentResource;
use App\Models\SystemSetting;
use App\Services\LoyaltyService;
use App\Services\PaymentService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getFormActions(): array
    {
        $record = $this->record;

        if ($record->status === 'completed' && $record->payment?->status !== 'refunded') {
            return [];
        }

        return parent::getFormActions();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn () => auth()->user()?->isStaff()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $payment = $this->record->payment;

        $data['has_completed_payment']      = $payment?->status === 'completed';
        $data['payment_method']             = $payment?->payment_method;
        $data['payment_amount']             = $payment?->status === 'completed'
            ? $payment->amount
            : $this->record->final_price;
        $data['discounted_amount']          = $payment?->status === 'completed'
            ? $payment->amount
            : null;
        $data['loyalty_discount_percentage'] = $payment?->status === 'completed'
            ? $payment->loyalty_discount_percentage
            : null;

        // Pre-caricato qui (richiesta HTTP completa) per non fare query DB nei callback
        // ->visible() che girano durante i live update Livewire senza contesto tenant garantito.
        $data['customer_loyalty_points'] = (int) (
            \App\Models\LoyaltyAccount::where('user_id', $this->record->user_id)->value('points') ?? 0
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['payment_method'], $data['payment_amount'], $data['discounted_amount'], $data['has_completed_payment'], $data['apply_loyalty_discount'], $data['loyalty_discount_percentage'], $data['loyalty_tier_threshold'], $data['loyalty_tier_index']);

        return $data;
    }

    protected function beforeSave(): void
    {
        // $this->data (stato grezzo del form) include sempre tutti i campi;
        // getState() può ometterne (campi disabilitati/non idratati) e far mancare 'status'.
        $data = $this->data;

        if (($data['status'] ?? null) !== 'completed') {
            return;
        }

        if (optional($this->record->payment)->status === 'completed') {
            return;
        }

        if (empty($data['payment_method'])) {
            Notification::make()
                ->title('Per completare la prenotazione è necessario registrare un pagamento.')
                ->danger()
                ->send();
            $this->halt();
            return;
        }

        // payment_amount contiene già l'importo scontato (aggiornato in tempo reale dal toggle via afterStateUpdated).
        // Qui occorre solo scalare i punti; non ricalcolare lo sconto.
        $amount            = (float) ($data['discounted_amount'] ?? $data['payment_amount'] ?? 0);
        $discountResult    = ['percentage' => 0, 'amount' => null];
        $originalAmount    = (float) ($data['pre_discount_amount'] ?? $this->record->final_price ?? $amount);
        $threshold         = isset($data['loyalty_tier_threshold']) ? (int) $data['loyalty_tier_threshold'] : null;

        if ($threshold) {
            $points = (int) (\App\Models\LoyaltyAccount::where('user_id', $this->record->user_id)->value('points') ?? 0);
            $tiers  = SystemSetting::getAvailableTiers($points);
            $tier   = collect($tiers)->firstWhere('threshold', $threshold);
            if ($tier) {
                $discountResult = app(LoyaltyService::class)->redeem($this->record, $tier);
            }
        }

        try {
            $payment = app(PaymentService::class)->recordInPersonPayment(
                $this->record->id,
                $data['payment_method'],
                $amount
            );

            if (($discountResult['percentage'] ?? 0) > 0 || ($discountResult['amount'] ?? 0) > 0) {
                $payment->update([
                    'loyalty_discount_percentage' => $discountResult['percentage'] ?: null,
                    'loyalty_original_amount'     => $originalAmount,
                ]);
                $this->record->update(['loyalty_discounted_price' => $amount, 'final_price' => $originalAmount]);
            } else {
                $this->record->update(['final_price' => $amount]);
            }
        } catch (BookingException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();
            $this->halt();
        }
    }
}
