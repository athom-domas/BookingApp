<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. DROP time_slots — slot calcolati dinamicamente da qui in poi
        Schema::dropIfExists('time_slots');

        // 2. Aggiungi nuovi campi a system_settings
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'slot_granularity_minutes')) {
                $table->integer('slot_granularity_minutes')->default(10)->after('slot_generation_weeks');
            }
            if (! Schema::hasColumn('system_settings', 'min_service_duration_minutes')) {
                $table->integer('min_service_duration_minutes')->default(15)->after('slot_granularity_minutes');
            }
            if (! Schema::hasColumn('system_settings', 'timezone')) {
                $table->string('timezone')->default('Europe/Rome')->after('min_service_duration_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->onDelete('set null');
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });

        Schema::table('system_settings', function (Blueprint $table) {
            foreach (['slot_granularity_minutes', 'min_service_duration_minutes', 'timezone'] as $col) {
                if (Schema::hasColumn('system_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
