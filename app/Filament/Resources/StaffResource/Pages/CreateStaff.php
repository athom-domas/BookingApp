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
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $record = parent::handleRecordCreation($data);
        $record->syncRoles(['staff']);

        return $record;
    }
}
