<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\Pages;

use App\Filament\SuperAdmin\Resources\BusinessResource;
use App\Models\User;
use App\Services\BusinessProvisioningService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    private string $adminType = 'new';
    private string $adminEmail = '';
    private ?int $adminExistingId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->adminType = $data['admin_type'] ?? 'new';
        $this->adminEmail = $data['admin_email'] ?? '';
        $this->adminExistingId = isset($data['admin_existing_id']) ? (int) $data['admin_existing_id'] : null;

        unset($data['admin_type'], $data['admin_email'], $data['admin_existing_id']);
        $data['trial_ends_at'] = now()->addDays(14);

        return $data;
    }

    protected function afterCreate(): void
    {
        $service = new BusinessProvisioningService();

        if ($this->adminType === 'existing' && $this->adminExistingId) {
            $existingAdmin = User::findOrFail($this->adminExistingId);
            $service->provisionWithExistingAdmin($this->record, $existingAdmin);

            Notification::make()
                ->title("Salone creato — Admin: {$existingAdmin->email}")
                ->success()
                ->persistent()
                ->send();
        } else {
            $admin = $service->provision($this->record, $this->adminEmail);

            Notification::make()
                ->title("Salone creato — Admin: {$admin->email} — Password: {$admin->plainPassword}")
                ->success()
                ->persistent()
                ->send();
        }
    }
}
