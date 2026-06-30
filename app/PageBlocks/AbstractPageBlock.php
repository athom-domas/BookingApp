<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\PageBlocks\Contracts\PageBlockContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

    public static function filamentFields(?BusinessPageBlock $record = null): array
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

    public static function storeAsWebp(TemporaryUploadedFile $file, string $directory): string
    {
        Storage::disk('public')->makeDirectory($directory);
        $path = $directory . '/' . Str::uuid() . '.webp';
        $fullPath = Storage::disk('public')->path($path);
        $img = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        imagewebp($img, $fullPath, 82);
        return $path;
    }
}
