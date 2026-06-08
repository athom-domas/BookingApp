<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedTinyInteger('loyalty_discount_percentage')->nullable()->after('stripe_response');
            $table->decimal('loyalty_original_amount', 10, 2)->nullable()->after('loyalty_discount_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['loyalty_discount_percentage', 'loyalty_original_amount']);
        });
    }
};
