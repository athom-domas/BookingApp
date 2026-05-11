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

    Carbon::setTestNow('2026-05-10 00:00:00'); // Sunday
    $expected = '2026-05-11'; // the Monday immediately after

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->once()
        ->with($staff->id, Mockery::on(fn (Carbon $d) =>
            $d->format('Y-m-d') === $expected
        ))
        ->andReturn(5);

    (new GenerateWeeklySlots())->handle($mockGenerator);

    Carbon::setTestNow(); // reset
});

it('GenerateWeeklySlots failed hook logs the error', function () {
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with('GenerateWeeklySlots failed', \Mockery::on(fn ($ctx) =>
            isset($ctx['error'])
        ));

    $job = new GenerateWeeklySlots();
    $job->failed(new \Exception('DB error'));
});
