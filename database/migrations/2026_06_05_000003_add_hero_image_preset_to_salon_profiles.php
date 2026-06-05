<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->string('hero_image_preset', 80)->nullable()->after('theme_mode');
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropColumn('hero_image_preset');
        });
    }
};
