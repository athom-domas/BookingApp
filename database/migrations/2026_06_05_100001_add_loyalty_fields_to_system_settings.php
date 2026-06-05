<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('loyalty_enabled')->default(false)->after('reviews_enabled');
            $table->unsignedInteger('loyalty_points_per_euro')->default(1)->after('loyalty_enabled');
            $table->unsignedInteger('loyalty_reward_threshold')->default(100)->after('loyalty_points_per_euro');
            $table->unsignedInteger('loyalty_reward_percentage')->default(10)->after('loyalty_reward_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'loyalty_enabled',
                'loyalty_points_per_euro',
                'loyalty_reward_threshold',
                'loyalty_reward_percentage',
            ]);
        });
    }
};
