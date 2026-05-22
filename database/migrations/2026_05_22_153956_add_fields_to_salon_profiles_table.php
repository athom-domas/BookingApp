<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            $table->longText('description')->nullable()->after('tagline');
            $table->longText('cancellation_policy')->nullable()->after('description');
            $table->text('google_maps_embed')->nullable()->after('cancellation_policy');
            $table->json('opening_hours')->nullable()->after('google_maps_embed');
            $table->string('instagram_url')->nullable()->after('opening_hours');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('tiktok_url')->nullable()->after('facebook_url');
            $table->string('whatsapp_number')->nullable()->after('tiktok_url');
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'tagline', 'description', 'cancellation_policy',
                'google_maps_embed', 'opening_hours',
                'instagram_url', 'facebook_url', 'tiktok_url', 'whatsapp_number',
            ]);
        });
    }
};
