<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slot_generation_weeks'])]
class SystemSetting extends Model
{
    protected function casts(): array
    {
        return ['slot_generation_weeks' => 'integer'];
    }

    public static function current(): self
    {
        $existing = self::find(1);

        if ($existing) {
            return $existing;
        }

        $setting = new self(['slot_generation_weeks' => 4]);
        $setting->id = 1;
        $setting->save();

        return $setting;
    }
}
