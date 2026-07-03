<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_page_blocks', function (Blueprint $table) {
            $table->dropForeign(['page_template_id']);
            $table->dropForeign(['page_template_block_id']);
            $table->dropColumn(['page_template_id', 'page_template_block_id']);
        });

        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropForeign(['page_template_id']);
            $table->dropColumn('page_template_id');
        });

        Schema::dropIfExists('page_template_blocks');
        Schema::dropIfExists('page_templates');
    }

    public function down(): void
    {
        Schema::create('page_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('page_template_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_template_id')->constrained()->cascadeOnDelete();
            $table->string('block_type');
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

        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->foreignId('page_template_id')->nullable()->constrained('page_templates')->nullOnDelete();
        });

        Schema::table('business_page_blocks', function (Blueprint $table) {
            $table->foreignId('page_template_id')->nullable()->constrained('page_templates')->nullOnDelete();
            $table->foreignId('page_template_block_id')->nullable()->constrained('page_template_blocks')->nullOnDelete();
        });
    }
};
