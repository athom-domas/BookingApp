<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('salon_profiles')
            ->where('theme', 'antracite')
            ->update(['theme' => 'notte']);
    }

    public function down(): void
    {
        // antracite is no longer a valid theme — no rollback
    }
};
