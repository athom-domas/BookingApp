<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->json('service_ids')->nullable();
            $table->timestamp('scheduled_date');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->timestamp('customer_confirmed_at')->nullable();
            $table->decimal('final_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('google_event_id')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_date');
            $table->index(['business_id', 'scheduled_date']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'user_id']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
