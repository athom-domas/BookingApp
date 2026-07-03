<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->string('shop_header_variant', 20)->nullable()->after('bg_texture');
            $table->string('shop_header_title', 120)->nullable()->after('shop_header_variant');
            $table->string('shop_header_subtitle', 200)->nullable()->after('shop_header_title');
            $table->string('shop_header_image')->nullable()->after('shop_header_subtitle');
            $table->string('shop_header_image_mobile')->nullable()->after('shop_header_image');
            $table->string('shop_header_image_preset', 50)->nullable()->after('shop_header_image_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'shop_header_variant',
                'shop_header_title',
                'shop_header_subtitle',
                'shop_header_image',
                'shop_header_image_mobile',
                'shop_header_image_preset',
            ]);
        });
    }
};
