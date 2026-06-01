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
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->string('primary_color', 7)->nullable(false)->change();
        });
    }
};
