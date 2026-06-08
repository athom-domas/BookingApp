<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Business;
use App\Services\LoyaltyService;
use Illuminate\Console\Command;

class LoyaltyBackfillCommand extends Command
{
    protected $signature = 'loyalty:backfill {--business=all : ID business o "all"}';
    protected $description = 'Accredita i punti fedeltà per gli appuntamenti confermati/completati privi di transazione earn';

    public function handle(LoyaltyService $loyalty): int
    {
        $businessId = $this->option('business');

        $businesses = $businessId === 'all'
            ? Business::withoutGlobalScopes()->get()
            : Business::withoutGlobalScopes()->where('id', $businessId)->get();

        $total = 0;

        foreach ($businesses as $business) {
            app()->instance('current_business_id', $business->id);

            $appointments = Appointment::whereIn('status', ['confirmed', 'completed'])
                ->whereNotNull('final_price')
                ->where('final_price', '>', 0)
                ->whereNotIn('id', function ($q) {
                    $q->select('appointment_id')
                        ->from('loyalty_transactions')
                        ->where('type', 'earn')
                        ->whereNotNull('appointment_id');
                })
                ->get();

            foreach ($appointments as $appointment) {
                $loyalty->accrue($appointment, (float) $appointment->final_price);
                $total++;
            }

            $this->line("Business {$business->id} ({$business->name}): {$appointments->count()} appuntamenti elaborati");
        }

        $this->info("Backfill completato: {$total} appuntamenti elaborati.");

        return self::SUCCESS;
    }
}
