<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['business_id', 'name', 'description', 'duration_minutes', 'price', 'active', 'featured', 'sort_order', 'image_path', 'service_category_id'])]
class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use BelongsToBusiness, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'price', 'active', 'duration_minutes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn(string $event) => "servizio {$event}");
    }

    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->business_id = $this->business_id;
        $activity->type        = 'activity';
        $activity->level       = 'info';
        $activity->source      = 'model_event';
    }

    protected function casts(): array
    {
        return [
            'active'   => 'boolean',
            'featured' => 'boolean',
        ];
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }
        return null;
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_staff');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
