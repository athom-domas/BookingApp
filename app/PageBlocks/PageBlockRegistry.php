<?php

namespace App\PageBlocks;

class PageBlockRegistry
{
    public static function all(): array
    {
        return [
            'hero'         => HeroBlock::class,
            'about'        => AboutBlock::class,
            'services'     => ServicesBlock::class,
            'staff'        => StaffBlock::class,
            'gallery'      => GalleryBlock::class,
            'reviews'      => ReviewsBlock::class,
            'contact_info' => ContactInfoBlock::class,
            'map'          => MapBlock::class,
            'cta'          => CtaBlock::class,
            'faq'          => FaqBlock::class,
        ];
    }

    public static function find(string $type): ?string
    {
        return static::all()[$type] ?? null;
    }

    public static function isValidVariant(string $type, string $variant): bool
    {
        $class = static::find($type);

        return $class !== null && array_key_exists($variant, $class::variants());
    }

    public static function defaultVariant(string $type): string
    {
        $class = static::find($type);

        return $class ? $class::defaultVariant() : '';
    }
}
