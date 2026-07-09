<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\Cache;

class PlanFeatureGate
{
    private const DISABLED = '__disabled__';

    public function allows(Business $business, string $feature): bool
    {
        $minPlan = Cache::remember("plan_feature_{$feature}", 60, function () use ($feature) {
            return PlanFeature::where('key', $feature)->value('min_plan') ?? self::DISABLED;
        });

        return match ($minPlan) {
            'base'         => true,
            'plus'         => $business->effectivePlan() === 'plus',
            self::DISABLED => false,
            default        => false,
        };
    }
}
