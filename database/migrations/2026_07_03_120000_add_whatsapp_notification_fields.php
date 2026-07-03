<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->after('business_id')
                ->constrained()->nullOnDelete();
            $table->string('template_name', 100)->nullable()->after('type');
            $table->string('status', 20)->nullable()->after('template_name');
            $table->timestamp('sent_at')->nullable()->after('processed_at');

            $table->index(['business_id', 'appointment_id', 'template_name'], 'wa_messages_notification_idx');
        });

        Schema::table('integration_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_notifications_enabled')->default(false);
            $table->unsignedInteger('whatsapp_monthly_limit')->nullable();
            $table->unsignedInteger('whatsapp_monthly_sent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wa_messages_notification_idx');
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropColumn(['template_name', 'status', 'sent_at']);
        });

        Schema::table('integration_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_notifications_enabled',
                'whatsapp_monthly_limit',
                'whatsapp_monthly_sent',
            ]);
        });
    }
};
