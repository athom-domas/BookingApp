<?php

use App\Jobs\ProcessWhatsAppMessageJob;
use App\Models\Business;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppConversationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->business = Business::factory()->create();
});

it('dispatches to the whatsapp queue', function () {
    ProcessWhatsAppMessageJob::dispatch(1);

    Queue::assertPushedOn('whatsapp', ProcessWhatsAppMessageJob::class);
});

it('calls conversation service handle with correct args for inbound message', function () {
    $message = WhatsAppMessage::create([
        'business_id'      => $this->business->id,
        'wamid'            => 'wamid.test1',
        'phone'            => '+393401234567',
        'phone_normalized' => '+393401234567',
        'direction'        => 'inbound',
        'type'             => 'text',
        'payload'          => ['text' => ['body' => 'test']],
    ]);

    $mock = \Mockery::mock(WhatsAppConversationService::class);
    $mock->shouldReceive('handle')
        ->once()
        ->with($message->id, $message->business_id);
    app()->instance(WhatsAppConversationService::class, $mock);

    (new ProcessWhatsAppMessageJob($message->id))->handle(
        app(WhatsAppConversationService::class)
    );
});

it('skips already processed messages', function () {
    $message = WhatsAppMessage::create([
        'business_id'      => $this->business->id,
        'wamid'            => 'wamid.test2',
        'phone'            => '+393401234567',
        'phone_normalized' => '+393401234567',
        'direction'        => 'inbound',
        'type'             => 'text',
        'payload'          => ['text' => ['body' => 'test']],
        'processed_at'     => now()->subHour(),
    ]);

    $mock = \Mockery::mock(WhatsAppConversationService::class);
    $mock->shouldNotReceive('handle');
    app()->instance(WhatsAppConversationService::class, $mock);

    (new ProcessWhatsAppMessageJob($message->id))->handle(
        app(WhatsAppConversationService::class)
    );
});

it('skips outbound messages', function () {
    $message = WhatsAppMessage::create([
        'business_id'      => $this->business->id,
        'wamid'            => 'wamid.test3',
        'phone'            => '+393401234567',
        'phone_normalized' => '+393401234567',
        'direction'        => 'outbound',
        'type'             => 'text',
        'payload'          => ['text' => ['body' => 'test']],
    ]);

    $mock = \Mockery::mock(WhatsAppConversationService::class);
    $mock->shouldNotReceive('handle');
    app()->instance(WhatsAppConversationService::class, $mock);

    (new ProcessWhatsAppMessageJob($message->id))->handle(
        app(WhatsAppConversationService::class)
    );
});

it('logs warning when message not found', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with(
            'ProcessWhatsAppMessageJob: message not found',
            ['message_id' => 999]
        );

    $mock = \Mockery::mock(WhatsAppConversationService::class);
    $mock->shouldNotReceive('handle');
    app()->instance(WhatsAppConversationService::class, $mock);

    (new ProcessWhatsAppMessageJob(999))->handle(
        app(WhatsAppConversationService::class)
    );
});
