<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('block_type')->unique();
            $table->string('variant')->default('default');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedTinyInteger('schema_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_defaults');
    }
};
