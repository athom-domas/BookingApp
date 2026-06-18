<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('review_request_enabled')->default(false)->after('reviews_enabled');
            $table->unsignedTinyInteger('review_request_delay_hours')->default(2)->after('review_request_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['review_request_enabled', 'review_request_delay_hours']);
        });
    }
};
