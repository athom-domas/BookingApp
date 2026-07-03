<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_refund_id')->unique();
            $table->unsignedInteger('amount');
            $table->string('status', 50)->default('pending');
            $table->string('reason')->nullable();
            $table->boolean('refund_application_fee')->default(true);
            $table->boolean('reverse_transfer')->default(true);
            $table->string('stripe_balance_transaction_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_refunds');
    }
};
