<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity;

#[Fillable([
    'log_name', 'description', 'subject_type', 'subject_id', 'event',
    'causer_type', 'causer_id', 'properties', 'attribute_changes', 'batch_uuid',
    'business_id', 'type', 'level', 'source',
    'ip_address', 'user_agent', 'url', 'method',
])]
class ActivityLog extends Activity
{
    protected $table = 'activity_log';

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
