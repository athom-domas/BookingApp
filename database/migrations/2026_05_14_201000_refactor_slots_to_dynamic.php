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

        // 2. CREATE appointment_holds — blocco temporaneo per concorrenza
        Schema::create('appointment_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->string('session_id');
            $table->foreignId('customer_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->json('service_ids');
            $table->enum('status', ['active', 'expired', 'converted', 'abandoned'])->default('active');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['staff_id', 'starts_at']);
            $table->index(['session_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['expires_at', 'status']);
        });

        // 3. Aggiungi nuovi campi a system_settings
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'slot_granularity_minutes')) {
                $table->integer('slot_granularity_minutes')->default(10)->after('slot_generation_weeks');
            }
            if (! Schema::hasColumn('system_settings', 'hold_duration_minutes')) {
                $table->integer('hold_duration_minutes')->default(5)->after('slot_granularity_minutes');
            }
            if (! Schema::hasColumn('system_settings', 'hold_extension_minutes')) {
                $table->integer('hold_extension_minutes')->default(5)->after('hold_duration_minutes');
            }
            if (! Schema::hasColumn('system_settings', 'min_service_duration_minutes')) {
                $table->integer('min_service_duration_minutes')->default(15)->after('hold_extension_minutes');
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

        Schema::dropIfExists('appointment_holds');

        Schema::table('system_settings', function (Blueprint $table) {
            foreach (['slot_granularity_minutes', 'hold_duration_minutes', 'hold_extension_minutes', 'min_service_duration_minutes', 'timezone'] as $col) {
                if (Schema::hasColumn('system_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
