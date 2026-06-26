<?php

use App\Models\PageTemplate;
use App\Models\PageTemplateBlock;
use Database\Seeders\PageBuilderSeeder;

it('PageBuilderSeeder creates Default template with 10 blocks', function () {
    $this->seed(PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'default')->first();
    expect($template)->not->toBeNull();
    expect($template->pageTemplateBlocks)->toHaveCount(10);
});

it('PageBuilderSeeder marks hero and contact_info as required in Default', function () {
    $this->seed(PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'default')->first();
    $required = $template->pageTemplateBlocks->where('is_required', true)->pluck('block_type')->toArray();
    expect($required)->toContain('hero');
    expect($required)->toContain('contact_info');
});

it('PageBuilderSeeder creates Minimal template with 4 blocks', function () {
    $this->seed(PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'minimal')->first();
    expect($template)->not->toBeNull();
    expect($template->pageTemplateBlocks)->toHaveCount(4);
});

it('PageBuilderSeeder marks hero and contact_info as required in Minimal', function () {
    $this->seed(PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'minimal')->first();
    $required = $template->pageTemplateBlocks->where('is_required', true)->pluck('block_type')->toArray();
    expect($required)->toContain('hero');
    expect($required)->toContain('contact_info');
});

it('PageBuilderSeeder creates Premium template with 7 blocks', function () {
    $this->seed(PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'premium')->first();
    expect($template)->not->toBeNull();
    expect($template->pageTemplateBlocks)->toHaveCount(7);
});

it('PageBuilderSeeder marks hero and contact_info as required in Premium', function () {
    $this->seed(PageBuilderSeeder::class);

    $template = PageTemplate::where('slug', 'premium')->first();
    $required = $template->pageTemplateBlocks->where('is_required', true)->pluck('block_type')->toArray();
    expect($required)->toContain('hero');
    expect($required)->toContain('contact_info');
});

it('PageBuilderSeeder is idempotent', function () {
    $this->seed(PageBuilderSeeder::class);
    $this->seed(PageBuilderSeeder::class);

    expect(PageTemplate::where('slug', 'default')->count())->toBe(1);
    expect(PageTemplate::count())->toBe(3);
});
