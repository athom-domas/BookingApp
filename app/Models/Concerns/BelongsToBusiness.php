<?php

namespace App\Models\Concerns;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query) {
            if (app()->bound('current_business_id')) {
                $query->where(
                    (new static)->getTable() . '.business_id',
                    app('current_business_id')
                );
            }
        });

        static::creating(function (Model $model) {
            if (app()->bound('current_business_id') && empty($model->business_id)) {
                $model->business_id = app('current_business_id');
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
