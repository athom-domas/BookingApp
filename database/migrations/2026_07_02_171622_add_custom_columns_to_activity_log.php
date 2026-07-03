<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete()->after('id');
            $table->string('type', 20)->nullable()->after('properties');   // activity, error, system
            $table->string('level', 20)->nullable()->after('type');        // info, warning, error, critical
            $table->string('source', 30)->nullable()->after('level');      // model_event, exception_reporter, manual, webhook
            $table->string('ip_address', 45)->nullable()->after('source');
            $table->string('user_agent', 500)->nullable()->after('ip_address');
            $table->string('url', 2000)->nullable()->after('user_agent');
            $table->string('method', 10)->nullable()->after('url');

            $table->index('business_id');
            $table->index(['type', 'level']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropIndex(['business_id']);
            $table->dropIndex(['type', 'level']);
            $table->dropColumn(['business_id', 'type', 'level', 'source', 'ip_address', 'user_agent', 'url', 'method']);
        });
    }
};
