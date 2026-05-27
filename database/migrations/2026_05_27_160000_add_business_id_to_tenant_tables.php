<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tenantTables = [
        'services', 'appointments', 'availability_rules',
        'appointment_reminders', 'payments', 'user_preferences',
        'system_settings', 'salon_profiles', 'salon_reviews',
        'waitlist_entries', 'integration_settings',
    ];

    public function up(): void
    {
        foreach ($this->tenantTables as $table) {
            if (! Schema::hasColumn($table, 'business_id')) {
                Schema::table($table, fn(Blueprint $t) =>
                    $t->unsignedBigInteger('business_id')->nullable()->after('id')
                );
            }
        }
        if (! Schema::hasColumn('users', 'business_id')) {
            Schema::table('users', fn(Blueprint $t) =>
                $t->unsignedBigInteger('business_id')->nullable()->after('id')
            );
        }

        foreach ([...$this->tenantTables, 'users'] as $table) {
            DB::table($table)->whereNull('business_id')->update(['business_id' => 1]);
        }

        foreach ($this->tenantTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('business_id')->nullable(false)->change();
                $t->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            });
        }

        Schema::table('users', fn(Blueprint $t) =>
            $t->foreign('business_id')->references('id')->on('businesses')->nullOnDelete()
        );
    }

    public function down(): void
    {
        foreach ([...$this->tenantTables, 'users'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['business_id']);
                $t->dropColumn('business_id');
            });
        }
    }
};
