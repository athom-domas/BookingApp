<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'page_template_id', 'block_type', 'variant', 'sort_order',
    'is_enabled', 'is_required', 'is_locked', 'content', 'settings', 'schema_version',
])]
class PageTemplateBlock extends Model
{
    /** @use HasFactory<\Database\Factories\PageTemplateBlockFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'content'    => 'array',
            'settings'   => 'array',
            'is_enabled' => 'boolean',
            'is_required'=> 'boolean',
            'is_locked'  => 'boolean',
        ];
    }

    public function pageTemplate(): BelongsTo
    {
        return $this->belongsTo(PageTemplate::class);
    }
}
