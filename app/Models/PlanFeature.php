<?php

namespace App\Models;

use Database\Factories\PlanFeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'label', 'description', 'min_plan'])]
class PlanFeature extends Model
{
    /** @use HasFactory<PlanFeatureFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn (self $feature) => Cache::forget("plan_feature_{$feature->key}"));
        static::deleted(fn (self $feature) => Cache::forget("plan_feature_{$feature->key}"));
    }
}
