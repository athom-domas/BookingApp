<?php

use App\Models\Appointment;
use App\Services\GoogleCalendarService;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Mockery;

it('createEvent creates a Google Calendar event and returns its ID', function () {
    $appointment = Appointment::factory()->create();
    $appointment->load('user', 'service', 'staff');

    $mockEvent = Mockery::mock(Event::class);
    $mockEvent->shouldReceive('getId')->andReturn('google_event_abc');

    $mockEvents = Mockery::mock();
    $mockEvents->shouldReceive('insert')
        ->once()
        ->with(config('services.google.calendar_id'), Mockery::type(Event::class))
        ->andReturn($mockEvent);

    $mockCalendar = Mockery::mock(Calendar::class);
    $mockCalendar->events = $mockEvents;

    $service = new GoogleCalendarService($mockCalendar);
    $result = $service->createEvent($appointment);

    expect($result)->toBe('google_event_abc');
});

it('deleteEvent deletes a Google Calendar event', function () {
    $mockEvents = Mockery::mock();
    $mockEvents->shouldReceive('delete')
        ->once()
        ->with(config('services.google.calendar_id'), 'google_event_abc');

    $mockCalendar = Mockery::mock(Calendar::class);
    $mockCalendar->events = $mockEvents;

    $service = new GoogleCalendarService($mockCalendar);
    $service->deleteEvent('google_event_abc');
});
