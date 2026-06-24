<?php

use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageStatus;
use App\Models\Business;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
});

it('creates a whatsapp_message record', function () {
    $msg = WhatsAppMessage::create([
        'business_id'    => app('current_business_id'),
        'wamid'          => 'wamid.abc123',
        'phone'          => '+393401234567',
        'phone_normalized' => '+393401234567',
        'direction'      => 'inbound',
        'type'           => 'text',
        'payload'        => ['text' => ['body' => 'Ciao']],
    ]);

    expect($msg->id)->toBeInt();
    expect($msg->wamid)->toBe('wamid.abc123');
});

it('prevents duplicate wamid', function () {
    WhatsAppMessage::create([
        'business_id'     => app('current_business_id'),
        'wamid'           => 'wamid.dup',
        'phone'           => '+393401234567',
        'phone_normalized'=> '+393401234567',
        'direction'       => 'inbound',
        'type'            => 'text',
        'payload'         => [],
    ]);

    expect(fn () => WhatsAppMessage::create([
        'business_id'     => app('current_business_id'),
        'wamid'           => 'wamid.dup',
        'phone'           => '+393401234567',
        'phone_normalized'=> '+393401234567',
        'direction'       => 'inbound',
        'type'            => 'text',
        'payload'         => [],
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('creates a whatsapp_message_status record', function () {
    $msg = WhatsAppMessage::create([
        'business_id'     => app('current_business_id'),
        'phone'           => '+393401234567',
        'phone_normalized'=> '+393401234567',
        'direction'       => 'outbound',
        'type'            => 'text',
        'payload'         => [],
    ]);

    $status = WhatsAppMessageStatus::create([
        'whatsapp_message_id' => $msg->id,
        'provider_message_id' => 'wamid.out1',
        'status'              => 'delivered',
        'payload'             => ['timestamp' => '1234567890'],
    ]);

    expect($status->status)->toBe('delivered');
});

it('prevents duplicate provider_message_id + status', function () {
    WhatsAppMessageStatus::create([
        'provider_message_id' => 'wamid.out2',
        'status'              => 'sent',
        'payload'             => [],
    ]);

    expect(fn () => WhatsAppMessageStatus::create([
        'provider_message_id' => 'wamid.out2',
        'status'              => 'sent',
        'payload'             => [],
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
