<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_settings', function (Blueprint $table) {
            $table->text('meta_whatsapp_token')->nullable()->after('twilio_from');
            $table->string('meta_whatsapp_phone_id')->nullable()->after('meta_whatsapp_token');
            $table->string('meta_whatsapp_template')->nullable()->default('appointment_reminder')->after('meta_whatsapp_phone_id');
        });

        DB::table('user_preferences')
            ->where('notification_channel', 'sms')
            ->update(['notification_channel' => 'email']);
    }

    public function down(): void
    {
        Schema::table('integration_settings', function (Blueprint $table) {
            $table->dropColumn(['meta_whatsapp_token', 'meta_whatsapp_phone_id', 'meta_whatsapp_template']);
        });
    }
};
