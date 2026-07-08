<?php

namespace App\Services;

use App\Models\Business;

class PlanFeatureGate
{
    public function allows(Business $business, string $feature): bool
    {
        $requiredPlans = config("plans.features.{$feature}");

        if ($requiredPlans === null) {
            return false;
        }

        return in_array($business->effectivePlan(), $requiredPlans, true);
    }
}
