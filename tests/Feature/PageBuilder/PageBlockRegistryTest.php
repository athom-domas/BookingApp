<?php

use App\PageBlocks\PageBlockRegistry;

it('registry returns all registered block types', function () {
    $all = PageBlockRegistry::all();
    expect($all)->toHaveKeys([
        'hero', 'about', 'services', 'staff', 'gallery',
        'reviews', 'contact_info', 'map', 'cta', 'faq',
    ]);
});

it('find returns block class for known type', function () {
    $class = PageBlockRegistry::find('hero');
    expect($class)->not->toBeNull();
    expect(class_exists($class))->toBeTrue();
});

it('find returns null for unknown type', function () {
    expect(PageBlockRegistry::find('nonexistent'))->toBeNull();
});

it('isValidVariant returns true for known variant', function () {
    expect(PageBlockRegistry::isValidVariant('hero', 'classic'))->toBeTrue();
});

it('isValidVariant returns false for unknown variant', function () {
    expect(PageBlockRegistry::isValidVariant('hero', 'nonexistent_variant'))->toBeFalse();
});

it('defaultVariant returns first variant key', function () {
    $default = PageBlockRegistry::defaultVariant('hero');
    $variants = PageBlockRegistry::find('hero')::variants();
    expect($default)->toBe(array_key_first($variants));
});
