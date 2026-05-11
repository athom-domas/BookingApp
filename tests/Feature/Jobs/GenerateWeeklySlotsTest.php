<?php

use App\Jobs\GenerateWeeklySlots;
use App\Models\AvailabilityRule;
use App\Models\User;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Mockery;

it('GenerateWeeklySlots calls generator for each staff with availability rules', function () {
    $staff1 = User::factory()->create();
    $staff2 = User::factory()->create();
    User::factory()->create(); // user with no availability rules

    AvailabilityRule::factory()->create(['user_id' => $staff1->id, 'is_available' => true]);
    AvailabilityRule::factory()->create(['user_id' => $staff2->id, 'is_available' => true]);

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->twice()
        ->with(Mockery::type('int'), Mockery::on(fn ($d) => $d instanceof Carbon))
        ->andReturn(8);

    (new GenerateWeeklySlots())->handle($mockGenerator);
});

it('GenerateWeeklySlots targets the next Monday week', function () {
    $staff = User::factory()->create();
    AvailabilityRule::factory()->create(['user_id' => $staff->id, 'is_available' => true]);

    $expectedWeekStart = Carbon::now()->startOfWeek()->addWeek();

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->once()
        ->with($staff->id, Mockery::on(fn (Carbon $d) =>
            $d->format('Y-m-d') === $expectedWeekStart->format('Y-m-d')
        ))
        ->andReturn(5);

    (new GenerateWeeklySlots())->handle($mockGenerator);
});
