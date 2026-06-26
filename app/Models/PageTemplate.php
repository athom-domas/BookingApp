<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_active', 'is_default'])]
class PageTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\PageTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function pageTemplateBlocks(): HasMany
    {
        return $this->hasMany(PageTemplateBlock::class)->orderBy('sort_order');
    }
}
