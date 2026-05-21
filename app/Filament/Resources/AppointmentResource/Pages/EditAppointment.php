<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Exceptions\BookingException;
use App\Filament\Resources\AppointmentResource;
use App\Services\PaymentService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getFormActions(): array
    {
        if (in_array($this->record->status, ['completed', 'cancelled'])) {
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
        unset($data['payment_method'], $data['payment_amount'], $data['has_completed_payment']);

        return $data;
    }

    protected function beforeSave(): void
    {
        $data = $this->form->getState();

        if ($data['status'] !== 'completed') {
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

        try {
            app(PaymentService::class)->recordInPersonPayment(
                $this->record->id,
                $data['payment_method'],
                (float) ($data['payment_amount'] ?? 0)
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
