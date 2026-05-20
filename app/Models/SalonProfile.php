<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'logo_path', 'primary_color', 'phone', 'address', 'website'])]
class SalonProfile extends Model
{
    public static function current(): self
    {
        $existing = self::find(1);

        if ($existing) {
            return $existing;
        }

        $profile = new self([
            'name'          => 'Il mio salone',
            'logo_path'     => null,
            'primary_color' => '#1d4ed8',
            'phone'         => null,
            'address'       => null,
            'website'       => null,
        ]);
        $profile->id = 1;
        $profile->save();

        return $profile;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }
}
