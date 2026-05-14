<?php

namespace App\Jobs;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateWeeklySlots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SlotGeneratorService $generator): void
    {
        $horizon = SystemSetting::current()->slot_generation_weeks;
        $nextWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek();

        User::whereHas('availabilityRules', fn ($q) => $q->where('is_available', true))
            ->with('preferences')
            ->each(function (User $staff) use ($generator, $horizon, $nextWeek): void {
                $slotMinutes = $staff->preferences->slot_duration_minutes ?? 60;
                for ($i = 0; $i < $horizon; $i++) {
                    $generator->generateWeeklySlots(
                        $staff->id,
                        $nextWeek->copy()->addWeeks($i),
                        $slotMinutes,
                    );
                }
            });
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateWeeklySlots failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
