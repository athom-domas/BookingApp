<?php

use App\Services\WhatsAppConversationState;

it('returns a fresh state with ULID conversation_id when no state exists', function () {
    $state = app(WhatsAppConversationState::class)->get(1, '+393401234567');

    expect($state['step'])->toBe('new');
    expect($state['conversation_id'])->toBeString()->toHaveLength(26);
    expect($state['intent'])->toBe('unknown');
});

it('persists and retrieves state', function () {
    $svc = app(WhatsAppConversationState::class);
    $state = $svc->get(1, '+393401234567');
    $state['step'] = 'collecting';
    $svc->set(1, '+393401234567', $state);

    $loaded = $svc->get(1, '+393401234567');
    expect($loaded['step'])->toBe('collecting');
    expect($loaded['conversation_id'])->toBe($state['conversation_id']);
});

it('caps messages array at 15 entries', function () {
    $svc = app(WhatsAppConversationState::class);
    $state = $svc->get(1, '+393401234567');
    $state['messages'] = array_fill(0, 20, ['role' => 'user', 'content' => 'x']);
    $svc->set(1, '+393401234567', $state);

    $loaded = $svc->get(1, '+393401234567');
    expect(count($loaded['messages']))->toBe(15);
});

it('executes callback inside lock', function () {
    $svc = app(WhatsAppConversationState::class);
    $result = $svc->withLock(1, '+393401234567', fn () => 'locked-result');

    expect($result)->toBe('locked-result');
});

it('normalizes legacy state shape on read', function () {
    $svc = app(WhatsAppConversationState::class);
    $state = $svc->get(1, '+393401234567');
    $state['step'] = 'collecting_service';
    $state['draft']['service_ids'] = [];
    $state['draft']['service_id'] = 42;
    $state['selected_slot'] = [
        'service_id' => 42,
        'staff_id' => 7,
        'starts_at' => now()->addDay()->toIso8601String(),
    ];
    $svc->set(1, '+393401234567', $state);

    $loaded = $svc->get(1, '+393401234567');

    expect($loaded['step'])->toBe('idle')
        ->and($loaded['draft']['service_ids'])->toBe([42])
        ->and($loaded['selected_slot']['service_ids'])->toBe([42])
        ->and($loaded['last_available_slots_service_ids'])->toBe([42])
        ->and($loaded['last_service_options'])->toBe([]);
});
