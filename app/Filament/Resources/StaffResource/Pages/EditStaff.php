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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageAvailability')
                ->label('Gestisci Disponibilità')
                ->color('primary')
                ->icon('heroicon-o-clock')
                ->url(fn() => StaffResource::getUrl('manage-availability', ['record' => $this->getRecord()])),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['password'] = null;
        $data['password_confirmation'] = null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        unset($data['password_confirmation']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $record = parent::handleRecordUpdate($record, $data);

        if (!$record->hasRole('staff')) {
            $record->assignRole('staff');
        }

        return $record;
    }

    protected function afterSave(): void
    {
        $rawState = $this->form->getRawState();
        if (! array_key_exists('appointments_visibility', $rawState)) {
            return;
        }

        $perms = [];

        if (($rawState['appointments_visibility'] ?? 'personal') === 'all') {
            $perms[] = 'appointments.view_all';
        }

        $management = $rawState['appointments_management'] ?? 'view_only';
        if (in_array($management, ['create', 'full', 'full_delete'])) {
            $perms[] = 'appointments.create';
        }
        if (in_array($management, ['full', 'full_delete'])) {
            $perms[] = 'appointments.edit';
            $perms[] = 'appointments.payments';
        }
        if ($management === 'full_delete') {
            $perms[] = 'appointments.delete';
        }

        $customers = $rawState['customers_management'] ?? 'none';
        if ($customers !== 'none') {
            $perms[] = 'customers.view';
        }
        if (in_array($customers, ['create', 'full', 'full_delete'])) {
            $perms[] = 'customers.create';
        }
        if (in_array($customers, ['full', 'full_delete'])) {
            $perms[] = 'customers.edit';
        }
        if ($customers === 'full_delete') {
            $perms[] = 'customers.delete';
        }

        $reports = $rawState['reports_visibility'] ?? 'none';
        if (in_array($reports, ['no_revenue', 'full'])) {
            $perms[] = 'reports.view';
        }
        if ($reports === 'full') {
            $perms[] = 'reports.view_revenue';
        }

        $this->record->syncPermissions($perms);
    }
}
