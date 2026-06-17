<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\SalonReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['business_id', 'user_id', 'appointment_id', 'author_name', 'body', 'rating', 'is_published', 'sort_order', 'seen_at'])]
class SalonReview extends Model
{
    /** @use HasFactory<SalonReviewFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'rating'       => 'integer',
            'sort_order'   => 'integer',
            'seen_at'      => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
