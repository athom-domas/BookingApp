<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('name')->default('Il mio salone');
            $table->string('tagline')->nullable();
            $table->boolean('announcement_active')->default(false);
            $table->string('announcement_text')->nullable();
            $table->string('booking_button_label')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('google_review_url')->nullable();
            $table->text('owner_signature')->nullable();
            $table->longText('description')->nullable();
            $table->text('google_maps_embed')->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->text('email_greeting')->nullable();
            $table->text('email_footer_note')->nullable();
            $table->string('email_accent_color', 7)->nullable();
            $table->string('theme')->default('luxury');
            $table->string('theme_mode', 10)->default('light');
            $table->string('hero_image_preset', 80)->nullable();
            $table->string('font_pair', 20)->nullable();
            $table->string('border_style', 20)->nullable();
            $table->string('bg_texture', 20)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_profiles');
    }
};
