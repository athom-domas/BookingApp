<?php

use App\Jobs\GenerateWeeklySlots;
use App\Models\AvailabilityRule;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Mockery;

beforeEach(function () {
    SystemSetting::current()->update(['slot_generation_weeks' => 1]);
});

it('GenerateWeeklySlots calls generator for each staff with availability rules', function () {
    $staff1 = User::factory()->create();
    $staff2 = User::factory()->create();
    User::factory()->create(); // user with no availability rules

    AvailabilityRule::factory()->create(['user_id' => $staff1->id, 'is_available' => true]);
    AvailabilityRule::factory()->create(['user_id' => $staff2->id, 'is_available' => true]);

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->twice()
        ->with(Mockery::type('int'), Mockery::on(fn ($d) => $d instanceof Carbon), Mockery::type('int'))
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
        ), Mockery::type('int'))
        ->andReturn(5);

    (new GenerateWeeklySlots())->handle($mockGenerator);

    Carbon::setTestNow();
});

it('GenerateWeeklySlots passes slot_duration_minutes from staff preferences', function () {
    $staff = User::factory()->create();
    AvailabilityRule::factory()->create(['user_id' => $staff->id, 'is_available' => true]);
    UserPreference::factory()->create(['user_id' => $staff->id, 'slot_duration_minutes' => 15]);

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->once()
        ->with($staff->id, Mockery::type(Carbon::class), 15)
        ->andReturn(4);

    (new GenerateWeeklySlots())->handle($mockGenerator);
});

it('GenerateWeeklySlots defaults to 60 minutes when no preferences exist', function () {
    $staff = User::factory()->create();
    AvailabilityRule::factory()->create(['user_id' => $staff->id, 'is_available' => true]);

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->once()
        ->with($staff->id, Mockery::type(Carbon::class), 60)
        ->andReturn(8);

    (new GenerateWeeklySlots())->handle($mockGenerator);
});

it('GenerateWeeklySlots generates slots for each week up to the configured horizon', function () {
    SystemSetting::current()->update(['slot_generation_weeks' => 3]);

    $staff = User::factory()->create();
    AvailabilityRule::factory()->create(['user_id' => $staff->id, 'is_available' => true]);

    Carbon::setTestNow('2026-05-10 00:00:00'); // Sunday

    $capturedWeeks = [];
    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->times(3)
        ->with($staff->id, Mockery::on(function (Carbon $d) use (&$capturedWeeks) {
            $capturedWeeks[] = $d->format('Y-m-d');
            return true;
        }), Mockery::type('int'))
        ->andReturn(5);

    (new GenerateWeeklySlots())->handle($mockGenerator);

    expect($capturedWeeks)->toBe(['2026-05-11', '2026-05-18', '2026-05-25']);

    Carbon::setTestNow();
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
