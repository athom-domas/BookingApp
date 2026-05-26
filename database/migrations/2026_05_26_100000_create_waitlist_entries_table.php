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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('service_ids');
            $table->foreignId('preferred_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('preferred_date_from');
            $table->date('preferred_date_to');
            $table->time('preferred_time_from');
            $table->time('preferred_time_to');
            $table->json('preferred_days');
            $table->enum('status', ['waiting', 'notified', 'booked', 'expired', 'cancelled'])->default('waiting');
            $table->json('offered_slot')->nullable();
            $table->datetime('offer_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
