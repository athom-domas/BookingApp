<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('business_id');
        });

        DB::statement('
            UPDATE users u
            JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY business_id ORDER BY created_at, id) AS rn
                FROM users
                WHERE business_id IS NOT NULL
            ) ranked ON u.id = ranked.id
            SET u.sort_order = ranked.rn
        ');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
