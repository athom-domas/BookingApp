<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function beforeSave(): void
    {
        $data = $this->form->getState();

        if ($data['status'] === 'completed' && $this->record->payment?->status !== 'completed') {
            Notification::make()
                ->title('Per completare la prenotazione è necessario registrare un pagamento.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
