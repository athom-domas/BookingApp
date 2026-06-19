<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class WalkInService
{
    public function createInlineCustomer(string $name, ?string $email, int $businessId): User
    {
        $email ??= 'walkin_' . Str::ulid() . '@noreply.local';

        $user = User::create([
            'name'        => $name,
            'email'       => $email,
            'password'    => Str::random(16),
            'business_id' => $businessId,
        ]);
        $user->assignRole('customer');

        return $user;
    }
}
