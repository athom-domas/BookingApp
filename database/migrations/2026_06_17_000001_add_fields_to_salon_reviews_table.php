<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('business_id');
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete()->after('user_id');
            $table->timestamp('seen_at')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('salon_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropColumn('seen_at');
        });
    }
};
