<?php

use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\IntegrationSetting;
use App\Services\GoogleCalendarService;

it('SyncGoogleCalendar create action stores google_event_id on appointment', function () {
    IntegrationSetting::current()->update(['google_calendar_id' => 'cal_test_123']);

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

it('SyncGoogleCalendar throws on unknown action', function () {
    $appointment = Appointment::factory()->create(['google_event_id' => null]);

    $mockService = $this->mock(GoogleCalendarService::class);
    $mockService->shouldNotReceive('createEvent');
    $mockService->shouldNotReceive('deleteEvent');

    expect(fn () => (new SyncGoogleCalendar($appointment, 'update'))->handle($mockService))
        ->toThrow(\InvalidArgumentException::class);
});

it('SyncGoogleCalendar failed hook logs the error', function () {
    $appointment = Appointment::factory()->create(['google_event_id' => null]);

    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with('SyncGoogleCalendar failed', \Mockery::on(fn ($ctx) =>
            $ctx['appointment_id'] === $appointment->id &&
            $ctx['action'] === 'create' &&
            isset($ctx['error'])
        ));

    $job = new SyncGoogleCalendar($appointment, 'create');
    $job->failed(new \Exception('API error'));
});
