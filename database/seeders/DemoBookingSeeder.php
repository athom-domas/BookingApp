<?php

namespace Database\Seeders;

use App\Models\Appointment;
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
        $staff     = $this->seedStaff();
        $customers = $this->seedCustomers();
        $services  = $this->seedServices();

        $this->attachServicesToStaff($services, $staff);
        $this->seedAvailabilityRules($staff);
        $this->seedPreferences($customers, $staff);
        $this->seedAppointments($customers, $staff, $services);
    }

    /** @return array<string, User> */
    private function seedStaff(): array
    {
        $staffData = [
            'marco'   => ['email' => 'marco@barbershop.test',   'name' => 'Marco Russo'],
            'andrea'  => ['email' => 'andrea@barbershop.test',  'name' => 'Andrea Conti'],
            'filippo' => ['email' => 'filippo@barbershop.test', 'name' => 'Filippo Mancini'],
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

        return $users;
    }

    /** @return array<string, User> */
    private function seedCustomers(): array
    {
        $customerData = [
            'giovanni'   => ['email' => 'giovanni@customer.test',   'name' => 'Giovanni Esposito'],
            'alessandro' => ['email' => 'alessandro@customer.test', 'name' => 'Alessandro Romano'],
            'matteo'     => ['email' => 'matteo@customer.test',     'name' => 'Matteo Ricci'],
            'davide'     => ['email' => 'davide@customer.test',     'name' => 'Davide Gallo'],
            'simone'     => ['email' => 'simone@customer.test',     'name' => 'Simone Marino'],
        ];

        $users = [];
        foreach ($customerData as $key => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make('password')]
            );
            $user->syncRoles(['customer']);
            $users[$key] = $user;
        }

        return $users;
    }

    /** @return array<string, Service> */
    private function seedServices(): array
    {
        $definitions = [
            'taglio' => [
                'name'             => 'Taglio capelli',
                'description'      => 'Taglio classico o moderno con finishing.',
                'duration_minutes' => 30,
                'price'            => 18.00,
                'active'           => true,
            ],
            'taglio_barba' => [
                'name'             => 'Taglio + Barba',
                'description'      => 'Taglio capelli con cura e rifinitura della barba.',
                'duration_minutes' => 50,
                'price'            => 28.00,
                'active'           => true,
            ],
            'rasatura' => [
                'name'             => 'Rasatura tradizionale',
                'description'      => 'Rasatura con rasoio a mano libera e asciugamano caldo.',
                'duration_minutes' => 30,
                'price'            => 15.00,
                'active'           => true,
            ],
            'barba' => [
                'name'             => 'Rimodellamento barba',
                'description'      => 'Modellatura, rifinitura e trattamento della barba.',
                'duration_minutes' => 20,
                'price'            => 12.00,
                'active'           => true,
            ],
            'colore' => [
                'name'             => 'Colore / Tinta',
                'description'      => 'Colorazione capelli con prodotti professionali.',
                'duration_minutes' => 60,
                'price'            => 40.00,
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
        foreach (['taglio', 'taglio_barba', 'barba'] as $key) {
            $services[$key]->staff()->syncWithoutDetaching([$staff['marco']->id]);
        }

        foreach (['taglio', 'taglio_barba', 'rasatura', 'barba'] as $key) {
            $services[$key]->staff()->syncWithoutDetaching([$staff['andrea']->id]);
        }

        foreach ($services as $service) {
            $service->staff()->syncWithoutDetaching([$staff['filippo']->id]);
        }
    }

    /** @param array<string, User> $staff */
    private function seedAvailabilityRules(array $staff): void
    {
        // Barbershop: martedì–sabato, mattina 08:00–13:00 e pomeriggio 16:00–21:00
        $days = [2, 3, 4, 5, 6];
        $slots = [
            ['08:00:00', '13:00:00'],
            ['16:00:00', '21:00:00'],
        ];

        foreach ($staff as $user) {
            foreach ($days as $dayOfWeek) {
                foreach ($slots as [$start, $end]) {
                    AvailabilityRule::updateOrCreate(
                        ['user_id' => $user->id, 'day_of_week' => $dayOfWeek, 'start_time' => $start],
                        ['end_time' => $end, 'is_available' => true]
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, User> $customers
     * @param  array<string, User> $staff
     */
    private function seedPreferences(array $customers, array $staff): void
    {
        $phones = [
            'giovanni'   => '+39 333 1234567',
            'alessandro' => '+39 347 2345678',
            'matteo'     => '+39 320 3456789',
            'davide'     => '+39 393 4567890',
            'simone'     => '+39 366 5678901',
        ];

        foreach ($phones as $key => $phone) {
            UserPreference::updateOrCreate(
                ['user_id' => $customers[$key]->id],
                [
                    'notification_channel' => 'email',
                    'phone_number'         => $phone,
                ]
            );
        }

        foreach ($staff as $user) {
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'notification_channel' => 'email',
                    'phone_number'         => null,
                ]
            );
        }
    }

    /**
     * @param  array<string, User>    $customers
     * @param  array<string, User>    $staff
     * @param  array<string, Service> $services
     */
    private function seedAppointments(array $customers, array $staff, array $services): void
    {
        // Passato completato
        $past = [
            $this->upsertAppointment($customers['giovanni'],   $staff['marco'],   $services['taglio'],       Carbon::now()->subWeeks(2)->next(Carbon::TUESDAY)->setTime(10, 0),   'completed'),
            $this->upsertAppointment($customers['alessandro'], $staff['andrea'],  $services['taglio_barba'], Carbon::now()->subDays(10)->setTime(11, 30),                          'completed'),
            $this->upsertAppointment($customers['matteo'],     $staff['andrea'],  $services['rasatura'],     Carbon::now()->subWeek()->next(Carbon::THURSDAY)->setTime(15, 0),    'completed'),
            $this->upsertAppointment($customers['davide'],     $staff['filippo'], $services['colore'],       Carbon::now()->subWeeks(3)->next(Carbon::WEDNESDAY)->setTime(14, 0), 'completed'),
            $this->upsertAppointment($customers['simone'],     $staff['filippo'], $services['barba'],        Carbon::now()->subDays(5)->setTime(16, 0),                           'completed'),
            $this->upsertAppointment($customers['giovanni'],   $staff['marco'],   $services['taglio_barba'], Carbon::now()->subWeeks(5)->next(Carbon::SATURDAY)->setTime(9, 30),  'completed'),
        ];

        // Cancellato
        $this->upsertAppointment($customers['matteo'], $staff['marco'], $services['taglio'], Carbon::now()->subDays(4)->setTime(9, 0), 'cancelled');

        // Futuro confermato
        $this->upsertAppointment($customers['giovanni'],   $staff['marco'],   $services['taglio'],   Carbon::now()->next(Carbon::TUESDAY)->setTime(10, 0),    'confirmed');
        $this->upsertAppointment($customers['alessandro'], $staff['andrea'],  $services['barba'],    Carbon::now()->next(Carbon::WEDNESDAY)->setTime(11, 30), 'confirmed');
        $this->upsertAppointment($customers['davide'],     $staff['filippo'], $services['taglio'],   Carbon::now()->next(Carbon::THURSDAY)->setTime(14, 0),   'confirmed');

        // Futuro pending
        $this->upsertAppointment($customers['matteo'], $staff['marco'],   $services['taglio_barba'], Carbon::now()->next(Carbon::FRIDAY)->setTime(16, 0),    'pending');
        $this->upsertAppointment($customers['simone'], $staff['filippo'], $services['colore'],       Carbon::now()->next(Carbon::SATURDAY)->setTime(10, 30), 'pending');

        foreach ($past as $a) {
            $this->seedPayment($a, 'completed');
        }

    }

    private function upsertAppointment(
        User $customer,
        User $staff,
        Service $service,
        Carbon $date,
        string $status,
    ): Appointment {
        return Appointment::updateOrCreate(
            [
                'user_id'        => $customer->id,
                'staff_id'       => $staff->id,
                'scheduled_date' => $date,
            ],
            [
                'service_ids' => [$service->id],
                'status'      => $status,
                'final_price' => $status === 'cancelled' ? null : $service->price,
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
                    'status'  => $status === 'completed' ? 'succeeded' : 'requires_payment_method',
                    'livemode' => false,
                ],
            ]
        );
    }
}
