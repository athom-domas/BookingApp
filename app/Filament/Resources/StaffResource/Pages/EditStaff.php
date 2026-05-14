<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    private int $pendingSlotDurationMinutes = 60;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageAvailability')
                ->label('Gestisci Disponibilità')
                ->icon('heroicon-o-clock')
                ->url(fn () => StaffResource::getUrl('manage-availability', ['record' => $this->getRecord()])),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['password'] = null;
        $data['password_confirmation'] = null;
        $data['slot_duration_minutes'] = $this->getRecord()->preferences->slot_duration_minutes ?? 60;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        unset($data['password_confirmation']);

        $this->pendingSlotDurationMinutes = $data['slot_duration_minutes'] ?? 60;
        unset($data['slot_duration_minutes']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->getRecord()->preferences()->updateOrCreate([], ['slot_duration_minutes' => $this->pendingSlotDurationMinutes]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $record = parent::handleRecordUpdate($record, $data);
        $record->syncRoles(['staff']);

        return $record;
    }
}
