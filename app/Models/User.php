<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'internal_notes', 'calendar_color', 'bio', 'receive_email_notifications'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'email_verified_at'              => 'datetime',
            'password'                       => 'hashed',
            'receive_email_notifications'    => 'boolean',
        ];
    }

    public function appointmentsAsCustomer(): HasMany
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    public function appointmentsAsStaff(): HasMany
    {
        return $this->hasMany(Appointment::class, 'staff_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile()->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->nonQueued();
    }
}
