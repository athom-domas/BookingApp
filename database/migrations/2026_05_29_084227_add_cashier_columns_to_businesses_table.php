<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index()->after('status');
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            $table->string('pm_expiration')->nullable()->after('pm_last_four');
            $table->timestamp('trial_ends_at')->nullable()->after('pm_expiration');
        });

        DB::table('businesses')->update(['trial_ends_at' => now()->addDays(14)]);
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'pm_expiration', 'trial_ends_at']);
        });
    }
};
