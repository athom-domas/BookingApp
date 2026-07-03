<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delete gallery and portfolio media for all salon profiles
        DB::table('media')
            ->where('model_type', 'App\\Models\\SalonProfile')
            ->whereIn('collection_name', ['gallery', 'portfolio'])
            ->delete();

        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'booking_button_label', 'description', 'owner_signature']);
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            $table->string('booking_button_label')->nullable()->after('announcement_text');
            $table->longText('description')->nullable()->after('address');
            $table->text('owner_signature')->nullable()->after('meta_description');
        });
    }
};
