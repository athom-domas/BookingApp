<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('service_ids');
            $table->foreignId('preferred_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->time('preferred_time_from');
            $table->time('preferred_time_to');
            $table->json('preferred_days');
            $table->enum('status', ['waiting', 'notified', 'booked', 'expired', 'cancelled'])->default('waiting');
            $table->json('offered_slot')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
