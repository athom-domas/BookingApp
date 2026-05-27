<?php

namespace App\Models;

use App\Models\Business;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'business_id',
    'name', 'tagline', 'logo_path', 'primary_color',
    'phone', 'address', 'website',
    'description', 'cancellation_policy', 'google_maps_embed',
    'opening_hours',
    'instagram_url', 'facebook_url', 'tiktok_url', 'whatsapp_number',
])]
class SalonProfile extends Model implements HasMedia
{
    use BelongsToBusiness, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::firstOrCreate(
            ['business_id' => Business::currentId()],
            [
                'name'          => 'Il mio salone',
                'primary_color' => '#1d1d1d',
            ]
        );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile()->useDisk('public');
        $this->addMediaCollection('cover')->singleFile()->useDisk('public');
        $this->addMediaCollection('gallery')->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->nonQueued();

        $this->addMediaConversion('web')
            ->width(1200)
            ->height(800)
            ->nonQueued();
    }

    public function logoUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('logo');
        if ($url) {
            return $url;
        }

        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    public function coverUrl(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }
}
