<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('integration_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_ai_enabled')->default(false)->after('meta_whatsapp_template');
            $table->boolean('whatsapp_ai_booking_enabled')->default(true)->after('whatsapp_ai_enabled');
            $table->boolean('whatsapp_ai_cancellation_enabled')->default(false)->after('whatsapp_ai_booking_enabled');
            $table->text('whatsapp_ai_custom_instructions')->nullable()->after('whatsapp_ai_cancellation_enabled');
            $table->string('whatsapp_ai_handoff_email')->nullable()->after('whatsapp_ai_custom_instructions');
            $table->string('whatsapp_ai_timezone', 50)->nullable()->after('whatsapp_ai_handoff_email');
            $table->string('whatsapp_ai_language', 10)->nullable()->after('whatsapp_ai_timezone');
            $table->unsignedSmallInteger('whatsapp_ai_max_turns')->default(12)->after('whatsapp_ai_language');
        });
    }

    public function down(): void
    {
        Schema::table('integration_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_ai_enabled', 'whatsapp_ai_booking_enabled', 'whatsapp_ai_cancellation_enabled',
                'whatsapp_ai_custom_instructions', 'whatsapp_ai_handoff_email',
                'whatsapp_ai_timezone', 'whatsapp_ai_language', 'whatsapp_ai_max_turns',
            ]);
        });
    }
};
