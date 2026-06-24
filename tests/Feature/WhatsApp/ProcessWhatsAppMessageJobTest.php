<?php
use App\Jobs\ProcessWhatsAppMessageJob;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppConversationService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    IntegrationSetting::current()->update(['whatsapp_ai_enabled' => true]);
});

it('dispatches to the whatsapp queue', function () {
    Queue::fake();

    ProcessWhatsAppMessageJob::dispatch(1, app('current_business_id'));

    Queue::assertPushedOn('whatsapp', ProcessWhatsAppMessageJob::class);
});

it('calls conversation service handle', function () {
    $message = WhatsAppMessage::create([
        'business_id'     => app('current_business_id'),
        'wamid'           => 'wamid.job1',
        'phone'           => '+393401234567',
        'phone_normalized'=> '+393401234567',
        'direction'       => 'inbound',
        'type'            => 'text',
        'payload'         => ['text' => ['body' => 'test'], 'timestamp' => (string) now()->timestamp],
    ]);

    $mock = Mockery::mock(WhatsAppConversationService::class);
    $mock->shouldReceive('handle')->once()->with($message->id, $message->business_id);
    app()->instance(WhatsAppConversationService::class, $mock);

    (new ProcessWhatsAppMessageJob($message->id, $message->business_id))->handle(
        app(WhatsAppConversationService::class)
    );
});
