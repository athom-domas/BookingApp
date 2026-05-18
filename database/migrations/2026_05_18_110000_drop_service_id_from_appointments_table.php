<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill service_ids for any row that already has service_id but no service_ids
        DB::statement('
            UPDATE appointments
            SET service_ids = JSON_ARRAY(service_id)
            WHERE service_ids IS NULL AND service_id IS NOT NULL
        ');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }
};
