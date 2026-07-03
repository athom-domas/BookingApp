<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('platform_fee_amount', 8, 2)->default(0)->change();
        });

        DB::statement('UPDATE payments SET platform_fee_amount = platform_fee_amount / 100 WHERE platform_fee_amount >= 1');
    }

    public function down(): void
    {
        DB::statement('UPDATE payments SET platform_fee_amount = ROUND(platform_fee_amount * 100) WHERE platform_fee_amount > 0');

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('platform_fee_amount')->default(0)->change();
        });
    }
};
