<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'block_type', 'variant', 'sort_order',
    'is_enabled', 'is_required', 'is_locked',
    'content', 'settings', 'schema_version',
])]
class BlockDefault extends Model
{
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
}
