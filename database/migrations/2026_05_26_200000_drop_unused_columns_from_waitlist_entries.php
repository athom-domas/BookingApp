<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropColumn(['preferred_date_from', 'preferred_date_to', 'offer_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->date('preferred_date_from')->nullable()->after('preferred_days');
            $table->date('preferred_date_to')->nullable()->after('preferred_date_from');
            $table->timestamp('offer_expires_at')->nullable()->after('offered_slot');
        });
    }
};
