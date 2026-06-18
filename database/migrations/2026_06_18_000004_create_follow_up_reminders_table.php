<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('rebooking');
            $table->string('channel')->default('email');
            $table->unsignedSmallInteger('delay_days');
            $table->dateTime('scheduled_for');
            $table->dateTime('sent_at')->nullable();
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'skipped'])->default('pending');
            $table->dateTime('processing_at')->nullable();
            $table->string('skipped_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['business_id', 'status', 'scheduled_for']);
            $table->index(['business_id', 'user_id', 'type', 'status']);
            $table->index(['business_id', 'user_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_reminders');
    }
};
