<?php

use App\Services\WhatsAppConversationState;
use Illuminate\Support\Facades\Cache;

it('returns a fresh state with ULID conversation_id when no state exists', function () {
    $state = app(WhatsAppConversationState::class)->get(1, '+393401234567');

    expect($state['step'])->toBe('new');
    expect($state['conversation_id'])->toBeString()->toHaveLength(26);
    expect($state['intent'])->toBe('unknown');
});

it('persists and retrieves state', function () {
    $svc   = app(WhatsAppConversationState::class);
    $state = $svc->get(1, '+393401234567');
    $state['step'] = 'collecting_service';
    $svc->set(1, '+393401234567', $state);

    $loaded = $svc->get(1, '+393401234567');
    expect($loaded['step'])->toBe('collecting_service');
    expect($loaded['conversation_id'])->toBe($state['conversation_id']);
});

it('caps messages array at 15 entries', function () {
    $svc   = app(WhatsAppConversationState::class);
    $state = $svc->get(1, '+393401234567');
    $state['messages'] = array_fill(0, 20, ['role' => 'user', 'content' => 'x']);
    $svc->set(1, '+393401234567', $state);

    $loaded = $svc->get(1, '+393401234567');
    expect(count($loaded['messages']))->toBe(15);
});

it('executes callback inside lock', function () {
    $svc    = app(WhatsAppConversationState::class);
    $result = $svc->withLock(1, '+393401234567', fn () => 'locked-result');

    expect($result)->toBe('locked-result');
});
