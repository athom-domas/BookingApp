<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $worksAsStaff = $data['works_as_staff'] ?? false;
        unset($data['works_as_staff']);

        $record = parent::handleRecordCreation($data);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $record->assignRole('admin');

        if ($worksAsStaff) {
            Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
            $record->assignRole('staff');
        }

        return $record;
    }
}
