<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\AppointmentReminder;
use Illuminate\Console\Command;

class SendReminderCommand extends Command
{
    protected $signature = 'reminder:send {appointment_id : ID dell\'appuntamento}';

    protected $description = 'Invia le mail di reminder per un appuntamento specifico';

    public function handle(): int
    {
        $appointmentId = (int) $this->argument('appointment_id');

        if ($appointmentId <= 0) {
            $this->error('ID appuntamento non valido.');
            return self::FAILURE;
        }

        $reminders = AppointmentReminder::where('appointment_id', $appointmentId)->get();

        if ($reminders->isEmpty()) {
            $this->error("Nessun reminder trovato per l'appuntamento {$appointmentId}.");
            return self::FAILURE;
        }

        foreach ($reminders as $reminder) {
            SendAppointmentReminder::dispatchSync($reminder);
            $this->line('Reminder #' . $reminder->id . ' inviato (tipo: ' . $reminder->type . ', stato precedente: ' . $reminder->status . ').');
        }

        $this->info("Inviati {$reminders->count()} reminder per l'appuntamento {$appointmentId}.");

        return self::SUCCESS;
    }
}
