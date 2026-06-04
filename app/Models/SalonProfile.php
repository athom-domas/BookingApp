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
    'name', 'tagline', 'logo_path', 'theme',
    'font_pair', 'border_style', 'bg_texture',
    'phone', 'address',
    'description', 'cancellation_policy', 'google_maps_embed',
    'opening_hours',
    'instagram_url', 'facebook_url', 'tiktok_url', 'whatsapp_number',
    'email_greeting', 'email_footer_note', 'email_accent_color',
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
        if (! app()->bound('current_business_id')) {
            return new self(['name' => config('app.name', 'Booking App')]);
        }

        return self::firstOrCreate(
            ['business_id' => Business::currentId()],
            [
                'name' => 'Il mio salone',
            ]
        );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile()->useDisk('public');
        $this->addMediaCollection('cover')->singleFile()->useDisk('public');
        $this->addMediaCollection('favicon')->singleFile()->useDisk('public');
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

    public function faviconUrl(): ?string
    {
        return $this->getFirstMediaUrl('favicon') ?: null;
    }
}
