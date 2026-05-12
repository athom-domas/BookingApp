<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoBookingSeeder extends Seeder
{
    public function run(): void
    {
        $staff = $this->seedStaff();
        $customer = $this->seedCustomer();
        $services = $this->seedServices();

        $this->attachServicesToStaff($services, $staff);
        $this->seedAvailabilityRules($staff);
        $this->seedPreferences($customer, $staff);
        $this->seedSlots($staff);
        $this->seedDemoAppointments($customer, $staff, $services);
    }

    /**
     * @return array<string, User>
     */
    private function seedStaff(): array
    {
        $staffUsers = [
            'giulia' => User::updateOrCreate(
                ['email' => 'giulia.staff@test.com'],
                ['name' => 'Giulia Bianchi', 'password' => Hash::make('password')]
            ),
            'marco' => User::updateOrCreate(
                ['email' => 'marco.staff@test.com'],
                ['name' => 'Marco Verdi', 'password' => Hash::make('password')]
            ),
        ];

        foreach ($staffUsers as $user) {
            $user->syncRoles(['staff']);
        }

        $defaultStaff = User::where('email', 'staff@test.com')->first();

        if ($defaultStaff) {
            $defaultStaff->update(['name' => 'Staff Demo']);
            $defaultStaff->syncRoles(['staff']);
            $staffUsers['demo'] = $defaultStaff;
        }

        return $staffUsers;
    }

    private function seedCustomer(): User
    {
        $customer = User::updateOrCreate(
            ['email' => 'customer@test.com'],
            ['name' => 'Cliente Demo', 'password' => Hash::make('password')]
        );

        $customer->syncRoles(['customer']);

        return $customer;
    }

    /**
     * @return array<string, Service>
     */
    private function seedServices(): array
    {
        $services = [
            'consultation' => [
                'name' => 'Consulenza iniziale',
                'description' => 'Primo incontro per valutare esigenze, obiettivi e disponibilita.',
                'duration_minutes' => 60,
                'price' => 75.00,
                'active' => true,
            ],
            'follow_up' => [
                'name' => 'Controllo periodico',
                'description' => 'Appuntamento di follow-up per clienti gia registrati.',
                'duration_minutes' => 30,
                'price' => 45.00,
                'active' => true,
            ],
            'planning' => [
                'name' => 'Pianificazione avanzata',
                'description' => 'Sessione operativa per definire piano, priorita e prossime attivita.',
                'duration_minutes' => 60,
                'price' => 95.00,
                'active' => true,
            ],
        ];

        return collect($services)
            ->mapWithKeys(fn (array $attributes, string $key): array => [
                $key => Service::updateOrCreate(
                    ['name' => $attributes['name']],
                    $attributes
                ),
            ])
            ->all();
    }

    /**
     * @param  array<string, Service>  $services
     * @param  array<string, User>  $staff
     */
    private function attachServicesToStaff(array $services, array $staff): void
    {
        $services['consultation']->staff()->syncWithoutDetaching([
            $staff['giulia']->id,
            $staff['marco']->id,
        ]);

        $services['follow_up']->staff()->syncWithoutDetaching([
            $staff['giulia']->id,
            $staff['demo']->id ?? $staff['giulia']->id,
        ]);

        $services['planning']->staff()->syncWithoutDetaching([
            $staff['marco']->id,
            $staff['demo']->id ?? $staff['marco']->id,
        ]);
    }

    /**
     * @param  array<string, User>  $staff
     */
    private function seedAvailabilityRules(array $staff): void
    {
        $rules = [
            1 => ['09:00:00', '17:00:00'],
            2 => ['09:00:00', '17:00:00'],
            3 => ['10:00:00', '18:00:00'],
            4 => ['09:00:00', '17:00:00'],
            5 => ['09:00:00', '15:00:00'],
        ];

        foreach ($staff as $user) {
            foreach ($rules as $dayOfWeek => [$start, $end]) {
                AvailabilityRule::updateOrCreate(
                    ['user_id' => $user->id, 'day_of_week' => $dayOfWeek],
                    ['start_time' => $start, 'end_time' => $end, 'is_available' => true]
                );
            }
        }
    }

    /**
     * @param  array<string, User>  $staff
     */
    private function seedPreferences(User $customer, array $staff): void
    {
        UserPreference::updateOrCreate(
            ['user_id' => $customer->id],
            [
                'receive_email_reminders' => true,
                'receive_sms_reminders' => false,
                'phone_number' => '+39123456789',
                'timezone' => config('app.timezone', 'Europe/Rome'),
                'preferred_staff' => $staff['giulia']->id,
            ]
        );

        foreach ($staff as $user) {
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'receive_email_reminders' => true,
                    'receive_sms_reminders' => false,
                    'phone_number' => null,
                    'timezone' => config('app.timezone', 'Europe/Rome'),
                    'preferred_staff' => null,
                ]
            );
        }
    }

    /**
     * @param  array<string, User>  $staff
     */
    private function seedSlots(array $staff): void
    {
        $generator = app(SlotGeneratorService::class);
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

        foreach ($staff as $user) {
            for ($weekOffset = 0; $weekOffset < 6; $weekOffset++) {
                $generator->generateWeeklySlots(
                    staffId: $user->id,
                    weekStart: $weekStart->copy()->addWeeks($weekOffset),
                    slotMinutes: 60,
                );
            }
        }
    }

    /**
     * @param  array<string, User>  $staff
     * @param  array<string, Service>  $services
     */
    private function seedDemoAppointments(User $customer, array $staff, array $services): void
    {
        $confirmedDate = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(10, 0);
        $completedDate = Carbon::now()->subWeek()->next(Carbon::THURSDAY)->setTime(11, 0);

        $confirmed = Appointment::updateOrCreate(
            [
                'user_id' => $customer->id,
                'service_id' => $services['consultation']->id,
                'staff_id' => $staff['giulia']->id,
                'scheduled_date' => $confirmedDate,
            ],
            [
                'status' => 'confirmed',
                'final_price' => $services['consultation']->price,
                'notes' => 'Prenotazione demo confermata.',
            ]
        );

        $completed = Appointment::updateOrCreate(
            [
                'user_id' => $customer->id,
                'service_id' => $services['follow_up']->id,
                'staff_id' => $staff['giulia']->id,
                'scheduled_date' => $completedDate,
            ],
            [
                'status' => 'completed',
                'final_price' => $services['follow_up']->price,
                'notes' => 'Appuntamento demo completato.',
            ]
        );

        $this->markSlotAsBooked($confirmed);
        $this->seedPayment($confirmed, 'completed');
        $this->seedPayment($completed, 'completed');
    }

    private function markSlotAsBooked(Appointment $appointment): void
    {
        TimeSlot::updateOrCreate(
            [
                'user_id' => $appointment->staff_id,
                'date' => $appointment->scheduled_date->toDateString(),
                'start_time' => $appointment->scheduled_date->format('H:i:s'),
            ],
            [
                'end_time' => $appointment->scheduled_date->copy()->addHour()->format('H:i:s'),
                'is_available' => false,
                'appointment_id' => $appointment->id,
            ]
        );
    }

    private function seedPayment(Appointment $appointment, string $status): void
    {
        $transactionId = 'pi_demo_'.$appointment->id;

        Payment::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'user_id' => $appointment->user_id,
                'amount' => $appointment->final_price,
                'status' => $status,
                'stripe_transaction_id' => $transactionId,
                'stripe_response' => [
                    'id' => $transactionId,
                    'object' => 'payment_intent',
                    'status' => 'succeeded',
                    'livemode' => false,
                ],
            ]
        );
    }
}
