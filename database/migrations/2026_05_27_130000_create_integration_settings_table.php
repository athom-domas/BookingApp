<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_public_key')->nullable();
            $table->text('stripe_secret_key')->nullable();
            $table->text('stripe_webhook_secret')->nullable();
            $table->string('twilio_sid')->nullable();
            $table->text('twilio_token')->nullable();
            $table->string('twilio_from')->nullable();
            $table->string('google_calendar_id')->nullable();
            $table->text('google_credentials_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
