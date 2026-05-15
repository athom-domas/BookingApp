<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Queue::fake();
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

function makePortalCustomer(): User
{
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    return $customer;
}

function makePortalBookableSetup(array $serviceAttributes = []): array
{
    $service = Service::factory()->create(array_merge([
        'duration_minutes' => 60,
        'price' => 75.00,
        'active' => true,
    ], $serviceAttributes));

    $staff = User::factory()->create(['name' => 'Staff Portal']);
    $staff->assignRole('staff');
    $service->staff()->attach($staff->id);

    $date = Carbon::parse('next monday')->setTime(10, 0);

    AvailabilityRule::factory()->create([
        'user_id' => $staff->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_available' => true,
    ]);

    return [$service, $staff, $date];
}

it('shows the public booking page with active services', function () {
    Service::factory()->create(['name' => 'Taglio', 'active' => true]);
    Service::factory()->create(['name' => 'Servizio nascosto', 'active' => false]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Prenota il tuo appuntamento')
        ->assertSee('Taglio')
        ->assertDontSee('Servizio nascosto');
});

it('creates a pending booking and payment intent for an authenticated customer', function () {
    $customer = makePortalCustomer();
    [$service, $staff, $date] = makePortalBookableSetup();

    $this->mock(PaymentService::class)
        ->shouldReceive('initiateStripePayment')
        ->once()
        ->with(Mockery::type('int'), 7500)
        ->andReturnUsing(fn (int $appointmentId) => Payment::factory()->create([
            'appointment_id' => $appointmentId,
            'user_id' => $customer->id,
            'amount' => 75.00,
            'status' => 'pending',
            'stripe_transaction_id' => 'pi_portal_123',
            'stripe_response' => ['client_secret' => 'pi_portal_123_secret_test'],
        ]));

    $response = $this->actingAs($customer)->post('/portal/bookings', [
        'service_id' => $service->id,
        'staff_id' => $staff->id,
        'scheduled_date' => $date->toDateTimeString(),
        'notes' => 'Prima visita',
    ]);

    $appointment = Appointment::where('user_id', $customer->id)->first();

    $response->assertRedirect(route('portal.appointments.payment', $appointment));
    expect($appointment)->not->toBeNull();
    expect($appointment->status)->toBe('pending');
    expect($appointment->notes)->toBe('Prima visita');
});

it('rejects inactive services', function () {
    $customer = makePortalCustomer();
    [$service, $staff, $date] = makePortalBookableSetup(['active' => false]);

    $this->mock(PaymentService::class)->shouldNotReceive('initiateStripePayment');

    $this->actingAs($customer)->post('/portal/bookings', [
        'service_id' => $service->id,
        'staff_id' => $staff->id,
        'scheduled_date' => $date->toDateTimeString(),
    ])->assertSessionHasErrors('scheduled_date');

    expect(Appointment::count())->toBe(0);
});

it('rejects staff not assigned to the selected service', function () {
    $customer = makePortalCustomer();
    [$service, $staff, $date] = makePortalBookableSetup();
    $service->staff()->detach($staff->id);

    $this->actingAs($customer)->post('/portal/bookings', [
        'service_id' => $service->id,
        'staff_id' => $staff->id,
        'scheduled_date' => $date->toDateTimeString(),
    ])->assertSessionHasErrors('scheduled_date');
});

it('rejects users without staff role even if attached to the service', function () {
    $customer = makePortalCustomer();
    [$service, $staff, $date] = makePortalBookableSetup();
    $staff->syncRoles([]);

    $this->actingAs($customer)->post('/portal/bookings', [
        'service_id' => $service->id,
        'staff_id' => $staff->id,
        'scheduled_date' => $date->toDateTimeString(),
    ])->assertSessionHasErrors('scheduled_date');
});

it('rejects bookings when the slot is already taken by another appointment', function () {
    $customer = makePortalCustomer();
    [$service, $staff, $date] = makePortalBookableSetup();

    // Occupy the slot with an existing confirmed appointment
    Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_id'     => $service->id,
        'scheduled_date' => $date,
        'status'         => 'confirmed',
    ]);

    $this->actingAs($customer)->post('/portal/bookings', [
        'service_id' => $service->id,
        'staff_id' => $staff->id,
        'scheduled_date' => $date->toDateTimeString(),
    ])->assertSessionHasErrors('scheduled_date');
});

it('rejects past booking dates', function () {
    $customer = makePortalCustomer();
    [$service, $staff] = makePortalBookableSetup();

    $this->actingAs($customer)->post('/portal/bookings', [
        'service_id' => $service->id,
        'staff_id' => $staff->id,
        'scheduled_date' => now()->subDay()->toDateTimeString(),
    ])->assertSessionHasErrors('scheduled_date');
});
