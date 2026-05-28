<?php

namespace Tests\Concerns;

use App\Models\Business;

trait WithBusinessContext
{
    protected function setBusinessContext(Business $business): void
    {
        app()->instance('current_business_id', $business->id);
    }
}
