<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentHold;
use App\Models\AvailabilityRule;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoBookingSeeder extends Seeder
{
    public function run(): void
    {
        $staff    = $this->seedStaff();
        $customer = $this->seedCustomer();
        $services = $this->seedServices();

        $this->attachServicesToStaff($services, $staff);
        $this->seedAvailabilityRules($staff);
        $this->seedPreferences($customer, $staff);
        $this->seedDemoAppointments($customer, $staff, $services);
        $this->seedDemoHolds($staff, $customer, $services);
    }

    /** @return array<string, User> */
    private function seedStaff(): array
    {
        $staffData = [
            'giulia' => ['email' => 'giulia.staff@test.com', 'name' => 'Giulia Bianchi'],
            'marco'  => ['email' => 'marco.staff@test.com',  'name' => 'Marco Verdi'],
        ];

        $users = [];
        foreach ($staffData as $key => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make('password')]
            );
            $user->syncRoles(['staff']);
            $users[$key] = $user;
        }

        // Default staff da RolesAndUsersSeeder
        $default = User::where('email', 'staff@test.com')->first();
        if ($default) {
            $default->syncRoles(['staff']);
            $users['demo'] = $default;
        }

        return $users;
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

    /** @return array<string, Service> */
    private function seedServices(): array
    {
        $definitions = [
            'consultation' => [
                'name'             => 'Consulenza iniziale',
                'description'      => 'Primo incontro per valutare esigenze e obiettivi.',
                'duration_minutes' => 60,
                'price'            => 75.00,
                'active'           => true,
            ],
            'follow_up' => [
                'name'             => 'Controllo periodico',
                'description'      => 'Follow-up per clienti già registrati.',
                'duration_minutes' => 30,
                'price'            => 45.00,
                'active'           => true,
            ],
            'planning' => [
                'name'             => 'Pianificazione avanzata',
                'description'      => 'Sessione per definire piano e prossime attività.',
                'duration_minutes' => 90,
                'price'            => 110.00,
                'active'           => true,
            ],
        ];

        return collect($definitions)
            ->mapWithKeys(fn (array $attrs, string $key) => [
                $key => Service::updateOrCreate(['name' => $attrs['name']], $attrs),
            ])
            ->all();
    }

    /**
     * @param  array<string, Service>  $services
     * @param  array<string, User>     $staff
     */
    private function attachServicesToStaff(array $services, array $staff): void
    {
        $services['consultation']->staff()->syncWithoutDetaching([$staff['giulia']->id]);
        $services['follow_up']->staff()->syncWithoutDetaching([$staff['giulia']->id]);

        $services['consultation']->staff()->syncWithoutDetaching([$staff['marco']->id]);
        $services['planning']->staff()->syncWithoutDetaching([$staff['marco']->id]);

        if (isset($staff['demo'])) {
            foreach ($services as $service) {
                $service->staff()->syncWithoutDetaching([$staff['demo']->id]);
            }
        }
    }

    /** @param array<string, User> $staff */
    private function seedAvailabilityRules(array $staff): void
    {
        // Carbon day_of_week: 0=Dom, 1=Lun, 2=Mar, 3=Mer, 4=Gio, 5=Ven, 6=Sab
        $schedule = [
            1 => ['09:00:00', '17:00:00'], // Lunedì
            2 => ['09:00:00', '17:00:00'], // Martedì
            3 => ['10:00:00', '18:00:00'], // Mercoledì
            4 => ['09:00:00', '17:00:00'], // Giovedì
            5 => ['09:00:00', '15:00:00'], // Venerdì
        ];

        foreach ($staff as $user) {
            foreach ($schedule as $dayOfWeek => [$start, $end]) {
                AvailabilityRule::updateOrCreate(
                    ['user_id' => $user->id, 'day_of_week' => $dayOfWeek],
                    ['start_time' => $start, 'end_time' => $end, 'is_available' => true]
                );
            }
        }
    }

    /** @param array<string, User> $staff */
    private function seedPreferences(User $customer, array $staff): void
    {
        UserPreference::updateOrCreate(
            ['user_id' => $customer->id],
            [
                'receive_email_reminders' => true,
                'receive_sms_reminders'   => false,
                'phone_number'            => '+39123456789',
                'timezone'                => 'Europe/Rome',
                'preferred_staff'         => $staff['giulia']->id,
            ]
        );

        foreach ($staff as $user) {
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'receive_email_reminders' => true,
                    'receive_sms_reminders'   => false,
                    'phone_number'            => null,
                    'timezone'                => 'Europe/Rome',
                    'preferred_staff'         => null,
                ]
            );
        }
    }

    /**
     * @param  array<string, User>     $staff
     * @param  array<string, Service>  $services
     */
    private function seedDemoAppointments(User $customer, array $staff, array $services): void
    {
        // Futuro confermato — prossimo mercoledì 10:00
        $confirmed = Appointment::updateOrCreate(
            [
                'user_id'        => $customer->id,
                'service_id'     => $services['consultation']->id,
                'staff_id'       => $staff['giulia']->id,
                'scheduled_date' => Carbon::now()->next(Carbon::WEDNESDAY)->setTime(10, 0),
            ],
            [
                'status'      => 'confirmed',
                'final_price' => $services['consultation']->price,
                'notes'       => 'Prenotazione demo — slot dinamico.',
            ]
        );

        // Futuro pending — prossimo giovedì 14:00
        Appointment::updateOrCreate(
            [
                'user_id'        => $customer->id,
                'service_id'     => $services['follow_up']->id,
                'staff_id'       => $staff['marco']->id,
                'scheduled_date' => Carbon::now()->next(Carbon::THURSDAY)->setTime(14, 0),
            ],
            [
                'status'      => 'pending',
                'final_price' => $services['follow_up']->price,
                'notes'       => 'In attesa di conferma.',
            ]
        );

        // Passato completato — martedì scorso
        $completed = Appointment::updateOrCreate(
            [
                'user_id'        => $customer->id,
                'service_id'     => $services['follow_up']->id,
                'staff_id'       => $staff['giulia']->id,
                'scheduled_date' => Carbon::now()->subWeek()->next(Carbon::TUESDAY)->setTime(11, 0),
            ],
            [
                'status'      => 'completed',
                'final_price' => $services['follow_up']->price,
                'notes'       => 'Completato.',
            ]
        );

        // Passato cancellato — 3 giorni fa
        Appointment::updateOrCreate(
            [
                'user_id'        => $customer->id,
                'service_id'     => $services['planning']->id,
                'staff_id'       => $staff['marco']->id,
                'scheduled_date' => Carbon::now()->subDays(3)->setTime(9, 0),
            ],
            [
                'status'      => 'cancelled',
                'final_price' => null,
                'notes'       => 'Cancellato dal cliente.',
            ]
        );

        $this->seedPayment($confirmed, 'completed');
        $this->seedPayment($completed, 'completed');
    }

    /**
     * Seed di AppointmentHold per dimostrare il sistema hold.
     * - active: cliente sta compilando il form (slot bloccato)
     * - expired: non confermato in tempo (slot ora libero)
     * - converted: ha generato un appuntamento confermato
     *
     * @param  array<string, User>    $staff
     * @param  array<string, Service> $services
     */
    private function seedDemoHolds(array $staff, User $customer, array $services): void
    {
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        $nextWed    = Carbon::now()->next(Carbon::WEDNESDAY);

        // ACTIVE — slot 11:00-12:00 lunedì prossimo
        AppointmentHold::updateOrCreate(
            [
                'staff_id'   => $staff['giulia']->id,
                'session_id' => 'demo-session-active',
                'starts_at'  => $nextMonday->copy()->setTime(11, 0),
            ],
            [
                'customer_id' => $customer->id,
                'ends_at'     => $nextMonday->copy()->setTime(12, 0),
                'service_ids' => [$services['consultation']->id],
                'status'      => 'active',
                'expires_at'  => now()->addMinutes(8),
            ]
        );

        // EXPIRED — slot 14:00-14:30 lunedì prossimo (non confermato)
        AppointmentHold::updateOrCreate(
            [
                'staff_id'   => $staff['marco']->id,
                'session_id' => 'demo-session-expired',
                'starts_at'  => $nextMonday->copy()->setTime(14, 0),
            ],
            [
                'customer_id' => $customer->id,
                'ends_at'     => $nextMonday->copy()->setTime(14, 30),
                'service_ids' => [$services['follow_up']->id],
                'status'      => 'expired',
                'expires_at'  => now()->subMinutes(10),
            ]
        );

        // CONVERTED — slot 10:00-11:00 mercoledì prossimo (ha generato l'appuntamento confermato)
        AppointmentHold::updateOrCreate(
            [
                'staff_id'   => $staff['giulia']->id,
                'session_id' => 'demo-session-converted',
                'starts_at'  => $nextWed->copy()->setTime(10, 0),
            ],
            [
                'customer_id' => $customer->id,
                'ends_at'     => $nextWed->copy()->setTime(11, 0),
                'service_ids' => [$services['consultation']->id],
                'status'      => 'converted',
                'expires_at'  => now()->subMinutes(5),
            ]
        );
    }

    private function seedPayment(Appointment $appointment, string $status): void
    {
        Payment::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'user_id'               => $appointment->user_id,
                'amount'                => $appointment->final_price,
                'status'                => $status,
                'stripe_transaction_id' => 'pi_demo_' . $appointment->id,
                'stripe_response'       => [
                    'id'      => 'pi_demo_' . $appointment->id,
                    'object'  => 'payment_intent',
                    'status'  => 'succeeded',
                    'livemode' => false,
                ],
            ]
        );
    }
}
