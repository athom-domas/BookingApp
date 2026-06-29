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

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;

it('page-builder:init creates blocks for uninitialized businesses', function () {
    $this->seed(PageBuilderSeeder::class);
    $business = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $business->id]);

    $this->artisan('page-builder:init')->assertSuccessful();

    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBeGreaterThan(0);
});

it('page-builder:init skips already initialized businesses', function () {
    $this->seed(PageBuilderSeeder::class);
    $business = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $business->id]);
    BusinessPageBlock::factory()->create(['business_id' => $business->id]);

    $this->artisan('page-builder:init')->assertSuccessful();

    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(1);
});

it('page-builder:init --business targets a single business', function () {
    $this->seed(PageBuilderSeeder::class);
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $b1->id]);
    SalonProfile::factory()->create(['business_id' => $b2->id]);

    $this->artisan("page-builder:init --business={$b1->id}")->assertSuccessful();

    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $b1->id)->count())->toBeGreaterThan(0);
    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $b2->id)->count())->toBe(0);
});
