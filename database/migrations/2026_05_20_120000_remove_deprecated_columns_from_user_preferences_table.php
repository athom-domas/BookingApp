<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropForeign(['preferred_staff']);
            $table->dropColumn(['preferred_staff', 'slot_duration_minutes', 'timezone']);
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->string('timezone')->default('UTC');
            $table->foreignId('preferred_staff')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('slot_duration_minutes')->default(60);
        });
    }
};
