<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('stripe_enabled');
            $table->string('payment_mode')->default('both')->after('reminder_2_hours');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
            $table->boolean('stripe_enabled')->default(true)->after('reminder_2_hours');
        });
    }
};
