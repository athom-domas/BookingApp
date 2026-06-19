<?php

namespace Database\Factories;

use App\Models\StaffBlockout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffBlockout> */
class StaffBlockoutFactory extends Factory
{
    protected $model = StaffBlockout::class;

    public function definition(): array
    {
        return [
            'business_id' => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'user_id'     => User::factory(),
            'start_date'  => today(),
            'end_date'    => today(),
            'start_time'  => null,
            'end_time'    => null,
            'reason'      => null,
        ];
    }
}
