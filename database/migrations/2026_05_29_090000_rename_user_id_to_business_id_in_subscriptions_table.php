<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'stripe_status']);
            $table->renameColumn('user_id', 'business_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['business_id', 'stripe_status']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'stripe_status']);
            $table->renameColumn('business_id', 'user_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'stripe_status']);
        });
    }
};
