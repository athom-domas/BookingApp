<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->enum('notification_channel', ['email', 'sms', 'whatsapp'])->default('email')->after('slot_duration_minutes');
            $table->dropColumn(['receive_email_reminders', 'receive_sms_reminders']);
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->boolean('receive_email_reminders')->default(true)->after('user_id');
            $table->boolean('receive_sms_reminders')->default(false)->after('receive_email_reminders');
            $table->dropColumn('notification_channel');
        });
    }
};
