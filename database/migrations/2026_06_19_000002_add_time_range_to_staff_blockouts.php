<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_blockouts', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('end_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->index(['user_id', 'start_date', 'end_date'], 'staff_blockouts_user_date_range_idx');
        });
    }

    public function down(): void
    {
        Schema::table('staff_blockouts', function (Blueprint $table) {
            $table->dropIndex('staff_blockouts_user_date_range_idx');
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
