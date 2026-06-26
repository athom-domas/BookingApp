<?php

use App\Models\PageTemplate;
use App\Models\PageTemplateBlock;
use App\Models\BusinessPageBlock;
use App\Models\Business;
use Illuminate\Support\Facades\Schema;

it('page_templates table has correct columns', function () {
    expect(Schema::hasColumns('page_templates', [
        'id', 'name', 'slug', 'description', 'is_active', 'is_default', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('page_template_blocks table has correct columns', function () {
    expect(Schema::hasColumns('page_template_blocks', [
        'id', 'page_template_id', 'block_type', 'variant', 'sort_order',
        'is_enabled', 'is_required', 'is_locked', 'content', 'settings', 'schema_version',
    ]))->toBeTrue();
});

it('business_page_blocks table has correct columns', function () {
    expect(Schema::hasColumns('business_page_blocks', [
        'id', 'business_id', 'page_template_id', 'page_template_block_id',
        'block_type', 'variant', 'sort_order', 'is_enabled', 'is_required', 'is_locked',
        'content', 'settings', 'schema_version',
    ]))->toBeTrue();
});

it('salon_profiles has page_template_id column', function () {
    expect(Schema::hasColumn('salon_profiles', 'page_template_id'))->toBeTrue();
});

it('PageTemplate has correct casts', function () {
    $template = PageTemplate::factory()->create(['is_active' => true, 'is_default' => false]);
    expect($template->is_active)->toBeBool();
    expect($template->is_default)->toBeBool();
});

it('PageTemplateBlock content is always array', function () {
    $block = PageTemplateBlock::factory()->create();
    expect($block->fresh()->content)->toBeArray();
});

it('BusinessPageBlock content is always array', function () {
    $block = BusinessPageBlock::factory()->create();
    expect($block->fresh()->content)->toBeArray();
});

it('BusinessPageBlock belongs to Business', function () {
    $block = BusinessPageBlock::factory()->create();
    expect($block->business)->toBeInstanceOf(Business::class);
});

it('PageTemplate has pageTemplateBlocks relation', function () {
    $template = PageTemplate::factory()->create();
    PageTemplateBlock::factory()->count(3)->create(['page_template_id' => $template->id]);
    expect($template->pageTemplateBlocks)->toHaveCount(3);
});
