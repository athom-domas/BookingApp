<?php

namespace App\Livewire;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PendingCompletionNotifications extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    #[Computed]
    public function pendingAppointments(): Collection
    {
        if (! auth()->user()?->isAdmin()) {
            return collect();
        }

        $appointments = Appointment::where('status', 'confirmed')
            ->where('scheduled_date', '<', now())
            ->with(['user', 'staff'])
            ->get();

        if ($appointments->isEmpty()) {
            return collect();
        }

        $allServiceIds = $appointments
            ->flatMap(fn ($a) => $a->service_ids ?? [])
            ->unique()
            ->values()
            ->all();

        $services = Service::whereIn('id', $allServiceIds)->get()->keyBy('id');

        return $appointments->filter(function (Appointment $appointment) use ($services): bool {
            $duration = collect($appointment->service_ids ?? [])
                ->sum(fn ($id) => $services->get($id)?->duration_minutes ?? 0);

            $appointment->end_time = $appointment->scheduled_date->copy()->addMinutes($duration);

            return $appointment->end_time->isPast();
        })->values();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->pendingAppointments->count();
    }

    public function openCompleteModal(int $appointmentId): void
    {
        $this->mountAction('completeAppointment', arguments: ['appointmentId' => $appointmentId]);
    }

    public function completeAppointmentAction(): Action
    {
        return Action::make('completeAppointment')
            ->label('Registra pagamento e completa')
            ->modalHeading('Completa appuntamento')
            ->modalWidth('sm')
            ->form([
                Select::make('payment_method')
                    ->label('Metodo di pagamento')
                    ->options(['cash' => 'Contanti', 'pos' => 'POS (carta)'])
                    ->required(),
                TextInput::make('payment_amount')
                    ->label('Importo (€)')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),
            ])
            ->fillForm(fn (array $arguments): array => [
                'payment_amount' => Appointment::find($arguments['appointmentId'])?->final_price,
            ])
            ->action(function (array $data, Action $action): void {
                $appointmentId = $action->getArguments()['appointmentId'];

                try {
                    app(PaymentService::class)->recordInPersonPayment(
                        $appointmentId,
                        $data['payment_method'],
                        (float) $data['payment_amount']
                    );

                    Notification::make()
                        ->title('Appuntamento completato con successo')
                        ->success()
                        ->send();
                } catch (BookingException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    $this->halt();
                }
            });
    }

    public function render()
    {
        return view('livewire.pending-completion-notifications');
    }
}
