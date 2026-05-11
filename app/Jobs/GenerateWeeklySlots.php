<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateWeeklySlots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SlotGeneratorService $generator): void
    {
        $nextWeek = Carbon::now()->startOfWeek()->addWeek();

        User::whereHas('availabilityRules', fn ($q) => $q->where('is_available', true))
            ->each(fn (User $staff) => $generator->generateWeeklySlots($staff->id, $nextWeek));
    }
}
