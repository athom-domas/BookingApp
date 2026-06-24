<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_message_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whatsapp_message_id')->nullable();
            $table->string('provider_message_id', 255);
            $table->enum('status', ['sent', 'delivered', 'read', 'failed']);
            $table->json('payload');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('whatsapp_message_id')->references('id')->on('whatsapp_messages')->nullOnDelete();
            $table->unique(['provider_message_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_statuses');
    }
};
