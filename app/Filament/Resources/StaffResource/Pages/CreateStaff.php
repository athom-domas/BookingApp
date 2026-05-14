<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $slotDuration = $data['slot_duration_minutes'] ?? 60;
        unset($data['slot_duration_minutes']);

        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $record = parent::handleRecordCreation($data);
        $record->syncRoles(['staff']);
        $record->preferences()->create(['slot_duration_minutes' => $slotDuration]);

        return $record;
    }
}
