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
        // MySQL uses a composite index (with business_id as leftmost column) as the FK's
        // supporting index, dropping the original single-column index. Drop FK first so the
        // composite indexes can be removed, then restore FK for migration 160000's down().
        foreach (['system_settings', 'salon_profiles', 'integration_settings'] as $table) {
            Schema::table($table, fn(Blueprint $t) => $t->dropForeign(['business_id']));
            Schema::table($table, fn(Blueprint $t) => $t->dropUnique(['business_id']));
            Schema::table($table, fn(Blueprint $t) => $t->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete());
        }

        Schema::table('appointments', fn(Blueprint $t) => $t->dropForeign(['business_id']));
        Schema::table('appointments', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'scheduled_date']);
            $t->dropIndex(['business_id', 'status']);
            $t->dropIndex(['business_id', 'user_id']);
            $t->dropIndex(['business_id', 'created_at']);
        });
        Schema::table('appointments', fn(Blueprint $t) => $t->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete());

        Schema::table('users', fn(Blueprint $t) => $t->dropForeign(['business_id']));
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'email']);
            $t->dropIndex(['business_id', 'created_at']);
        });
        Schema::table('users', fn(Blueprint $t) => $t->foreign('business_id')->references('id')->on('businesses')->nullOnDelete());

        Schema::table('services', fn(Blueprint $t) => $t->dropForeign(['business_id']));
        Schema::table('services', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'active']);
            $t->dropIndex(['business_id', 'created_at']);
        });
        Schema::table('services', fn(Blueprint $t) => $t->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete());

        Schema::table('payments', fn(Blueprint $t) => $t->dropForeign(['business_id']));
        Schema::table('payments', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'status']);
            $t->dropIndex(['business_id', 'created_at']);
        });
        Schema::table('payments', fn(Blueprint $t) => $t->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete());

        Schema::table('waitlist_entries', fn(Blueprint $t) => $t->dropForeign(['business_id']));
        Schema::table('waitlist_entries', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'status']);
            $t->dropIndex(['business_id', 'created_at']);
        });
        Schema::table('waitlist_entries', fn(Blueprint $t) => $t->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete());
    }
};
