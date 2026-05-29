<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\Pages;

use App\Filament\SuperAdmin\Resources\BusinessResource;
use App\Services\BusinessProvisioningService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    private string $adminEmail = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->adminEmail = $data['admin_email'];
        unset($data['admin_email']);
        $data['trial_ends_at'] = now()->addDays(14);
        return $data;
    }

    protected function afterCreate(): void
    {
        $admin = (new BusinessProvisioningService())->provision($this->record, $this->adminEmail);

        Notification::make()
            ->title("Salone creato — Admin: {$admin->email} — Password: {$admin->plainPassword}")
            ->success()
            ->persistent()
            ->send();
    }
}
