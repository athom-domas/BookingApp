<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\PageBlocks\Contracts\PageBlockContract;

abstract class AbstractPageBlock implements PageBlockContract
{
    public static function description(): string
    {
        return '';
    }

    public static function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function defaultContent(): array
    {
        return [];
    }

    public static function defaultSettings(): array
    {
        return [];
    }

    public static function contentRules(): array
    {
        return [];
    }

    public static function settingsRules(): array
    {
        return [];
    }

    public static function filamentFields(): array
    {
        return [];
    }

    public static function viewFor(string $variant): string
    {
        return 'page-blocks.' . static::type() . '.' . $variant;
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        return [];
    }

    public static function defaultVariant(): string
    {
        return array_key_first(static::variants());
    }
}
