<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table): void {
            $table->string('font_pair',    20)->nullable()->after('theme');
            $table->string('border_style', 20)->nullable()->after('font_pair');
            $table->string('bg_texture',   20)->nullable()->after('border_style');
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table): void {
            $table->dropColumn(['font_pair', 'border_style', 'bg_texture']);
        });
    }
};
