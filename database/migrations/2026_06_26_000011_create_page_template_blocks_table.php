<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('page_template_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_template_id')->constrained()->cascadeOnDelete();
            $table->string('block_type');
            $table->string('variant');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->json('content');
            $table->json('settings');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamps();

            $table->index(['page_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_template_blocks');
    }
};
