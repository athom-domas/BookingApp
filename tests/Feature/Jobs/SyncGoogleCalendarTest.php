<?php

use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Services\GoogleCalendarService;

it('SyncGoogleCalendar create action stores google_event_id on appointment', function () {
    $appointment = Appointment::factory()->create(['google_event_id' => null]);

    $mockService = $this->mock(GoogleCalendarService::class);
    $mockService->shouldReceive('createEvent')
        ->with(Mockery::on(fn ($a) => $a->id === $appointment->id))
        ->andReturn('evt_xyz');

    (new SyncGoogleCalendar($appointment, 'create'))->handle($mockService);

    expect($appointment->fresh()->google_event_id)->toBe('evt_xyz');
});

it('SyncGoogleCalendar delete action removes google_event_id from appointment', function () {
    $appointment = Appointment::factory()->create(['google_event_id' => 'evt_to_delete']);

    $mockService = $this->mock(GoogleCalendarService::class);
    $mockService->shouldReceive('deleteEvent')
        ->with('evt_to_delete')
        ->once();

    (new SyncGoogleCalendar($appointment, 'delete'))->handle($mockService);

    expect($appointment->fresh()->google_event_id)->toBeNull();
});

it('SyncGoogleCalendar delete action is a no-op when google_event_id is null', function () {
    $appointment = Appointment::factory()->create(['google_event_id' => null]);

    $mockService = $this->mock(GoogleCalendarService::class);
    $mockService->shouldNotReceive('deleteEvent');

    (new SyncGoogleCalendar($appointment, 'delete'))->handle($mockService);
});
