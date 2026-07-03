<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('system_settings')->whereNull('business_id')->delete();

        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id')->nullable(false)->change();
        });
    }
};
