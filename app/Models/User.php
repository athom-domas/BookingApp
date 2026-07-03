<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'email', 'password', 'internal_notes', 'calendar_color',
    'bio', 'receive_email_notifications', 'business_id', 'must_change_password',
    'google_id', 'google_refresh_token', 'sort_order', 'avatar_path',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'receive_email_notifications' => 'boolean',
            'must_change_password'        => 'boolean',
            'business_id'                 => 'integer',
            'google_refresh_token'        => 'encrypted',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class);
    }

    public function getTenants(Panel $panel): Collection
    {
        if ($this->isAdmin()) {
            return $this->businesses;
        }
        return $this->business ? collect([$this->business]) : collect();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->hasRole('super_admin')) {
            return false;
        }
        if ($this->isAdmin()) {
            return $this->businesses()->where('businesses.id', $tenant->getKey())->exists();
        }
        return $this->business_id !== null && $this->business_id === $tenant->getKey();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superadmin') {
            return $this->hasRole('super_admin');
        }

        return $this->isAdmin() || $this->isStaff();
    }

    public function appointmentsAsCustomer(): HasMany { return $this->hasMany(Appointment::class, 'user_id'); }
    public function appointmentsAsStaff(): HasMany   { return $this->hasMany(Appointment::class, 'staff_id'); }
    public function services(): BelongsToMany        { return $this->belongsToMany(Service::class, 'service_staff'); }
    public function availabilityRules(): HasMany     { return $this->hasMany(AvailabilityRule::class); }
    public function preferences(): HasOne            { return $this->hasOne(UserPreference::class); }
    public function payments(): HasMany              { return $this->hasMany(Payment::class); }

    public function isAdmin(): bool    { return $this->hasRole('admin'); }
    public function isStaff(): bool    { return $this->hasRole('staff'); }
    public function isCustomer(): bool { return $this->hasRole('customer'); }

    public function hasPlaceholderEmail(): bool
    {
        return str_ends_with($this->email, '@noreply.local');
    }

    public function avatarUrl(): ?string
    {
        if ($this->avatar_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path);
        }
        return null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
