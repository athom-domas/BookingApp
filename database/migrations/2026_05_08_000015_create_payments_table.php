<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'refunded', 'failed', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['stripe', 'cash', 'pos'])->default('stripe');
            $table->string('stripe_transaction_id')->nullable()->unique();
            $table->json('stripe_response')->nullable();
            $table->unsignedTinyInteger('loyalty_discount_percentage')->nullable();
            $table->decimal('loyalty_original_amount', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
