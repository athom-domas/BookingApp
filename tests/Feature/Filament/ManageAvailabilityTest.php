<?php

use App\Filament\Resources\StaffResource\Pages\ManageAvailability;
use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\SalonProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->business = Business::withoutGlobalScopes()->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);

    // Ensure salon has open hours so ManageAvailability::save() doesn't reject all days
    SalonProfile::current()->update([
        'opening_hours' => collect([0, 1, 2, 3, 4, 5, 6])->mapWithKeys(fn ($d) => [
            ['sun','mon','tue','wed','thu','fri','sat'][$d] => ['type' => 'continuous', 'open_time' => '00:00', 'close_time' => '23:59'],
        ])->all(),
    ]);

    $this->admin = User::factory()->create(['business_id' => $this->business->id])->assignRole('admin');
    $this->admin->businesses()->attach($this->business->id);
    $this->staff = User::factory()->create(['business_id' => $this->business->id])->assignRole('staff');
});

it('renders the manage availability page', function () {
    $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.staff.manage-availability', ['tenant' => $this->business, 'record' => $this->staff]))
        ->assertSuccessful();
});

it('pre-populates form with existing rules', function () {
    AvailabilityRule::factory()->create([
        'user_id'      => $this->staff->id,
        'day_of_week'  => 1,
        'is_available' => true,
        'start_time'   => '09:00:00',
        'end_time'     => '13:00:00',
        'start_time_2' => '14:00:00',
        'end_time_2'   => '18:00:00',
    ]);

    $this->actingAs($this->admin);

    livewire(ManageAvailability::class, ['record' => $this->staff])
        ->assertSet('data.days.1.is_available', true)
        ->assertSet('data.days.1.start_time', '09:00')
        ->assertSet('data.days.1.end_time', '13:00')
        ->assertSet('data.days.1.start_time_2', '14:00')
        ->assertSet('data.days.1.end_time_2', '18:00');
});

it('defaults to unavailable when no rule exists for a day', function () {
    $this->actingAs($this->admin);

    livewire(ManageAvailability::class, ['record' => $this->staff])
        ->assertSet('data.days.1.is_available', false)
        ->assertSet('data.days.1.start_time', null);
});

it('saves all 7 rules on save()', function () {
    $this->actingAs($this->admin);

    $days = [];
    foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
        $available = $day >= 1 && $day <= 5;
        $days[$day] = [
            'is_available' => $available,
            'start_time'   => $available ? '09:00' : null,
            'end_time'     => $available ? '13:00' : null,
            'start_time_2' => $available ? '14:00' : null,
            'end_time_2'   => $available ? '18:00' : null,
        ];
    }

    livewire(ManageAvailability::class, ['record' => $this->staff])
        ->set('data.days', $days)
        ->call('save');

    expect(AvailabilityRule::where('user_id', $this->staff->id)->count())->toBe(7);

    $monday = AvailabilityRule::where('user_id', $this->staff->id)
        ->where('day_of_week', 1)
        ->first();

    expect($monday)
        ->is_available->toBeTrue()
        ->start_time->toBe('09:00:00')
        ->end_time->toBe('13:00:00')
        ->start_time_2->toBe('14:00:00')
        ->end_time_2->toBe('18:00:00');
});

it('sets time fields to null when day is unavailable', function () {
    $this->actingAs($this->admin);

    AvailabilityRule::factory()->create([
        'user_id'      => $this->staff->id,
        'day_of_week'  => 0,
        'is_available' => true,
        'start_time'   => '10:00:00',
        'end_time'     => '14:00:00',
    ]);

    $days = [];
    foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
        $days[$day] = ['is_available' => false, 'start_time' => null, 'end_time' => null, 'start_time_2' => null, 'end_time_2' => null];
    }

    livewire(ManageAvailability::class, ['record' => $this->staff])
        ->set('data.days', $days)
        ->call('save');

    $sunday = AvailabilityRule::where('user_id', $this->staff->id)->where('day_of_week', 0)->first();
    expect($sunday->start_time)->toBeNull();
});
