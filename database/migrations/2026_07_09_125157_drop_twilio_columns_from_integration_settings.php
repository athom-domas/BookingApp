<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_settings', function (Blueprint $table) {
            $table->dropColumn(['twilio_sid', 'twilio_token', 'twilio_from']);
        });
    }

    public function down(): void
    {
        Schema::table('integration_settings', function (Blueprint $table) {
            $table->string('twilio_sid')->nullable();
            $table->string('twilio_token')->nullable();
            $table->string('twilio_from')->nullable();
        });
    }
};
