<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_connect_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('stripe_account_id')->unique()->nullable();
            $table->enum('mode', ['test', 'live'])->default('test');
            $table->enum('status', ['pending', 'active', 'restricted', 'disabled'])->default('pending');
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);
            $table->json('capabilities')->nullable();
            $table->json('requirements_currently_due')->nullable();
            $table->json('requirements_past_due')->nullable();
            $table->string('requirements_disabled_reason')->nullable();
            $table->char('default_currency', 3)->nullable();
            $table->char('country', 2)->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_connect_accounts');
    }
};
