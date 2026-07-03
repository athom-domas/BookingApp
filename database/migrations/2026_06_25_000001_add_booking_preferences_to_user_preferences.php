<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->json('preferred_days')->nullable()->after('phone_number');
            $table->time('preferred_time_from')->nullable()->after('preferred_days');
            $table->time('preferred_time_to')->nullable()->after('preferred_time_from');
            $table->boolean('booking_preference_prompt_dismissed')->default(false)->after('preferred_time_to');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_days',
                'preferred_time_from',
                'preferred_time_to',
                'booking_preference_prompt_dismissed',
            ]);
        });
    }
};
