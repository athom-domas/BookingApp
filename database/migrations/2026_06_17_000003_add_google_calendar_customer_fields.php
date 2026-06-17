<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('google_refresh_token')->nullable()->after('google_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('customer_google_event_id')->nullable()->after('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_refresh_token');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('customer_google_event_id');
        });
    }
};
