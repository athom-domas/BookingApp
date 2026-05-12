# Background Jobs & Integrations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add five background jobs, three Mailables, and two external-service wrappers (Twilio for SMS, Google Calendar for events) plus a scheduler that dispatches due reminders and regenerates weekly slots.

**Architecture:** Each job is a standalone `ShouldQueue` class that resolves its dependencies through the service container. External integrations (Twilio, Google Calendar) live in dedicated service classes bound in `AppServiceProvider`. The scheduler lives in `routes/console.php` (Laravel 13 has no `app/Console/Kernel.php`). All tests mock external SDKs; `Mail::fake()` covers email assertions.

**Tech Stack:** Laravel 13, Redis queues, Twilio PHP SDK (already installed), Google API Client (already installed), Laravel Mail with Blade templates

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `app/Services/NotificationService.php` | Create | Twilio SMS/WhatsApp wrapper |
| `app/Services/GoogleCalendarService.php` | Create | Google Calendar CRUD wrapper |
| `app/Mail/AppointmentReminderMail.php` | Create | Mailable for 24h reminder |
| `app/Mail/AppointmentConfirmationMail.php` | Create | Mailable for booking confirmation |
| `app/Mail/AppointmentCancellationMail.php` | Create | Mailable for cancellation (needed by SendCancellationNotification) |
| `resources/views/emails/appointment-reminder.blade.php` | Create | Reminder email template |
| `resources/views/emails/appointment-confirmation.blade.php` | Create | Confirmation email template |
| `resources/views/emails/appointment-cancellation.blade.php` | Create | Cancellation email template |
| `app/Jobs/SendAppointmentReminder.php` | Create | Dispatched when reminder is due; sends email + optional SMS |
| `app/Jobs/SendAppointmentConfirmation.php` | Create | Dispatched on booking; sends confirmation email |
| `app/Jobs/SendCancellationNotification.php` | Create | Dispatched on cancel; emails customer + staff |
| `app/Jobs/SyncGoogleCalendar.php` | Create | Creates or deletes Google Calendar events |
| `app/Jobs/GenerateWeeklySlots.php` | Create | Generates slots for all staff for the next week |
| `app/Providers/AppServiceProvider.php` | Modify | Bind NotificationService, GoogleCalendarService |
| `config/services.php` | Modify | Add `twilio` and `google` config keys |
| `routes/console.php` | Modify | Schedule GenerateWeeklySlots + reminder dispatcher |

---

## Domain Context

**Existing models:**
- `AppointmentReminder` — fields: `id`, `appointment_id`, `type` (email/sms), `scheduled_for`, `sent_at`, `status` (pending/sent/failed), `error_message`; scope `pending()`
- `Appointment` — relations: `user()`, `staff()`, `service()`, `reminders()`; field `google_event_id` (nullable)
- `UserPreference` — fields: `user_id`, `receive_email_reminders` (bool), `receive_sms_reminders` (bool), `phone_number` (nullable)
- `User` — has `preferences()` hasOne relation

**Existing services:**
- `SlotGeneratorService::generateWeeklySlots(int $staffId, Carbon $weekStart, int $slotMinutes = 30): int`

**Test conventions:**
- `RefreshDatabase` global for all Feature tests
- Roles must exist in DB before `assignRole()` — use `Role::firstOrCreate(...)` in `beforeEach`
- Docker commands: `docker-compose run --rm app ./vendor/bin/pest`
- Mockery available (`use Mockery;`)

---

## Task 1: NotificationService (Twilio) + config

**Files:**
- Create: `app/Services/NotificationService.php`
- Modify: `config/services.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Services/NotificationServiceTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Services/NotificationServiceTest.php`:

```php
<?php

use App\Services\NotificationService;
use Mockery;
use Twilio\Rest\Client as TwilioClient;

it('sendSms sends a message via Twilio', function () {
    $mockMessages = Mockery::mock();
    $mockMessages->shouldReceive('create')
        ->once()
        ->with('+39123456789', Mockery::on(fn ($opts) =>
            $opts['from'] === config('services.twilio.from') &&
            str_contains($opts['body'], 'test message')
        ));

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->shouldReceive('getService')->with('messages')->andReturn($mockMessages);

    $service = new NotificationService($mockTwilio);
    $service->sendSms('+39123456789', 'test message');
});

it('sendWhatsApp sends a whatsapp message via Twilio', function () {
    $mockMessages = Mockery::mock();
    $mockMessages->shouldReceive('create')
        ->once()
        ->with('whatsapp:+39123456789', Mockery::on(fn ($opts) =>
            str_starts_with($opts['from'], 'whatsapp:') &&
            str_contains($opts['body'], 'test whatsapp')
        ));

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->shouldReceive('getService')->with('messages')->andReturn($mockMessages);

    $service = new NotificationService($mockTwilio);
    $service->sendWhatsApp('+39123456789', 'test whatsapp');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/NotificationServiceTest.php
```

Expected: FAIL — class does not exist.

- [ ] **Step 3: Add twilio + google keys to config/services.php**

Read `config/services.php`. Add before the closing `];`:

```php
    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM', '+1234567890'),
    ],

    'google' => [
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS', '/app/config/google-credentials.json'),
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
    ],
```

- [ ] **Step 4: Create NotificationService**

Create `app/Services/NotificationService.php`:

```php
<?php

namespace App\Services;

use Twilio\Rest\Client as TwilioClient;

class NotificationService
{
    public function __construct(private readonly TwilioClient $twilio) {}

    public function sendSms(string $to, string $message): void
    {
        $this->twilio->messages->create($to, [
            'from' => config('services.twilio.from'),
            'body' => $message,
        ]);
    }

    public function sendWhatsApp(string $to, string $message): void
    {
        $this->twilio->messages->create('whatsapp:' . $to, [
            'from' => 'whatsapp:' . config('services.twilio.from'),
            'body' => $message,
        ]);
    }
}
```

- [ ] **Step 5: Bind NotificationService in AppServiceProvider**

Read `app/Providers/AppServiceProvider.php` and add to `register()`:

```php
$this->app->singleton(\App\Services\NotificationService::class, function () {
    return new \App\Services\NotificationService(
        new \Twilio\Rest\Client(
            config('services.twilio.sid'),
            config('services.twilio.token'),
        )
    );
});
```

Also add `use App\Services\NotificationService;` to imports.

- [ ] **Step 6: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/NotificationServiceTest.php
```

Expected: 2/2 pass.

- [ ] **Step 7: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all 91 + 2 = 93 pass.

- [ ] **Step 8: Commit**

```bash
git add app/Services/NotificationService.php config/services.php app/Providers/AppServiceProvider.php tests/Feature/Services/NotificationServiceTest.php
git commit -m "feat: add NotificationService with Twilio SMS/WhatsApp support"
```

---

## Task 2: Email Mailables + Blade Templates

**Files:**
- Create: `app/Mail/AppointmentReminderMail.php`
- Create: `app/Mail/AppointmentConfirmationMail.php`
- Create: `app/Mail/AppointmentCancellationMail.php`
- Create: `resources/views/emails/appointment-reminder.blade.php`
- Create: `resources/views/emails/appointment-confirmation.blade.php`
- Create: `resources/views/emails/appointment-cancellation.blade.php`
- Test: `tests/Feature/Mail/MailableTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Mail/MailableTest.php`:

```php
<?php

use App\Mail\AppointmentCancellationMail;
use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('AppointmentReminderMail renders correctly', function () {
    $appointment = Appointment::factory()->create();
    $appointment->load('user', 'service', 'staff');

    $mailable = new AppointmentReminderMail($appointment);

    $mailable->assertTo($appointment->user->email);
    expect($mailable->render())->toContain($appointment->service->name);
});

it('AppointmentConfirmationMail renders correctly', function () {
    $appointment = Appointment::factory()->create();
    $appointment->load('user', 'service', 'staff');

    $mailable = new AppointmentConfirmationMail($appointment);

    $mailable->assertTo($appointment->user->email);
    expect($mailable->render())->toContain($appointment->service->name);
});

it('AppointmentCancellationMail renders correctly for customer', function () {
    $appointment = Appointment::factory()->create();
    $appointment->load('user', 'service', 'staff');
    $recipient = $appointment->user;

    $mailable = new AppointmentCancellationMail($appointment, $recipient);

    $mailable->assertTo($recipient->email);
    expect($mailable->render())->toContain($appointment->service->name);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Mail/MailableTest.php
```

Expected: FAIL — classes do not exist.

- [ ] **Step 3: Create Blade templates**

Create `resources/views/emails/appointment-reminder.blade.php`:

```html
<!DOCTYPE html>
<html>
<body>
<h2>Reminder: {{ $appointment->service->name }}</h2>
<p>Hi {{ $appointment->user->name }},</p>
<p>This is a reminder that your appointment is tomorrow:</p>
<ul>
  <li><strong>Service:</strong> {{ $appointment->service->name }}</li>
  <li><strong>Staff:</strong> {{ $appointment->staff->name }}</li>
  <li><strong>Date:</strong> {{ $appointment->scheduled_date->format('d/m/Y H:i') }}</li>
</ul>
</body>
</html>
```

Create `resources/views/emails/appointment-confirmation.blade.php`:

```html
<!DOCTYPE html>
<html>
<body>
<h2>Appointment Confirmed</h2>
<p>Hi {{ $appointment->user->name }},</p>
<p>Your appointment has been confirmed:</p>
<ul>
  <li><strong>Service:</strong> {{ $appointment->service->name }}</li>
  <li><strong>Staff:</strong> {{ $appointment->staff->name }}</li>
  <li><strong>Date:</strong> {{ $appointment->scheduled_date->format('d/m/Y H:i') }}</li>
  <li><strong>Price:</strong> €{{ number_format($appointment->final_price, 2) }}</li>
</ul>
</body>
</html>
```

Create `resources/views/emails/appointment-cancellation.blade.php`:

```html
<!DOCTYPE html>
<html>
<body>
<h2>Appointment Cancelled</h2>
<p>Hi {{ $recipient->name }},</p>
<p>The following appointment has been cancelled:</p>
<ul>
  <li><strong>Service:</strong> {{ $appointment->service->name }}</li>
  <li><strong>Staff:</strong> {{ $appointment->staff->name }}</li>
  <li><strong>Date:</strong> {{ $appointment->scheduled_date->format('d/m/Y H:i') }}</li>
</ul>
</body>
</html>
```

- [ ] **Step 4: Create AppointmentReminderMail**

Create `app/Mail/AppointmentReminderMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->appointment->user->email,
            subject: 'Reminder: ' . $this->appointment->service->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-reminder',
        );
    }
}
```

- [ ] **Step 5: Create AppointmentConfirmationMail**

Create `app/Mail/AppointmentConfirmationMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->appointment->user->email,
            subject: 'Appointment Confirmed: ' . $this->appointment->service->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-confirmation',
        );
    }
}
```

- [ ] **Step 6: Create AppointmentCancellationMail**

Create `app/Mail/AppointmentCancellationMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->recipient->email,
            subject: 'Appointment Cancelled: ' . $this->appointment->service->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-cancellation',
        );
    }
}
```

- [ ] **Step 7: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Mail/MailableTest.php
```

Expected: 3/3 pass.

- [ ] **Step 8: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all previous + 3 = 96 pass.

- [ ] **Step 9: Commit**

```bash
git add app/Mail/ resources/views/emails/ tests/Feature/Mail/
git commit -m "feat: add AppointmentReminderMail, AppointmentConfirmationMail, AppointmentCancellationMail with Blade templates"
```

---

## Task 3: Notification Jobs

**Files:**
- Create: `app/Jobs/SendAppointmentReminder.php`
- Create: `app/Jobs/SendAppointmentConfirmation.php`
- Create: `app/Jobs/SendCancellationNotification.php`
- Test: `tests/Feature/Jobs/NotificationJobsTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Jobs/NotificationJobsTest.php`:

```php
<?php

use App\Jobs\SendAppointmentConfirmation;
use App\Jobs\SendAppointmentReminder;
use App\Jobs\SendCancellationNotification;
use App\Mail\AppointmentCancellationMail;
use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Mail::fake();
});

// --- SendAppointmentReminder ---

it('SendAppointmentReminder sends email to customer', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'type'           => 'email',
        'status'         => 'pending',
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldNotReceive('sendSms');

    (new SendAppointmentReminder($reminder))->handle($mockNotification);

    Mail::assertSent(AppointmentReminderMail::class, fn ($mail) =>
        $mail->appointment->id === $appointment->id
    );
    expect($reminder->fresh()->status)->toBe('sent');
    expect($reminder->fresh()->sent_at)->not->toBeNull();
});

it('SendAppointmentReminder sends SMS when user has sms preference enabled', function () {
    $user = User::factory()->create();
    UserPreference::factory()->create([
        'user_id'               => $user->id,
        'receive_sms_reminders' => true,
        'phone_number'          => '+39123456789',
    ]);
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'status'         => 'pending',
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldReceive('sendSms')
        ->once()
        ->with('+39123456789', Mockery::type('string'));

    (new SendAppointmentReminder($reminder))->handle($mockNotification);
});

it('SendAppointmentReminder sends SMS exception propagates', function () {
    $user = User::factory()->create();
    UserPreference::factory()->create([
        'user_id'               => $user->id,
        'receive_sms_reminders' => true,
        'phone_number'          => '+39123456789',
    ]);
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'status'         => 'pending',
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldReceive('sendSms')
        ->andThrow(new \Exception('Twilio error'));

    expect(fn () => (new SendAppointmentReminder($reminder))->handle($mockNotification))
        ->toThrow(\Exception::class);
});

// --- SendAppointmentConfirmation ---

it('SendAppointmentConfirmation sends confirmation email', function () {
    $appointment = Appointment::factory()->create();

    (new SendAppointmentConfirmation($appointment))->handle();

    Mail::assertSent(AppointmentConfirmationMail::class, fn ($mail) =>
        $mail->appointment->id === $appointment->id
    );
});

// --- SendCancellationNotification ---

it('SendCancellationNotification emails both customer and staff', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $appointment = Appointment::factory()->create([
        'user_id'  => $customer->id,
        'staff_id' => $staff->id,
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldNotReceive('sendSms');

    (new SendCancellationNotification($appointment))->handle($mockNotification);

    Mail::assertSent(AppointmentCancellationMail::class, 2);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Jobs/NotificationJobsTest.php
```

Expected: FAIL — job classes do not exist.

- [ ] **Step 3: Create SendAppointmentReminder**

Create `app/Jobs/SendAppointmentReminder.php`:

```php
<?php

namespace App\Jobs;

use App\Mail\AppointmentReminderMail;
use App\Models\AppointmentReminder;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly AppointmentReminder $reminder) {}

    public function handle(NotificationService $notificationService): void
    {
        $reminder     = $this->reminder->load('appointment.user.preferences', 'appointment.service', 'appointment.staff');
        $appointment  = $reminder->appointment;
        $user         = $appointment->user;
        $prefs        = $user->preferences;

        Mail::to($user->email)->send(new AppointmentReminderMail($appointment));

        if ($prefs?->receive_sms_reminders && $prefs->phone_number) {
            $message = "Reminder: {$appointment->service->name} on {$appointment->scheduled_date->format('d/m/Y H:i')}";
            $notificationService->sendSms($prefs->phone_number, $message);
        }

        $reminder->update(['status' => 'sent', 'sent_at' => now()]);
    }
}
```

- [ ] **Step 4: Create SendAppointmentConfirmation**

Create `app/Jobs/SendAppointmentConfirmation.php`:

```php
<?php

namespace App\Jobs;

use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAppointmentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function handle(): void
    {
        $appointment = $this->appointment->load('user', 'service', 'staff');

        Mail::to($appointment->user->email)->send(new AppointmentConfirmationMail($appointment));
    }
}
```

- [ ] **Step 5: Create SendCancellationNotification**

Create `app/Jobs/SendCancellationNotification.php`:

```php
<?php

namespace App\Jobs;

use App\Mail\AppointmentCancellationMail;
use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCancellationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function handle(NotificationService $notificationService): void
    {
        $appointment = $this->appointment->load('user', 'service', 'staff.preferences');

        Mail::to($appointment->user->email)
            ->send(new AppointmentCancellationMail($appointment, $appointment->user));

        Mail::to($appointment->staff->email)
            ->send(new AppointmentCancellationMail($appointment, $appointment->staff));

        $staffPrefs = $appointment->staff->preferences;
        if ($staffPrefs?->receive_sms_reminders && $staffPrefs->phone_number) {
            $message = "Cancelled: {$appointment->service->name} on {$appointment->scheduled_date->format('d/m/Y H:i')}";
            $notificationService->sendSms($staffPrefs->phone_number, $message);
        }
    }
}
```

- [ ] **Step 6: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Jobs/NotificationJobsTest.php
```

Expected: 5/5 pass.

- [ ] **Step 7: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all previous + new tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Jobs/SendAppointmentReminder.php app/Jobs/SendAppointmentConfirmation.php app/Jobs/SendCancellationNotification.php tests/Feature/Jobs/NotificationJobsTest.php
git commit -m "feat: add SendAppointmentReminder, SendAppointmentConfirmation, SendCancellationNotification jobs"
```

---

## Task 4: GoogleCalendarService + SyncGoogleCalendar

**Files:**
- Create: `app/Services/GoogleCalendarService.php`
- Create: `app/Jobs/SyncGoogleCalendar.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Services/GoogleCalendarServiceTest.php`
- Test: `tests/Feature/Jobs/SyncGoogleCalendarTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Services/GoogleCalendarServiceTest.php`:

```php
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
```

Create `tests/Feature/Jobs/SyncGoogleCalendarTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/GoogleCalendarServiceTest.php tests/Feature/Jobs/SyncGoogleCalendarTest.php
```

Expected: FAIL — classes do not exist.

- [ ] **Step 3: Create GoogleCalendarService**

Create `app/Services/GoogleCalendarService.php`:

```php
<?php

namespace App\Services;

use App\Models\Appointment;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

class GoogleCalendarService
{
    public function __construct(private readonly Calendar $calendar) {}

    public function createEvent(Appointment $appointment): string
    {
        $appointment->load('user', 'service', 'staff');

        $start = new EventDateTime();
        $start->setDateTime($appointment->scheduled_date->toRfc3339String());
        $start->setTimeZone('UTC');

        $end = new EventDateTime();
        $end->setDateTime(
            $appointment->scheduled_date->copy()
                ->addMinutes($appointment->service->duration_minutes)
                ->toRfc3339String()
        );
        $end->setTimeZone('UTC');

        $event = new Event([
            'summary'     => $appointment->service->name . ' - ' . $appointment->user->name,
            'description' => $appointment->notes ?? '',
            'start'       => $start,
            'end'         => $end,
        ]);

        $created = $this->calendar->events->insert(
            config('services.google.calendar_id'),
            $event
        );

        return $created->getId();
    }

    public function deleteEvent(string $eventId): void
    {
        $this->calendar->events->delete(
            config('services.google.calendar_id'),
            $eventId
        );
    }
}
```

- [ ] **Step 4: Create SyncGoogleCalendar job**

Create `app/Jobs/SyncGoogleCalendar.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $action,
    ) {}

    public function handle(GoogleCalendarService $calendarService): void
    {
        if ($this->action === 'create') {
            $eventId = $calendarService->createEvent($this->appointment);
            $this->appointment->update(['google_event_id' => $eventId]);
            return;
        }

        if ($this->action === 'delete' && $this->appointment->google_event_id) {
            $calendarService->deleteEvent($this->appointment->google_event_id);
            $this->appointment->update(['google_event_id' => null]);
        }
    }
}
```

- [ ] **Step 5: Bind GoogleCalendarService in AppServiceProvider**

Read `app/Providers/AppServiceProvider.php` and add to `register()`:

```php
$this->app->singleton(\App\Services\GoogleCalendarService::class, function () {
    $client = new \Google\Client();
    $credPath = config('services.google.credentials');
    if (file_exists($credPath)) {
        $client->setAuthConfig($credPath);
    }
    $client->addScope(\Google\Service\Calendar::CALENDAR);
    return new \App\Services\GoogleCalendarService(
        new \Google\Service\Calendar($client)
    );
});
```

- [ ] **Step 6: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/GoogleCalendarServiceTest.php tests/Feature/Jobs/SyncGoogleCalendarTest.php
```

Expected: 5/5 pass.

- [ ] **Step 7: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all previous + 5 pass.

- [ ] **Step 8: Commit**

```bash
git add app/Services/GoogleCalendarService.php app/Jobs/SyncGoogleCalendar.php app/Providers/AppServiceProvider.php tests/Feature/Services/GoogleCalendarServiceTest.php tests/Feature/Jobs/SyncGoogleCalendarTest.php
git commit -m "feat: add GoogleCalendarService and SyncGoogleCalendar job"
```

---

## Task 5: GenerateWeeklySlots job + Scheduler

**Files:**
- Create: `app/Jobs/GenerateWeeklySlots.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Jobs/GenerateWeeklySlotsTest.php`

**Note:** In Laravel 13, there is NO `app/Console/Kernel.php`. The scheduler is defined in `routes/console.php` using `Illuminate\Support\Facades\Schedule`.

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Jobs/GenerateWeeklySlotsTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Jobs/GenerateWeeklySlotsTest.php
```

Expected: FAIL — class does not exist.

- [ ] **Step 3: Create GenerateWeeklySlots job**

Create `app/Jobs/GenerateWeeklySlots.php`:

```php
<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateWeeklySlots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SlotGeneratorService $generator): void
    {
        $nextWeek = Carbon::now()->startOfWeek()->addWeek();

        User::whereHas('availabilityRules', fn ($q) => $q->where('is_available', true))
            ->each(fn (User $staff) => $generator->generateWeeklySlots($staff->id, $nextWeek));
    }
}
```

- [ ] **Step 4: Update routes/console.php with scheduler**

Read `routes/console.php`. Replace the entire file with:

```php
<?php

use App\Jobs\GenerateWeeklySlots;
use App\Jobs\SendAppointmentReminder;
use App\Models\AppointmentReminder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(GenerateWeeklySlots::class)
    ->weekly()
    ->sundays()
    ->at('01:00')
    ->description('Generate time slots for all staff for the next week');

Schedule::call(function () {
    AppointmentReminder::pending()
        ->where('scheduled_for', '<=', now())
        ->each(fn (AppointmentReminder $reminder) => SendAppointmentReminder::dispatch($reminder));
})
    ->everyFiveMinutes()
    ->description('Dispatch due appointment reminders');
```

- [ ] **Step 5: Run job tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Jobs/GenerateWeeklySlotsTest.php
```

Expected: 2/2 pass.

- [ ] **Step 6: Verify schedule is registered**

```bash
docker-compose run --rm app php artisan schedule:list
```

Expected output includes:
```
0 1 * * 0  GenerateWeeklySlots
*/5 * * * * ...
```

- [ ] **Step 7: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Jobs/GenerateWeeklySlots.php routes/console.php tests/Feature/Jobs/GenerateWeeklySlotsTest.php
git commit -m "feat: add GenerateWeeklySlots job and scheduler for weekly slots + reminder dispatch"
```
