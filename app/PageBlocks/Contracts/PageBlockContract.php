<?php

namespace App\PageBlocks\Contracts;

use App\Models\Business;
use App\Models\BusinessPageBlock;

interface PageBlockContract
{
    public static function type(): string;

    public static function label(): string;

    public static function description(): string;

    public static function icon(): string;

    /** @return array<string, array{label: string, description: string}> */
    public static function variants(): array;

    public static function defaultVariant(): string;

    public static function defaultContent(): array;

    public static function defaultSettings(): array;

    public static function contentRules(): array;

    public static function settingsRules(): array;

    /** Filament form fields. Fields use state paths content.* and settings.* */
    public static function filamentFields(): array;

    /** Blade view path for the given variant, e.g. 'page-blocks.hero.classic' */
    public static function viewFor(string $variant): string;

    /**
     * Data injected into the view at render time.
     * Static blocks return []. Dynamic blocks return model collections.
     */
    public static function resolveData(Business $business, BusinessPageBlock $block): array;
}
