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
            'business_id' => 1,
            'user_id'     => User::factory(),
            'start_date'  => '2026-07-14',
            'end_date'    => '2026-07-18',
            'reason'      => null,
        ];
    }
}
