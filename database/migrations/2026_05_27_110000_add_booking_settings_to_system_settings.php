<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedInteger('booking_max_days_ahead')->default(90)->after('slot_granularity_minutes');
            $table->unsignedInteger('cancellation_deadline_hours')->default(24)->after('booking_max_days_ahead');
            $table->unsignedTinyInteger('reminder_count')->default(1)->after('cancellation_deadline_hours');
            $table->unsignedInteger('reminder_1_hours')->default(24)->after('reminder_count');
            $table->unsignedInteger('reminder_2_hours')->default(2)->after('reminder_1_hours');
            $table->boolean('stripe_enabled')->default(true)->after('reminder_2_hours');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'booking_max_days_ahead',
                'cancellation_deadline_hours',
                'reminder_count',
                'reminder_1_hours',
                'reminder_2_hours',
                'stripe_enabled',
            ]);
        });
    }
};
