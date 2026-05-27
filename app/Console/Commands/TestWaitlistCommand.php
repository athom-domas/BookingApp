<?php

namespace App\Console\Commands;

use App\Jobs\NotifyWaitlistCandidateJob;
use App\Listeners\MatchWaitlistOnCancellation;
use App\Events\AppointmentCancelled;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

class TestWaitlistCommand extends Command
{
    protected $signature = 'waitlist:test
                            {--date= : Data slot (YYYY-MM-DD, default domani)}
                            {--cleanup : Elimina i dati di test creati in precedenza}';

    protected $description = 'Simula il flusso lista d\'attesa: crea appuntamento + entry, cancella, invia offerta';

    private const EMAIL_A = 'waitlist-test-cancella@example.com';
    private const EMAIL_B = 'waitlist-test-attesa@example.com';

    public function handle(): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::tomorrow();

        if ($targetDate->isPast()) {
            $this->error('La data deve essere futura.');
            return self::FAILURE;
        }

        // Find a staff+service+rule combination that will actually work when booking
        [$staff, $service, $rule] = $this->findValidCombination($targetDate);

        if (! $staff) {
            $this->error('Nessuna combinazione staff+servizio+orario valida trovata per ' . $targetDate->format('d/m/Y') . ' (' . $targetDate->locale('it')->dayName . ').');
            $this->line('Verifica nell\'admin che:');
            $this->line('  - esista almeno un servizio attivo');
            $this->line('  - esista uno staff con quel servizio assegnato');
            $this->line('  - lo staff abbia una regola di disponibilità per quel giorno');
            return self::FAILURE;
        }

        // Pick a slot time within the rule's window
        $slotTime      = Carbon::parse($rule->start_time)->addMinutes(30)->format('H:i');
        $scheduledDate = $targetDate->copy()->setTimeFromTimeString($slotTime);

        $this->newLine();
        $this->line('<fg=cyan>════════════════════════════════</>');
        $this->line('<fg=cyan>      WAITLIST TEST              </>');
        $this->line('<fg=cyan>════════════════════════════════</>');
        $this->newLine();
        $this->line("  Servizio : <info>{$service->name}</info>");
        $this->line("  Staff    : <info>{$staff->name}</info>");
        $this->line("  Slot     : <info>{$scheduledDate->format('d/m/Y H:i')}</info>");
        $this->newLine();

        // STEP 1 — Create appointment for Person A (will be cancelled)
        $personA = User::firstOrCreate(
            ['email' => self::EMAIL_A],
            ['name' => 'Test Cliente A (cancella)', 'password' => bcrypt('password')]
        );
        $personA->syncRoles(['customer']);

        $appointment = Appointment::create([
            'user_id'        => $personA->id,
            'staff_id'       => $staff->id,
            'service_ids'    => [$service->id],
            'scheduled_date' => $scheduledDate,
            'status'         => 'confirmed',
            'final_price'    => $service->price,
        ]);

        $this->line("  [1] Appuntamento <info>#{$appointment->id}</info> creato per {$personA->name}");

        // STEP 2 — Create waitlist entry for Person B (will receive the offer)
        $personB = User::firstOrCreate(
            ['email' => self::EMAIL_B],
            ['name' => 'Test Cliente B (attesa)', 'password' => bcrypt('password')]
        );
        $personB->syncRoles(['customer']);

        $entry = WaitlistEntry::create([
            'user_id'             => $personB->id,
            'service_ids'         => [$service->id],
            'preferred_staff_id'  => null,
            'preferred_days'      => [$targetDate->toDateString()],
            'preferred_time_from' => '00:00',
            'preferred_time_to'   => '23:59',
            'status'              => 'waiting',
        ]);

        $this->line("  [2] Entry lista d'attesa <info>#{$entry->id}</info> creata per {$personB->name}");

        // STEP 3 — Cancel the appointment
        $appointment->update(['status' => 'cancelled']);
        $this->line("  [3] Appuntamento cancellato");

        // STEP 4 — Match (same logic as MatchWaitlistOnCancellation listener)
        $slotInfo = [
            'date'        => $scheduledDate->toDateString(),
            'time'        => $scheduledDate->format('H:i'),
            'staff_id'    => $staff->id,
            'service_ids' => [$service->id],
        ];

        $candidates = MatchWaitlistOnCancellation::findCandidates($slotInfo);

        if ($candidates->isEmpty()) {
            $this->error("Matching fallito: nessuna entry trovata. Controlla i log.");
            return self::FAILURE;
        }

        $this->line("  [4] Candidati trovati: <info>{$candidates->count()}</info>");

        // STEP 5 — Run the notification job synchronously for each candidate
        $this->line("  [5] Invio notifiche...");
        foreach ($candidates as $candidate) {
            NotifyWaitlistCandidateJob::dispatchSync($candidate, $slotInfo);
            $this->line("      → notificato: {$candidate->user->name}");
        }

        $candidate = $candidates->first();
        $candidate->refresh();

        // STEP 6 — Output results
        $offerUrl = URL::temporarySignedRoute(
            'waitlist.offer.accept',
            now()->addDays(7),
            ['entry' => $candidate->id],
        );

        $this->newLine();
        $this->line('<fg=cyan>════════════════════════════════</>');
        $this->newLine();
        $this->line("  Status entry : <info>{$candidate->status}</info>");
        $this->newLine();
        $this->line("  <fg=yellow>Email inviata a:</> {$personB->email}");
        $this->line("  <fg=yellow>Mailpit:</>         http://localhost:8025");
        $this->newLine();
        $this->line("  <fg=yellow>URL offerta (apri nel browser):</>");
        $this->line("  {$offerUrl}");
        $this->newLine();
        $this->line("  Credenziali cliente B:");
        $this->line("    email:    <info>{$personB->email}</info>");
        $this->line("    password: <info>password</info>");
        $this->newLine();
        $this->line("  Pulisci i dati di test:");
        $this->line("  <fg=gray>docker-compose run --rm app php artisan waitlist:test --cleanup</>");
        $this->newLine();

        return self::SUCCESS;
    }

    private function findValidCombination(Carbon $date): array
    {
        $dayOfWeek = (int) $date->dayOfWeek;

        $staffList = User::role('staff')
            ->whereHas('services', fn ($q) => $q->where('services.active', true))
            ->with(['services' => fn ($q) => $q->where('services.active', true)])
            ->get();

        foreach ($staffList as $staff) {
            $rule = AvailabilityRule::where('user_id', $staff->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_available', true)
                ->first();

            if (! $rule) {
                continue;
            }

            $service = $staff->services->first();

            if ($service) {
                return [$staff, $service, $rule];
            }
        }

        return [null, null, null];
    }

    private function cleanup(): int
    {
        $users = User::whereIn('email', [self::EMAIL_A, self::EMAIL_B])->get();

        foreach ($users as $user) {
            Appointment::where('user_id', $user->id)->delete();
            WaitlistEntry::where('user_id', $user->id)->delete();
            $user->delete();
        }

        $this->info('Dati di test eliminati.');
        return self::SUCCESS;
    }
}
