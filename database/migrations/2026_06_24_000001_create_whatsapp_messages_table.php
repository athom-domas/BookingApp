<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('wamid', 255)->nullable()->unique();
            $table->string('idempotency_key', 255)->nullable()->unique();
            $table->string('phone', 30);
            $table->string('phone_normalized', 30);
            $table->string('wa_id', 50)->nullable();
            $table->string('profile_name', 255)->nullable();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('type', 30);
            $table->json('payload');
            $table->string('conversation_id', 26)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index(['business_id', 'phone_normalized']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
