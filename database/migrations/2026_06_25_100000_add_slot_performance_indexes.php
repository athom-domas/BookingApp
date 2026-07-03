<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(
                ['business_id', 'staff_id', 'status', 'scheduled_date'],
                'appt_biz_staff_status_date'
            );
        });

        Schema::table('availability_rules', function (Blueprint $table) {
            $table->index(
                ['business_id', 'user_id', 'day_of_week', 'is_available'],
                'avail_biz_user_day_available'
            );
        });

        Schema::table('staff_blockouts', function (Blueprint $table) {
            $table->index(
                ['business_id', 'user_id', 'start_date', 'end_date'],
                'blockout_biz_user_dates'
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appt_biz_staff_status_date');
        });

        Schema::table('availability_rules', function (Blueprint $table) {
            $table->dropIndex('avail_biz_user_day_available');
        });

        Schema::table('staff_blockouts', function (Blueprint $table) {
            $table->dropIndex('blockout_biz_user_dates');
        });
    }
};
