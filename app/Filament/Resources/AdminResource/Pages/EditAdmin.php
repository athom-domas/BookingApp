<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\StaffResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageAvailability')
                ->label('Gestisci Disponibilità')
                ->color('primary')
                ->icon('heroicon-o-clock')
                ->visible(fn() => $this->getRecord()->hasRole('staff'))
                ->url(fn() => StaffResource::getUrl('manage-availability', ['record' => $this->getRecord()])),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['works_as_staff'] = $this->getRecord()->hasRole('staff');
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $worksAsStaff = $data['works_as_staff'] ?? false;
        unset($data['works_as_staff']);

        $record = parent::handleRecordUpdate($record, $data);

        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        if ($worksAsStaff) {
            if (!$record->hasRole('staff')) {
                $record->assignRole('staff');
            }
        } elseif ($record->hasRole('staff')) {
            $hasUpcoming = $record->appointmentsAsStaff()
                ->where('status', 'confirmed')
                ->where('scheduled_date', '>=', now())
                ->exists();

            if ($hasUpcoming) {
                Notification::make()
                    ->title('Attenzione: appuntamenti futuri confermati')
                    ->body('Questo admin ha appuntamenti confermati futuri come staff. Verifica e gestisci gli appuntamenti prima di disattivare il ruolo staff.')
                    ->warning()
                    ->send();
            }

            $record->removeRole('staff');
        }

        return $record;
    }
}
