<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        // Backfill existing admin users so they can still access their panel after deploy.
        DB::table('users')
            ->whereNotNull('business_id')
            ->whereIn('id', function ($sub) {
                $sub->select('model_id')
                    ->from('model_has_roles')
                    ->where('model_type', 'App\\Models\\User')
                    ->whereIn('role_id', function ($q) {
                        $q->select('id')->from('roles')
                          ->where('name', 'admin')
                          ->where('guard_name', 'web');
                    });
            })
            ->select('id', 'business_id')
            ->get()
            ->each(fn ($u) => DB::table('business_user')->insertOrIgnore([
                'business_id' => $u->business_id,
                'user_id'     => $u->id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');
    }
};
