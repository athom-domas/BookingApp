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

class CurrentMonthSeeder extends Seeder
{
    private const MORNING_SLOTS   = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00'];
    private const AFTERNOON_SLOTS = ['16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00'];
    private const WORK_DAYS       = [Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY, Carbon::SATURDAY];

    private const STAFF_SERVICES = [
        'marco'   => ['taglio', 'taglio_barba', 'barba'],
        'andrea'  => ['taglio', 'taglio_barba', 'rasatura', 'barba'],
        'filippo' => ['taglio', 'taglio_barba', 'rasatura', 'barba', 'colore'],
    ];

    public function run(): void
    {
        $staff     = $this->seedStaff();
        $customers = $this->seedCustomers();
        $services  = $this->seedServices();

        $this->attachServicesToStaff($services, $staff);
        $this->seedAvailabilityRules($staff);
        $this->seedPreferences($customers, $staff);
        $this->seedCurrentMonthAppointments($customers, $staff, $services);
    }

    /** @return array<string, User> */
    private function seedStaff(): array
    {
        $data = [
            'marco'   => ['email' => 'marco@barbershop.test',   'name' => 'Marco Russo'],
            'andrea'  => ['email' => 'andrea@barbershop.test',  'name' => 'Andrea Conti'],
            'filippo' => ['email' => 'filippo@barbershop.test', 'name' => 'Filippo Mancini'],
        ];

        $users = [];
        foreach ($data as $key => $attrs) {
            $user = User::updateOrCreate(
                ['email' => $attrs['email']],
                ['name' => $attrs['name'], 'password' => Hash::make('password')]
            );
            $user->syncRoles(['staff']);
            $users[$key] = $user;
        }

        return $users;
    }

    /** @return array<string, User> */
    private function seedCustomers(): array
    {
        $data = [
            'giovanni'   => ['email' => 'giovanni@customer.test',   'name' => 'Giovanni Esposito'],
            'alessandro' => ['email' => 'alessandro@customer.test', 'name' => 'Alessandro Romano'],
            'matteo'     => ['email' => 'matteo@customer.test',     'name' => 'Matteo Ricci'],
            'davide'     => ['email' => 'davide@customer.test',     'name' => 'Davide Gallo'],
            'simone'     => ['email' => 'simone@customer.test',     'name' => 'Simone Marino'],
            'luca'       => ['email' => 'luca@customer.test',       'name' => 'Luca Ferraro'],
            'antonio'    => ['email' => 'antonio@customer.test',    'name' => 'Antonio De Luca'],
            'giuseppe'   => ['email' => 'giuseppe@customer.test',   'name' => 'Giuseppe Lombardi'],
            'roberto'    => ['email' => 'roberto@customer.test',    'name' => 'Roberto Moretti'],
            'stefano'    => ['email' => 'stefano@customer.test',    'name' => 'Stefano Vitali'],
        ];

        $users = [];
        foreach ($data as $key => $attrs) {
            $user = User::updateOrCreate(
                ['email' => $attrs['email']],
                ['name' => $attrs['name'], 'password' => Hash::make('password')]
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
                'name' => 'Taglio capelli', 'description' => 'Taglio classico o moderno con finishing.',
                'duration_minutes' => 30, 'price' => 18.00, 'active' => true,
            ],
            'taglio_barba' => [
                'name' => 'Taglio + Barba', 'description' => 'Taglio capelli con cura e rifinitura della barba.',
                'duration_minutes' => 50, 'price' => 28.00, 'active' => true,
            ],
            'rasatura' => [
                'name' => 'Rasatura tradizionale', 'description' => 'Rasatura con rasoio a mano libera e asciugamano caldo.',
                'duration_minutes' => 30, 'price' => 15.00, 'active' => true,
            ],
            'barba' => [
                'name' => 'Rimodellamento barba', 'description' => 'Modellatura, rifinitura e trattamento della barba.',
                'duration_minutes' => 20, 'price' => 12.00, 'active' => true,
            ],
            'colore' => [
                'name' => 'Colore / Tinta', 'description' => 'Colorazione capelli con prodotti professionali.',
                'duration_minutes' => 60, 'price' => 40.00, 'active' => true,
            ],
        ];

        return collect($definitions)
            ->mapWithKeys(fn (array $attrs, string $key) => [
                $key => Service::updateOrCreate(['name' => $attrs['name']], $attrs),
            ])
            ->all();
    }

    /**
     * @param array<string, Service> $services
     * @param array<string, User>    $staff
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
        $slots = [['08:00:00', '13:00:00'], ['16:00:00', '21:00:00']];

        foreach ($staff as $user) {
            foreach (self::WORK_DAYS as $day) {
                foreach ($slots as [$start, $end]) {
                    AvailabilityRule::updateOrCreate(
                        ['user_id' => $user->id, 'day_of_week' => $day, 'start_time' => $start],
                        ['end_time' => $end, 'is_available' => true]
                    );
                }
            }
        }
    }

    /**
     * @param array<string, User> $customers
     * @param array<string, User> $staff
     */
    private function seedPreferences(array $customers, array $staff): void
    {
        $phones = [
            'giovanni' => '+39 333 1234567', 'alessandro' => '+39 347 2345678',
            'matteo'   => '+39 320 3456789', 'davide'     => '+39 393 4567890',
            'simone'   => '+39 366 5678901', 'luca'       => '+39 329 6789012',
            'antonio'  => '+39 388 7890123', 'giuseppe'   => '+39 344 8901234',
            'roberto'  => '+39 371 9012345', 'stefano'    => '+39 302 0123456',
        ];

        foreach ($customers as $key => $user) {
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                ['notification_channel' => 'email', 'phone_number' => $phones[$key] ?? null]
            );
        }

        foreach ($staff as $user) {
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                ['notification_channel' => 'email', 'phone_number' => null]
            );
        }
    }

    /**
     * @param array<string, User>    $customers
     * @param array<string, User>    $staff
     * @param array<string, Service> $services
     */
    private function seedCurrentMonthAppointments(array $customers, array $staff, array $services): void
    {
        $now        = now();
        $monthStart = $now->copy()->startOfMonth()->startOfDay();
        $monthEnd   = $now->copy()->endOfMonth()->endOfDay();

        $existingIds = Appointment::whereBetween('scheduled_date', [$monthStart, $monthEnd])->pluck('id');
        Payment::whereIn('appointment_id', $existingIds)->delete();
        Appointment::whereIn('id', $existingIds)->delete();

        $customerValues = array_values($customers);

        $allSlotsMins = array_map(
            fn ($t) => $this->timeToMinutes($t),
            array_merge(self::MORNING_SLOTS, self::AFTERNOON_SLOTS)
        );

        for ($date = $monthStart->copy(); $date->lte($monthEnd); $date->addDay()) {
            if (! in_array($date->dayOfWeek, self::WORK_DAYS)) {
                continue;
            }

            $dateStr   = $date->toDateString();
            $baseCount = $date->isSaturday() ? 5 : ($date->isFriday() ? 4 : 3);

            foreach (array_keys($staff) as $staffKey) {
                $staffMember  = $staff[$staffKey];
                $staffSvcKeys = self::STAFF_SERVICES[$staffKey];
                $target       = $this->dInt("n_{$dateStr}_{$staffKey}", $baseCount, $baseCount + 2);
                $booked       = []; // [[start_min, end_min], ...]

                for ($attempt = 0; count($booked) < $target && $attempt < count($allSlotsMins) * 2; $attempt++) {
                    $seed = "{$dateStr}_{$staffKey}_{$attempt}";

                    // Slots where at least one staff service fits without overlap or session breach
                    $available = array_values(array_filter(
                        $allSlotsMins,
                        fn ($m) => collect($staffSvcKeys)->contains(
                            fn ($k) => $this->slotFits($m, $services[$k]->duration_minutes, $booked)
                        )
                    ));

                    if (empty($available)) {
                        break;
                    }

                    $slotMin = $available[$this->dInt("slot_{$seed}", 0, count($available) - 1)];

                    // Pick a service that actually fits at this slot
                    $fitting = array_values(array_filter(
                        $staffSvcKeys,
                        fn ($k) => $this->slotFits($slotMin, $services[$k]->duration_minutes, $booked)
                    ));

                    $svcKey  = $fitting[$this->dInt("svc_{$seed}", 0, count($fitting) - 1)];
                    $service = $services[$svcKey];

                    $booked[] = [$slotMin, $slotMin + $service->duration_minutes];

                    $customer      = $customerValues[$this->dInt("c_{$seed}", 0, count($customerValues) - 1)];
                    $scheduledDate = $date->copy()->setTime(intdiv($slotMin, 60), $slotMin % 60);
                    $status        = $this->resolveStatus($scheduledDate, $now, $seed);

                    $appointment = Appointment::create([
                        'user_id'        => $customer->id,
                        'staff_id'       => $staffMember->id,
                        'scheduled_date' => $scheduledDate,
                        'service_ids'    => [$service->id],
                        'status'         => $status,
                        'final_price'    => $status === 'cancelled' ? null : $service->price,
                    ]);

                    if ($status === 'completed') {
                        Payment::create([
                            'appointment_id'        => $appointment->id,
                            'user_id'               => $appointment->user_id,
                            'amount'                => $appointment->final_price,
                            'status'                => 'completed',
                            'stripe_transaction_id' => 'pi_demo_' . $appointment->id,
                            'stripe_response'       => [
                                'id'      => 'pi_demo_' . $appointment->id,
                                'object'  => 'payment_intent',
                                'status'  => 'succeeded',
                                'livemode' => false,
                            ],
                        ]);
                    }
                }
            }
        }
    }

    // Returns true if slotMin + durationMin fits without overlapping existing bookings
    // and without breaching session boundaries (morning closes 13:00, afternoon 21:00).
    private function slotFits(int $slotMin, int $durationMin, array $booked): bool
    {
        $end = $slotMin + $durationMin;

        if ($slotMin < 780 && $end > 780) {
            return false;
        }
        if ($slotMin >= 960 && $end > 1260) {
            return false;
        }

        foreach ($booked as [$bs, $be]) {
            if ($slotMin < $be && $end > $bs) {
                return false;
            }
        }

        return true;
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return (int) $h * 60 + (int) $m;
    }

    private function resolveStatus(Carbon $scheduledDate, Carbon $now, string $seed): string
    {
        if ($scheduledDate->lt($now->copy()->startOfDay())) {
            return $this->dInt("st_{$seed}", 0, 19) < 17 ? 'completed' : 'cancelled';
        }

        if ($scheduledDate->isSameDay($now)) {
            return $scheduledDate->lt($now) ? 'completed' : 'confirmed';
        }

        return $this->dInt("st_{$seed}", 0, 9) < 7 ? 'confirmed' : 'pending';
    }

    private function dInt(string $seed, int $min, int $max): int
    {
        return $min + (abs(crc32($seed)) % ($max - $min + 1));
    }


}
