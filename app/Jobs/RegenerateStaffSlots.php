<?php

namespace App\Jobs;

use App\Models\SystemSetting;
use App\Models\TimeSlot;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegenerateStaffSlots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $staffId,
        public readonly int $slotMinutes,
    ) {}

    public function handle(SlotGeneratorService $generator): void
    {
        TimeSlot::where('user_id', $this->staffId)
            ->whereDate('date', '>=', Carbon::today())
            ->whereNull('appointment_id')
            ->delete();

        $horizon = SystemSetting::current()->slot_generation_weeks;
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);

        for ($i = 0; $i < $horizon; $i++) {
            $generator->generateWeeklySlots(
                $this->staffId,
                $weekStart->copy()->addWeeks($i),
                $this->slotMinutes,
            );
        }

        Log::info('RegenerateStaffSlots completed', [
            'staff_id'     => $this->staffId,
            'slot_minutes' => $this->slotMinutes,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RegenerateStaffSlots failed', [
            'staff_id' => $this->staffId,
            'error'    => $e->getMessage(),
        ]);
    }
}
