<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->boolean('announcement_active')->default(false)->after('tagline');
            $table->string('announcement_text')->nullable()->after('announcement_active');
            $table->string('booking_button_label')->nullable()->after('announcement_text');
            $table->string('meta_description')->nullable()->after('booking_button_label');
            $table->string('google_review_url')->nullable()->after('meta_description');
            $table->text('owner_signature')->nullable()->after('google_review_url');

            if (Schema::hasColumn('salon_profiles', 'cancellation_policy')) {
                $table->dropColumn('cancellation_policy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'announcement_active', 'announcement_text', 'booking_button_label',
                'meta_description', 'google_review_url', 'owner_signature',
            ]);
            $table->longText('cancellation_policy')->nullable()->after('description');
        });
    }
};
