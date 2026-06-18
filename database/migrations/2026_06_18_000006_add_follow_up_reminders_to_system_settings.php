<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('follow_up_reminders_enabled')->default(false)->after('review_request_delay_hours');
            $table->unsignedSmallInteger('follow_up_reminder_days')->default(30)->after('follow_up_reminders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['follow_up_reminders_enabled', 'follow_up_reminder_days']);
        });
    }
};
