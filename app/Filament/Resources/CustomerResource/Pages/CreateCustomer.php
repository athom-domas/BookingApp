<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $record = parent::handleRecordCreation($data);
        $record->syncRoles(['customer']);
        return $record;
    }
}
