<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'page_template_id', 'page_template_block_id',
    'block_type', 'variant', 'sort_order',
    'is_enabled', 'is_required', 'is_locked', 'content', 'settings', 'schema_version',
])]
class BusinessPageBlock extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessPageBlockFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return [
            'content'     => 'array',
            'settings'    => 'array',
            'is_enabled'  => 'boolean',
            'is_required' => 'boolean',
            'is_locked'   => 'boolean',
        ];
    }

    public function getContentAttribute(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) { $decoded = json_decode($value, true); return is_array($decoded) ? $decoded : []; }
        return [];
    }

    public function getSettingsAttribute(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) { $decoded = json_decode($value, true); return is_array($decoded) ? $decoded : []; }
        return [];
    }

    public function pageTemplate(): BelongsTo
    {
        return $this->belongsTo(PageTemplate::class);
    }

    public function pageTemplateBlock(): BelongsTo
    {
        return $this->belongsTo(PageTemplateBlock::class);
    }
}
