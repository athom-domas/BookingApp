<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_page_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_template_block_id')->nullable()->constrained('page_template_blocks')->nullOnDelete();
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

            $table->index(['business_id', 'is_enabled', 'sort_order']);
            $table->index(['business_id', 'block_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_page_blocks');
    }
};
