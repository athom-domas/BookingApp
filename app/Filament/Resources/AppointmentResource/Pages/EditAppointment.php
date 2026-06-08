<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Exceptions\BookingException;
use App\Filament\Resources\AppointmentResource;
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

        $data['has_completed_payment'] = $payment?->status === 'completed';
        $data['payment_method']        = $payment?->payment_method;
        $data['payment_amount']        = $payment?->status === 'completed'
            ? $payment->amount
            : $this->record->final_price;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['payment_method'], $data['payment_amount'], $data['has_completed_payment'], $data['apply_loyalty_discount']);

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

        $amount = (float) ($data['payment_amount'] ?? 0);

        if (! empty($data['apply_loyalty_discount'])) {
            $percentage = app(LoyaltyService::class)->redeem($this->record);
            if ($percentage > 0) {
                $amount = round($amount * (1 - $percentage / 100), 2);
            }
        }

        try {
            app(PaymentService::class)->recordInPersonPayment(
                $this->record->id,
                $data['payment_method'],
                $amount
            );
        } catch (BookingException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();
            $this->halt();
        }
    }
}
