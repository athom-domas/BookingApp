<?php

namespace App\Console\Commands;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\SalonProfile;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class CreateBusinessCommand extends Command
{
    protected $signature = 'app:create-business
                            {--name= : Nome del business}
                            {--subdomain= : Sottodominio (es. "rossini" → rossini.tuogestionale.it)}
                            {--admin-name= : Nome dell\'admin}
                            {--admin-email= : Email dell\'admin}
                            {--admin-password= : Password dell\'admin}';

    protected $description = 'Crea un nuovo business con il suo admin';

    public function handle(): int
    {
        $name     = $this->option('name')     ?? $this->ask('Nome del business (es. "Rossini Barbershop")');
        $subdomain = $this->option('subdomain') ?? $this->ask('Sottodominio (es. "rossini" → rossini.' . config('app.base_domain', 'tuogestionale.it') . ')');
        $adminName  = $this->option('admin-name')     ?? $this->ask('Nome dell\'admin');
        $adminEmail = $this->option('admin-email')    ?? $this->ask('Email dell\'admin');
        $adminPassword = $this->option('admin-password') ?? $this->secret('Password dell\'admin');

        $subdomain = strtolower(trim($subdomain));

        if (Business::withoutGlobalScopes()->where('subdomain', $subdomain)->exists()) {
            $this->error("Esiste già un business con il sottodominio '{$subdomain}'.");
            return self::FAILURE;
        }

        if (User::where('email', $adminEmail)->exists()) {
            $this->error("Esiste già un utente con email '{$adminEmail}'.");
            return self::FAILURE;
        }

        $business = Business::create([
            'name'           => $name,
            'subdomain'      => $subdomain,
            'status'         => BusinessStatus::Active,
            'trial_ends_at'  => now()->addDays(14),
        ]);

        app()->instance('current_business_id', $business->id);

        $admin = User::create([
            'name'        => $adminName,
            'email'       => $adminEmail,
            'password'    => Hash::make($adminPassword),
            'business_id' => $business->id,
        ]);
        $admin->syncRoles(['admin']);
        $admin->businesses()->syncWithoutDetaching([$business->id]);

        SystemSetting::create([
            'business_id'                 => $business->id,
            'slot_generation_weeks'       => 4,
            'slot_granularity_minutes'    => 15,
            'timezone'                    => 'Europe/Rome',
            'booking_max_days_ahead'      => 60,
            'cancellation_deadline_hours' => 24,
            'reminder_count'              => 1,
            'reminder_1_hours'            => 24,
            'payment_mode'                => 'both',
            'reviews_enabled'             => true,
            'review_request_enabled'      => false,
            'loyalty_enabled'             => false,
            'loyalty_points_per_euro'     => 1,
            'loyalty_reward_threshold'    => 100,
            'loyalty_reward_percentage'   => 10,
            'follow_up_reminders_enabled' => false,
            'follow_up_reminder_days'     => 30,
        ]);

        SalonProfile::create([
            'business_id' => $business->id,
            'name'        => $name,
        ]);

        Artisan::call('page-builder:init', ['--business' => $business->id]);

        $this->info('');
        $this->info('✓ Business creato con successo!');
        $this->table(
            ['Campo', 'Valore'],
            [
                ['Business',    $name],
                ['Sottodominio', $subdomain . '.' . config('app.base_domain', 'tuogestionale.it')],
                ['Admin URL',   url('/admin')],
                ['Admin email', $adminEmail],
                ['Stato',       'Trial (14 giorni)'],
            ]
        );
        $this->info('');
        $this->line('Prossimi passi:');
        $this->line('  1. Accedi al pannello admin e completa il profilo salone');
        $this->line('  2. Aggiungi i servizi offerti');
        $this->line('  3. Crea gli account staff e imposta le disponibilità');

        return self::SUCCESS;
    }
}
