<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->string('primary_color', 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('salon_profiles')
            ->whereNull('primary_color')
            ->update(['primary_color' => '#9E8A70']);

        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->string('primary_color', 7)->nullable(false)->change();
        });
    }
};
