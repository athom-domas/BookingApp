<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'user_id', 'start_date', 'end_date', 'reason'])]
class StaffBlockout extends Model
{
    /** @use HasFactory<\Database\Factories\StaffBlockoutFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
