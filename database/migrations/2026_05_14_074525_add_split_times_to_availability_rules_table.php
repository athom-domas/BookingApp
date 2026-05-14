<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availability_rules', function (Blueprint $table) {
            $table->time('start_time_2')->nullable()->after('end_time');
            $table->time('end_time_2')->nullable()->after('start_time_2');
        });
    }

    public function down(): void
    {
        Schema::table('availability_rules', function (Blueprint $table) {
            $table->dropColumn(['start_time_2', 'end_time_2']);
        });
    }
};
