<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'subdomain', 'status'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => BusinessStatus::class];
    }

    public static function currentId(): int
    {
        if (! app()->bound('current_business_id')) {
            throw new \RuntimeException('No current business context bound.');
        }

        return (int) app('current_business_id');
    }

    public function users(): HasMany        { return $this->hasMany(User::class); }
    public function services(): HasMany     { return $this->hasMany(Service::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    public function systemSetting(): HasOne { return $this->hasOne(SystemSetting::class); }
    public function salonProfile(): HasOne  { return $this->hasOne(SalonProfile::class); }
    public function integrationSetting(): HasOne { return $this->hasOne(IntegrationSetting::class); }
}
