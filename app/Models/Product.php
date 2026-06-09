<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['business_id', 'name', 'description', 'price', 'stock', 'low_stock_threshold', 'in_sale', 'active'])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToBusiness, HasFactory, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'price'               => 'decimal:2',
            'stock'               => 'integer',
            'low_stock_threshold' => 'integer',
            'in_sale'             => 'boolean',
            'active'              => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(10);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeInSale(Builder $query): Builder
    {
        return $query->where('in_sale', true)->where('active', true);
    }

    public function scopeBelowThreshold(Builder $query): Builder
    {
        return $query->whereNotNull('low_stock_threshold')
            ->whereColumn('stock', '<=', 'low_stock_threshold');
    }

    public function isAvailable(): bool
    {
        return $this->active && $this->in_sale && $this->stock > 0;
    }

    public function isBelowThreshold(): bool
    {
        return $this->low_stock_threshold !== null && $this->stock <= $this->low_stock_threshold;
    }
}
