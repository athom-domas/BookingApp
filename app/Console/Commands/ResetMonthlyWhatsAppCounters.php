<?php

namespace App\Console\Commands;

use App\Models\IntegrationSetting;
use Illuminate\Console\Command;

class ResetMonthlyWhatsAppCounters extends Command
{
    protected $signature   = 'whatsapp:reset-monthly-counters';
    protected $description = 'Reset monthly WhatsApp notification counters for all businesses';

    public function handle(): void
    {
        $count = IntegrationSetting::withoutGlobalScope('business')
            ->where('whatsapp_monthly_sent', '>', 0)
            ->update(['whatsapp_monthly_sent' => 0]);

        $this->info("Reset counters for {$count} businesses.");
    }
}
