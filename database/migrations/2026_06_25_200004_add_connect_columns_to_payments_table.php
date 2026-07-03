<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('stripe_charge_id')->nullable()->after('stripe_transaction_id');
            $table->string('stripe_application_fee_id')->nullable()->after('stripe_charge_id');
            $table->string('stripe_account_id')->nullable()->after('stripe_application_fee_id');
            $table->string('stripe_transfer_id')->nullable()->after('stripe_account_id');
            $table->decimal('platform_fee_percent', 5, 2)->nullable()->after('stripe_transfer_id');
            $table->unsignedInteger('platform_fee_amount')->default(0)->after('platform_fee_percent');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_charge_id', 'stripe_application_fee_id',
                'stripe_account_id', 'stripe_transfer_id',
                'platform_fee_percent', 'platform_fee_amount',
            ]);
        });
    }
};
