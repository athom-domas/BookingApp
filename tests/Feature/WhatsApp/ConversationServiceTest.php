<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\SalonProfile;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppConversationService;
use App\Services\WhatsAppConversationState;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);

    IntegrationSetting::current()->update([
        'meta_whatsapp_token' => 'fake-token',
        'meta_whatsapp_phone_id' => '1234',
        'whatsapp_ai_enabled' => true,
    ]);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    \App\Models\UserPreference::create([
        'business_id'          => $business->id,
        'user_id'              => $customer->id,
        'phone_number'         => '+393401234567',
        'notification_channel' => 'whatsapp',
    ]);

    config(['services.anthropic.key' => 'fake-key']);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeInboundMessage(int $businessId, string $text = 'Voglio prenotare', string $type = 'text', ?array $payload = null): WhatsAppMessage
{
    $payload ??= ['text' => ['body' => $text], 'timestamp' => (string) now()->timestamp];

    return WhatsAppMessage::create([
        'business_id' => $businessId,
        'wamid' => 'wamid.'.uniqid(),
        'phone' => '+393401234567',
        'phone_normalized' => '+393401234567',
        'wa_id' => '393401234567',
        'direction' => 'inbound',
        'type' => $type,
        'payload' => $payload,
    ]);
}

function makeWaAiStaff(Service $service, string $name, Carbon $date, string $start = '09:00:00', string $end = '12:00:00'): User
{
    $staff = User::factory()->create(['name' => $name]);
    $staff->assignRole('staff');
    $staff->services()->attach($service->id);

    AvailabilityRule::factory()->create([
        'business_id' => app('current_business_id'),
        'user_id' => $staff->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => $start,
        'end_time' => $end,
        'is_available' => true,
    ]);

    return $staff;
}

it('processes a simple text reply from Claude', function () {
    $businessId = app('current_business_id');
    $message = makeInboundMessage($businessId, 'Ciao');

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Ciao! Come posso aiutarti?']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $message->refresh();
    expect($message->processed_at)->not->toBeNull();

    Http::assertSentCount(2); // one to Anthropic, one to Meta
});

it('handles unsupported media messages without calling Claude', function () {
    $businessId = app('current_business_id');
    $message = makeInboundMessage($businessId, '', 'image', [
        'type' => 'image',
        'image' => ['id' => 'media-id'],
        'timestamp' => (string) now()->timestamp,
    ]);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.anthropic.com')) {
            return Http::response([
                'content' => [['type' => 'text', 'text' => 'fallback']],
                'stop_reason' => 'end_turn',
            ], 200);
        }

        return Http::response(['messages' => [['id' => 'wamid.out.'.uniqid()]]], 200);
    });

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $message->refresh();

    expect($message->processed_at)->not->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'posso gestire solo messaggi scritti')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('uses interactive reply text as conversation input', function () {
    $businessId = app('current_business_id');
    Service::factory()->create([
        'name' => 'Taglio',
        'duration_minutes' => 30,
        'price' => 20,
        'active' => true,
    ]);

    $message = makeInboundMessage($businessId, '', 'interactive', [
        'type' => 'interactive',
        'interactive' => [
            'button_reply' => [
                'id' => 'book',
                'title' => 'Vorrei prenotare',
            ],
        ],
        'timestamp' => (string) now()->timestamp,
    ]);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.anthropic.com')) {
            return Http::response([
                'content' => [['type' => 'text', 'text' => 'fallback']],
                'stop_reason' => 'end_turn',
            ], 200);
        }

        return Http::response(['messages' => [['id' => 'wamid.out.'.uniqid()]]], 200);
    });

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $state = app(WhatsAppConversationState::class)->get($businessId, '+393401234567');

    expect($state['step'])->toBe('collecting');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Quale servizio ti interessa?')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('retries Claude on rate limit responses', function () {
    $businessId = app('current_business_id');
    $message = makeInboundMessage($businessId, 'Ciao');
    $anthropicCalls = 0;

    Http::fake(function ($request) use (&$anthropicCalls) {
        if (str_contains($request->url(), 'api.anthropic.com')) {
            $anthropicCalls++;

            return $anthropicCalls === 1
                ? Http::response(['error' => ['message' => 'rate limited']], 429)
                : Http::response([
                    'content' => [['type' => 'text', 'text' => 'Ciao! Come posso aiutarti?']],
                    'stop_reason' => 'end_turn',
                ], 200);
        }

        return Http::response(['messages' => [['id' => 'wamid.out']]], 200);
    });

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    expect($anthropicCalls)->toBe(2);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Ciao! Come posso aiutarti?')
    );
});

it('marks message as failed on Claude API error', function () {
    $businessId = app('current_business_id');
    $message = makeInboundMessage($businessId, 'Ciao');

    Http::fake([
        'https://api.anthropic.com/*' => Http::response(['error' => 'Internal'], 500),
    ]);

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $message->refresh();
    expect($message->failed_at)->not->toBeNull();
    expect($message->error_code)->toBe('CLAUDE_ERROR');
});

it('stops conversation when turn_count exceeds max_turns', function () {
    $businessId = app('current_business_id');

    IntegrationSetting::current()->update(['whatsapp_ai_max_turns' => 3]);

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    $state['turn_count'] = 3; // already at limit; next message pushes it over
    $stateService->set($businessId, '+393401234567', $state);

    $message = makeInboundMessage($businessId, 'Quarto turno');

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
    ]);

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $message->refresh();
    expect($message->processed_at)->not->toBeNull();
    Http::assertSentCount(1); // limit message sent to Meta, no Anthropic call
});

it('sends acknowledgement when escalated', function () {
    $businessId = app('current_business_id');
    $message = makeInboundMessage($businessId, 'ancora non ho capito');

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    $state['escalated'] = true;
    $stateService->set($businessId, '+393401234567', $state);

    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200)]);

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    Http::assertSentCount(1); // one request to Meta for the acknowledgement
});

it('shows a deterministic numbered service menu for booking requests', function () {
    $businessId = app('current_business_id');

    Service::factory()->create([
        'name' => 'Taglio Classico',
        'duration_minutes' => 20,
        'price' => 12,
        'active' => true,
    ]);
    Service::factory()->create([
        'name' => 'Rasatura Barba',
        'duration_minutes' => 20,
        'price' => 10,
        'active' => true,
    ]);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.anthropic.com')) {
            return Http::response([
                'content' => [['type' => 'text', 'text' => 'fallback']],
                'stop_reason' => 'end_turn',
            ], 200);
        }

        return Http::response(['messages' => [['id' => 'wamid.out.'.uniqid()]]], 200);
    });

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'Ciao, vorrei fare una prenotazione')->id,
        $businessId,
    );

    $state = app(WhatsAppConversationState::class)->get($businessId, '+393401234567');

    expect($state['step'])->toBe('collecting')
        ->and($state['last_service_options'])->toHaveCount(2);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), '1. Taglio Classico')
        && str_contains(data_get($request->data(), 'text.body', ''), '2. Rasatura Barba')
        && str_contains(data_get($request->data(), 'text.body', ''), '1 e 2')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('accepts numbered service selections from the last service menu', function () {
    $businessId = app('current_business_id');

    $taglio = Service::factory()->create([
        'name' => 'Taglio Classico',
        'duration_minutes' => 20,
        'price' => 12,
        'active' => true,
    ]);
    $rasatura = Service::factory()->create([
        'name' => 'Rasatura Barba',
        'duration_minutes' => 20,
        'price' => 10,
        'active' => true,
    ]);
    $staff = User::factory()->create(['name' => 'Nicola']);
    $staff->assignRole('staff');
    $staff->services()->attach([$taglio->id, $rasatura->id]);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.anthropic.com')) {
            return Http::response([
                'content' => [['type' => 'text', 'text' => 'fallback']],
                'stop_reason' => 'end_turn',
            ], 200);
        }

        return Http::response(['messages' => [['id' => 'wamid.out.'.uniqid()]]], 200);
    });

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'Vorrei prenotare')->id,
        $businessId,
    );
    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, '1 e 2')->id,
        $businessId,
    );

    $state = app(WhatsAppConversationState::class)->get($businessId, '+393401234567');

    expect($state['step'])->toBe('collecting')
        ->and($state['draft']['service_ids'])->toBe([$taglio->id, $rasatura->id]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Perfetto: Taglio Classico + Rasatura Barba')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Hai preferenze sullo staff?')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('replaces previous services when numbered selections are sent again', function () {
    $businessId = app('current_business_id');

    $taglio = Service::factory()->create(['name' => 'Taglio Classico', 'duration_minutes' => 20, 'price' => 12, 'active' => true]);
    $rasatura = Service::factory()->create(['name' => 'Rasatura Barba', 'duration_minutes' => 20, 'price' => 10, 'active' => true]);
    $modellatura = Service::factory()->create(['name' => 'Modellatura Barba', 'duration_minutes' => 20, 'price' => 5, 'active' => true]);

    Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.out.'.uniqid()]]], 200));

    app(WhatsAppConversationService::class)->handle(makeInboundMessage($businessId, 'Vorrei prenotare')->id, $businessId);
    app(WhatsAppConversationService::class)->handle(makeInboundMessage($businessId, '3')->id, $businessId);
    app(WhatsAppConversationService::class)->handle(makeInboundMessage($businessId, 'anzi facciamo 1 e 2')->id, $businessId);

    $state = app(WhatsAppConversationState::class)->get($businessId, '+393401234567');

    expect($state['draft']['service_ids'])->toBe([$taglio->id, $rasatura->id])
        ->and($state['draft']['service_ids'])->not->toContain($modellatura->id);
});

it('uses explicit calendar date before weekday names when fetching slots', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00', 'Europe/Rome'));

    $businessId = app('current_business_id');
    SystemSetting::current()->update(['slot_granularity_minutes' => 30, 'payment_mode' => 'in_salon']);

    $service = Service::factory()->create([
        'name' => 'Taglio',
        'duration_minutes' => 30,
        'price' => 25,
        'active' => true,
    ]);

    makeWaAiStaff($service, 'Nicola', Carbon::parse('2026-07-11'));

    $message = makeInboundMessage($businessId, 'Vorrei un taglio venerdì 11 luglio');

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.anthropic.com')) {
            return Http::response([
                'content' => [['type' => 'text', 'text' => 'Ho trovato alcuni orari per l\'11 luglio.']],
                'stop_reason' => 'end_turn',
            ], 200);
        }

        return Http::response(['messages' => [['id' => 'wamid.out.'.uniqid()]]], 200);
    });

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $state = app(WhatsAppConversationState::class)->get($businessId, '+393401234567');

    expect($state['draft']['date'])->toBe('2026-07-11')
        ->and($state['step'])->toBe('collecting')
        ->and($state['last_available_slots'])->toBeEmpty();

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'nessuna preferenza')->id,
        $businessId,
    );
    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'mattina')->id,
        $businessId,
    );

    $state = app(WhatsAppConversationState::class)->get($businessId, '+393401234567');

    expect($state['draft']['date'])->toBe('2026-07-11')
        ->and($state['last_available_slots'])->not->toBeEmpty()
        ->and($state['last_available_slots'][0]['starts_at'])->toContain('2026-07-11');
});

it('updates staff preference before parsing a slot pick in slots_shown', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00', 'Europe/Rome'));

    $businessId = app('current_business_id');
    $service = Service::factory()->create(['name' => 'Taglio', 'duration_minutes' => 30, 'active' => true]);
    $giuseppe = makeWaAiStaff($service, 'Giuseppe', Carbon::parse('2026-07-11'));
    $nicola = makeWaAiStaff($service, 'Nicola', Carbon::parse('2026-07-11'));

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    $state['step'] = 'slots_shown';
    $state['draft'] = [
        'service_ids' => [$service->id],
        'service_id' => $service->id,
        'staff_id' => $giuseppe->id,
        'date' => '2026-07-11',
        'time' => null,
        'customer_name' => null,
    ];
    $state['last_available_slots_service_ids'] = [$service->id];
    $state['last_available_slots_generated_at'] = now()->toIso8601String();
    $state['last_available_slots'] = [[
        'start' => '09:00',
        'starts_at' => Carbon::parse('2026-07-11 09:00:00', 'Europe/Rome')->toIso8601String(),
        'availableOperators' => [$giuseppe->id, $nicola->id],
        'availableStaff' => [
            ['id' => $giuseppe->id, 'name' => 'Giuseppe'],
            ['id' => $nicola->id, 'name' => 'Nicola'],
        ],
        'label' => 'disponibile',
    ]];
    $stateService->set($businessId, '+393401234567', $state);

    $message = makeInboundMessage($businessId, 'il primo con Nicola');

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'fallback']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $updated = $stateService->get($businessId, '+393401234567');

    expect($updated['selected_slot']['staff_id'])->toBe($nicola->id)
        ->and($updated['selected_slot']['staff_name'])->toBe('Nicola');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('selects a real available operator for an exact time and excludes busy staff', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00', 'Europe/Rome'));

    $businessId = app('current_business_id');
    SystemSetting::current()->update(['slot_granularity_minutes' => 20, 'payment_mode' => 'in_salon']);

    $taglio = Service::factory()->create(['name' => 'Taglio Classico', 'duration_minutes' => 20, 'active' => true]);
    $rasatura = Service::factory()->create(['name' => 'Rasatura Barba', 'duration_minutes' => 20, 'active' => true]);

    $date = Carbon::parse('2026-07-10');
    $nicola = makeWaAiStaff($taglio, 'Nicola Demo', $date, '09:00:00', '13:00:00');
    $giuseppe = makeWaAiStaff($taglio, 'Giuseppe Demo', $date, '09:00:00', '13:00:00');
    $giorgi = makeWaAiStaff($taglio, 'Giorgi Demo', $date, '09:00:00', '13:00:00');
    foreach ([$nicola, $giuseppe, $giorgi] as $staff) {
        $staff->services()->attach($rasatura->id);
    }

    Appointment::factory()->create([
        'business_id' => $businessId,
        'staff_id' => $nicola->id,
        'service_ids' => [$taglio->id, $rasatura->id],
        'scheduled_date' => Carbon::parse('2026-07-10 11:00:00', 'Europe/Rome'),
        'status' => 'confirmed',
    ]);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.anthropic.com')) {
            return Http::response([
                'content' => [['type' => 'text', 'text' => 'fallback']],
                'stop_reason' => 'end_turn',
            ], 200);
        }

        return Http::response(['messages' => [['id' => 'wamid.out.'.uniqid()]]], 200);
    });

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'Vorrei taglio e rasatura venerdì 10 luglio alle 11')->id,
        $businessId,
    );

    $state = app(WhatsAppConversationState::class)->get($businessId, '+393401234567');

    expect($state['step'])->toBe('collecting')
        ->and($state['selected_slot'])->toBeNull();

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'nessuna preferenza')->id,
        $businessId,
    );

    $state = app(WhatsAppConversationState::class)->get($businessId, '+393401234567');

    expect($state['step'])->toBe('slot_confirmed')
        ->and($state['selected_slot']['staff_id'])->not->toBe($nicola->id)
        ->and($state['selected_slot']['staff_name'])->not->toContain('Nicola')
        ->and($state['selected_slot']['service_ids'])->toBe([$taglio->id, $rasatura->id]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Riepilogo prenotazione')
        && ! str_contains(data_get($request->data(), 'text.body', ''), 'Nicola Demo')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('answers staff questions from the selected slot during confirmation', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00', 'Europe/Rome'));

    $businessId = app('current_business_id');
    $service = Service::factory()->create(['name' => 'Taglio', 'duration_minutes' => 30, 'active' => true]);
    $staff = makeWaAiStaff($service, 'Giuseppe Demo', Carbon::parse('2026-07-11'));

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    $state['step'] = 'slot_confirmed';
    $state['awaiting_confirmation'] = true;
    $state['selected_slot'] = [
        'starts_at' => Carbon::parse('2026-07-11 09:00:00', 'Europe/Rome')->toIso8601String(),
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'service_name' => 'Taglio',
        'staff_name' => 'Giuseppe Demo',
    ];
    $stateService->set($businessId, '+393401234567', $state);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Nicola Demo']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'Chi mi taglierà i capelli?')->id,
        $businessId,
    );

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Giuseppe Demo')
        && ! str_contains(data_get($request->data(), 'text.body', ''), 'Nicola Demo')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('changes staff deterministically during confirmation when available', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00', 'Europe/Rome'));

    $businessId = app('current_business_id');
    $service = Service::factory()->create(['name' => 'Taglio', 'duration_minutes' => 30, 'active' => true]);
    $giuseppe = makeWaAiStaff($service, 'Giuseppe Demo', Carbon::parse('2026-07-11'));
    $giorgi = makeWaAiStaff($service, 'Giorgi Demo', Carbon::parse('2026-07-11'));

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    $state['step'] = 'slot_confirmed';
    $state['awaiting_confirmation'] = true;
    $state['draft']['service_ids'] = [$service->id];
    $state['draft']['date'] = '2026-07-11';
    $state['last_available_slots_service_ids'] = [$service->id];
    $state['last_available_slots_generated_at'] = now()->toIso8601String();
    $state['last_available_slots'] = [[
        'start' => '09:00',
        'starts_at' => Carbon::parse('2026-07-11 09:00:00', 'Europe/Rome')->toIso8601String(),
        'availableOperators' => [$giuseppe->id, $giorgi->id],
        'availableStaff' => [
            ['id' => $giuseppe->id, 'name' => 'Giuseppe Demo'],
            ['id' => $giorgi->id, 'name' => 'Giorgi Demo'],
        ],
    ]];
    $state['selected_slot'] = [
        'starts_at' => Carbon::parse('2026-07-11 09:00:00', 'Europe/Rome')->toIso8601String(),
        'service_ids' => [$service->id],
        'staff_id' => $giuseppe->id,
        'service_name' => 'Taglio',
        'staff_name' => 'Giuseppe Demo',
    ];
    $stateService->set($businessId, '+393401234567', $state);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'fallback']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'preferisco Giorgi')->id,
        $businessId,
    );

    $updated = $stateService->get($businessId, '+393401234567');

    expect($updated['selected_slot']['staff_id'])->toBe($giorgi->id)
        ->and($updated['selected_slot']['staff_name'])->toBe('Giorgi Demo');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Giorgi Demo')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('answers exact-time staff availability questions without changing selected slot', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00', 'Europe/Rome'));

    $businessId = app('current_business_id');
    SystemSetting::current()->update(['slot_granularity_minutes' => 15, 'payment_mode' => 'in_salon']);

    $service = Service::factory()->create(['name' => 'Taglio', 'duration_minutes' => 30, 'active' => true]);
    $giuseppe = makeWaAiStaff($service, 'Giuseppe Demo', Carbon::parse('2026-07-11'));
    $giorgi = makeWaAiStaff($service, 'Giorgi Demo', Carbon::parse('2026-07-11'));

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    $state['step'] = 'slot_confirmed';
    $state['awaiting_confirmation'] = true;
    $state['draft']['service_ids'] = [$service->id];
    $state['draft']['date'] = '2026-07-11';
    $state['selected_slot'] = [
        'starts_at' => Carbon::parse('2026-07-11 11:15:00', 'Europe/Rome')->toIso8601String(),
        'service_ids' => [$service->id],
        'staff_id' => $giuseppe->id,
        'service_name' => 'Taglio',
        'staff_name' => 'Giuseppe Demo',
    ];
    $stateService->set($businessId, '+393401234567', $state);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'fallback']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'chi è disponibile alle 11?')->id,
        $businessId,
    );

    $updated = $stateService->get($businessId, '+393401234567');

    expect($updated['selected_slot']['starts_at'])->toContain('2026-07-11T11:15:00');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Giuseppe Demo')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Giorgi Demo')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('rechecks exact time changes during confirmation without Claude', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00', 'Europe/Rome'));

    $businessId = app('current_business_id');
    SystemSetting::current()->update(['slot_granularity_minutes' => 15, 'payment_mode' => 'in_salon']);

    $taglio = Service::factory()->create(['name' => 'Taglio Classico', 'duration_minutes' => 20, 'active' => true]);
    $rasatura = Service::factory()->create(['name' => 'Rasatura Barba', 'duration_minutes' => 20, 'active' => true]);

    $date = Carbon::parse('2026-07-10');
    $nicola = makeWaAiStaff($taglio, 'Nicola Demo', $date, '09:00:00', '13:00:00');
    $giuseppe = makeWaAiStaff($taglio, 'Giuseppe Demo', $date, '09:00:00', '13:00:00');
    $giorgi = makeWaAiStaff($taglio, 'Giorgi Demo', $date, '09:00:00', '13:00:00');
    foreach ([$nicola, $giuseppe, $giorgi] as $staff) {
        $staff->services()->attach($rasatura->id);
    }

    Appointment::factory()->create([
        'business_id' => $businessId,
        'staff_id' => $nicola->id,
        'service_ids' => [$taglio->id, $rasatura->id],
        'scheduled_date' => Carbon::parse('2026-07-10 11:00:00', 'Europe/Rome'),
        'status' => 'confirmed',
    ]);

    Appointment::factory()->create([
        'business_id' => $businessId,
        'staff_id' => $giuseppe->id,
        'service_ids' => [$taglio->id, $rasatura->id],
        'scheduled_date' => Carbon::parse('2026-07-10 10:00:00', 'Europe/Rome'),
        'status' => 'pending',
    ]);

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    $state['step'] = 'slot_confirmed';
    $state['awaiting_confirmation'] = true;
    $state['draft']['service_ids'] = [$taglio->id, $rasatura->id];
    $state['draft']['date'] = '2026-07-10';
    $state['last_available_slots_service_ids'] = [$taglio->id, $rasatura->id];
    $state['last_available_slots_generated_at'] = now()->toIso8601String();
    $state['selected_slot'] = [
        'starts_at' => Carbon::parse('2026-07-10 11:40:00', 'Europe/Rome')->toIso8601String(),
        'service_ids' => [$taglio->id, $rasatura->id],
        'staff_id' => $nicola->id,
        'service_name' => 'Taglio Classico, Rasatura Barba',
        'staff_name' => 'Nicola Demo',
    ];
    $stateService->set($businessId, '+393401234567', $state);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'fallback']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'Alle 11')->id,
        $businessId,
    );

    $updated = $stateService->get($businessId, '+393401234567');

    expect($updated['step'])->toBe('slot_confirmed')
        ->and($updated['selected_slot']['starts_at'])->toContain('2026-07-10T11:00:00')
        ->and($updated['selected_slot']['staff_id'])->toBe($giuseppe->id)
        ->and($updated['selected_slot']['staff_name'])->toBe('Giuseppe Demo');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), '10/07/2026 alle 11:00')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Giuseppe Demo')
        && ! str_contains(data_get($request->data(), 'text.body', ''), 'Nicola Demo')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('refreshes expired slots immediately when the draft is complete', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00', 'Europe/Rome'));

    $businessId = app('current_business_id');
    SystemSetting::current()->update(['slot_granularity_minutes' => 30, 'payment_mode' => 'in_salon']);

    $service = Service::factory()->create(['name' => 'Taglio', 'duration_minutes' => 30, 'active' => true]);
    $staff = makeWaAiStaff($service, 'Nicola', Carbon::parse('2026-07-11'));

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    $state['step'] = 'slot_confirmed';
    $state['awaiting_confirmation'] = true;
    $state['draft']['service_ids'] = [$service->id];
    $state['draft']['service_id'] = $service->id;
    $state['draft']['staff_id'] = $staff->id;
    $state['draft']['date'] = '2026-07-11';
    $state['last_available_slots_service_ids'] = [$service->id];
    $state['last_available_slots_generated_at'] = now()->subMinutes(31)->toIso8601String();
    $state['last_available_slots'] = [[
        'start' => '09:00',
        'starts_at' => Carbon::parse('2026-07-11 09:00:00', 'Europe/Rome')->toIso8601String(),
        'availableOperators' => [$staff->id],
        'availableStaff' => [['id' => $staff->id, 'name' => 'Nicola']],
    ]];
    $state['selected_slot'] = [
        'starts_at' => Carbon::parse('2026-07-11 09:00:00', 'Europe/Rome')->toIso8601String(),
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'service_name' => 'Taglio',
        'staff_name' => 'Nicola',
    ];
    $stateService->set($businessId, '+393401234567', $state);

    $message = makeInboundMessage($businessId, 'sì');

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
    ]);

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $updated = $stateService->get($businessId, '+393401234567');

    expect($updated['step'])->toBe('slots_shown')
        ->and($updated['last_available_slots'])->not->toBeEmpty()
        ->and(Appointment::where('business_id', $businessId)->count())->toBe(0);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'ho ricontrollato le disponibilità')
    );
});

it('does not start booking when whatsapp booking is disabled', function () {
    $businessId = app('current_business_id');
    IntegrationSetting::current()->update(['whatsapp_ai_booking_enabled' => false]);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'fallback']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'Vorrei prenotare')->id,
        $businessId,
    );

    $state = app(WhatsAppConversationState::class)->get($businessId, '+393401234567');

    expect($state['step'])->toBe('idle')
        ->and($state['last_available_slots'])->toBeEmpty();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'prenotazione via WhatsApp non è disponibile')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('cancels an appointment only after explicit php confirmation', function () {
    $businessId = app('current_business_id');
    IntegrationSetting::current()->update(['whatsapp_ai_cancellation_enabled' => true]);

    $customerId = \App\Models\UserPreference::where('business_id', $businessId)
        ->where('phone_number', '+393401234567')
        ->value('user_id');

    $service = Service::factory()->create(['name' => 'Taglio', 'duration_minutes' => 30, 'active' => true]);
    $staff = User::factory()->create(['business_id' => $businessId, 'name' => 'Nicola']);
    $staff->assignRole('staff');
    $staff->services()->attach($service->id);

    $appointment = Appointment::factory()->create([
        'business_id' => $businessId,
        'user_id' => $customerId,
        'staff_id' => $staff->id,
        'service_ids' => [$service->id],
        'scheduled_date' => now()->addDays(3),
        'status' => 'confirmed',
    ]);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'fallback']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'Vorrei cancellare il mio appuntamento')->id,
        $businessId,
    );

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');

    expect($state['step'])->toBe('awaiting_cancellation_confirmation')
        ->and($state['pending_cancellation_appointment_id'])->toBe($appointment->id)
        ->and($appointment->fresh()->status)->toBe('confirmed');

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'sì confermo')->id,
        $businessId,
    );

    $state = $stateService->get($businessId, '+393401234567');

    expect($appointment->fresh()->status)->toBe('cancelled')
        ->and($state['step'])->toBe('idle')
        ->and($state['pending_cancellation_appointment_id'])->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
        && str_contains(data_get($request->data(), 'text.body', ''), 'Confermi la cancellazione')
    );
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

it('runs the PHP-driven booking flow from request to confirmed appointment', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00', 'Europe/Rome'));

    $businessId = app('current_business_id');
    SystemSetting::current()->update(['slot_granularity_minutes' => 30, 'payment_mode' => 'in_salon']);

    SalonProfile::factory()->create([
        'business_id' => $businessId,
        'name' => 'Atelier Fable',
        'address' => 'Via Roma 1',
        'opening_hours' => ['sat' => ['type' => 'continuous', 'open_time' => '09:00', 'close_time' => '12:00']],
        'meta_description' => 'Salone specializzato in taglio e colore.',
    ]);

    $service = Service::factory()->create([
        'name' => 'Taglio',
        'description' => 'Taglio capelli donna e uomo.',
        'duration_minutes' => 30,
        'price' => 25,
        'active' => true,
    ]);
    makeWaAiStaff($service, 'Nicola', Carbon::parse('2026-07-11'));

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.anthropic.com')) {
            return Http::response([
                'content' => [['type' => 'text', 'text' => 'Ho trovato disponibilità per sabato 11 luglio.']],
                'stop_reason' => 'end_turn',
            ], 200);
        }

        return Http::response(['messages' => [['id' => 'wamid.out.'.uniqid()]]], 200);
    });

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'Vorrei prenotare un taglio l\'11 luglio')->id,
        $businessId,
    );

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    expect($state['step'])->toBe('collecting')
        ->and($state['last_available_slots'])->toBeEmpty();

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'nessuna preferenza')->id,
        $businessId,
    );

    $state = $stateService->get($businessId, '+393401234567');
    expect($state['step'])->toBe('collecting')
        ->and($state['last_available_slots'])->toBeEmpty();

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'mattina')->id,
        $businessId,
    );

    $state = $stateService->get($businessId, '+393401234567');
    expect($state['step'])->toBe('slots_shown')
        ->and($state['last_available_slots'])->not->toBeEmpty();

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'il primo')->id,
        $businessId,
    );

    $state = $stateService->get($businessId, '+393401234567');
    expect($state['step'])->toBe('slot_confirmed')
        ->and($state['selected_slot']['service_ids'])->toBe([$service->id]);

    app(WhatsAppConversationService::class)->handle(
        makeInboundMessage($businessId, 'sì confermo')->id,
        $businessId,
    );

    $appointment = Appointment::where('business_id', $businessId)->first();

    expect($appointment)->not->toBeNull()
        ->and($appointment->service_ids)->toBe([$service->id])
        ->and($appointment->status)->toBe('confirmed')
        ->and($appointment->scheduled_date->format('Y-m-d'))->toBe('2026-07-11');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});
