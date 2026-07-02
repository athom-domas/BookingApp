<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_stripe_transaction_id_unique');
            $table->unique(['stripe_transaction_id', 'stripe_account_id'], 'payments_stripe_txn_account_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_stripe_txn_account_unique');
            $table->unique('stripe_transaction_id');
        });
    }
};
