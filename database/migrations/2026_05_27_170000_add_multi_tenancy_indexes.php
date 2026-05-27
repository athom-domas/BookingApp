<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One record per business for singletons
        Schema::table('system_settings',     fn(Blueprint $t) => $t->unique('business_id'));
        Schema::table('salon_profiles',      fn(Blueprint $t) => $t->unique('business_id'));
        Schema::table('integration_settings',fn(Blueprint $t) => $t->unique('business_id'));

        Schema::table('appointments', function (Blueprint $t) {
            $t->index(['business_id', 'scheduled_date']);
            $t->index(['business_id', 'status']);
            $t->index(['business_id', 'user_id']);
            $t->index(['business_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->index(['business_id', 'email']);
            $t->index(['business_id', 'created_at']);
        });

        Schema::table('services', function (Blueprint $t) {
            $t->index(['business_id', 'active']);
            $t->index(['business_id', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->index(['business_id', 'status']);
            $t->index(['business_id', 'created_at']);
        });

        Schema::table('waitlist_entries', function (Blueprint $t) {
            $t->index(['business_id', 'status']);
            $t->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings',     fn(Blueprint $t) => $t->dropUnique(['business_id']));
        Schema::table('salon_profiles',      fn(Blueprint $t) => $t->dropUnique(['business_id']));
        Schema::table('integration_settings',fn(Blueprint $t) => $t->dropUnique(['business_id']));

        Schema::table('appointments', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'scheduled_date']);
            $t->dropIndex(['business_id', 'status']);
            $t->dropIndex(['business_id', 'user_id']);
            $t->dropIndex(['business_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'email']);
            $t->dropIndex(['business_id', 'created_at']);
        });

        Schema::table('services', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'active']);
            $t->dropIndex(['business_id', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'status']);
            $t->dropIndex(['business_id', 'created_at']);
        });

        Schema::table('waitlist_entries', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'status']);
            $t->dropIndex(['business_id', 'created_at']);
        });
    }
};
