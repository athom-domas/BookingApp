<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete()->unique();
            $table->unsignedInteger('slot_generation_weeks')->default(4);
            $table->integer('slot_granularity_minutes')->default(10);
            $table->string('timezone')->default('Europe/Rome');
            $table->unsignedInteger('booking_max_days_ahead')->default(90);
            $table->unsignedInteger('cancellation_deadline_hours')->default(24);
            $table->unsignedTinyInteger('reminder_count')->default(1);
            $table->unsignedInteger('reminder_1_hours')->default(24);
            $table->unsignedInteger('reminder_2_hours')->default(2);
            $table->string('payment_mode')->default('both');
            $table->boolean('reviews_enabled')->default(true);
            $table->boolean('loyalty_enabled')->default(false);
            $table->unsignedInteger('loyalty_points_per_euro')->default(1);
            $table->unsignedInteger('loyalty_reward_threshold')->default(100);
            $table->unsignedInteger('loyalty_reward_percentage')->default(10);
            $table->json('low_stock_notify_user_ids')->nullable();
            $table->json('order_notify_user_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
