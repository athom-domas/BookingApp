<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->enum('plan', ['base', 'plus'])->default('base')->after('status');
            $table->enum('plan_override', ['base', 'plus'])->nullable()->after('plan');
            $table->timestamp('plan_override_expires_at')->nullable()->after('plan_override');
            $table->string('plan_override_reason')->nullable()->after('plan_override_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'plan_override',
                'plan_override_expires_at',
                'plan_override_reason',
            ]);
        });
    }
};
