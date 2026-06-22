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
    private const WORK_DAYS = [Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY, Carbon::SATURDAY];

    // Orari entro l'apertura del salone (Rossini): mar-ven split 09-13 / 15-19:30, sab split 09-13 / 14-18
    private const AVAILABILITY = [
        Carbon::TUESDAY   => ['09:00:00', '13:00:00', '15:00:00', '19:30:00'],
        Carbon::WEDNESDAY => ['09:00:00', '13:00:00', '15:00:00', '19:30:00'],
        Carbon::THURSDAY  => ['09:00:00', '13:00:00', '15:00:00', '19:30:00'],
        Carbon::FRIDAY    => ['09:00:00', '13:00:00', '15:00:00', '19:30:00'],
        Carbon::SATURDAY  => ['09:00:00', '13:00:00', '14:00:00', '18:00:00'],
    ];

    private const STAFF = [
        'marco'  => ['email' => 'marco@rossini.test',  'name' => 'Marco Russo'],
        'andrea' => ['email' => 'andrea@rossini.test', 'name' => 'Andrea Conti'],
    ];

    private const CUSTOMERS = [
        'giovanni' => ['email' => 'giovanni@rossini.test', 'name' => 'Giovanni Esposito', 'phone' => '+39 333 1234567'],
        'luca'     => ['email' => 'luca@rossini.test',     'name' => 'Luca Ferraro',       'phone' => '+39 329 6789012'],
    ];

    private const SERVICES = [
        'taglio'       => ['name' => 'Taglio capelli',       'description' => 'Taglio classico o moderno con finishing.',                    'duration_minutes' => 30, 'price' => 18.00, 'active' => true],
        'taglio_barba' => ['name' => 'Taglio + Barba',       'description' => 'Taglio capelli con cura e rifinitura della barba.',            'duration_minutes' => 50, 'price' => 28.00, 'active' => true],
        'rasatura'     => ['name' => 'Rasatura tradizionale', 'description' => 'Rasatura con rasoio a mano libera e asciugamano caldo.',     'duration_minutes' => 30, 'price' => 15.00, 'active' => true],
        'barba'        => ['name' => 'Rimodellamento barba', 'description' => 'Modellatura, rifinitura e trattamento della barba.',          'duration_minutes' => 20, 'price' => 12.00, 'active' => true],
        'colore'       => ['name' => 'Colore / Tinta',       'description' => 'Colorazione capelli con prodotti professionali.',             'duration_minutes' => 60, 'price' => 40.00, 'active' => true],
    ];

    private const STAFF_SERVICES = [
        'marco'  => ['taglio', 'taglio_barba', 'barba'],
        'andrea' => ['taglio', 'taglio_barba', 'rasatura', 'barba'],
    ];

    // [customer, staff, service, target_day, hour, minute]
    private const APPOINTMENT_TEMPLATES = [
        ['giovanni', 'marco',  'taglio',       3,  9,  0],
        ['giovanni', 'andrea', 'taglio_barba', 25, 15, 0],
        ['luca',     'andrea', 'taglio',       3,  9, 30],
        ['luca',     'marco',  'taglio_barba', 25, 15, 30],
    ];

    public function run(): void
    {
        $staff     = $this->seedUsers(self::STAFF, 'staff');
        $customers = $this->seedUsers(self::CUSTOMERS, 'customer');
        $services  = $this->seedServices();

        $this->attachServicesToStaff($services, $staff);
        $this->seedAvailabilityRules($staff);
        $this->seedPreferences($customers, $staff);
        $this->seedAppointments($customers, $staff, $services);
    }

    /** @return array<string, User> */
    private function seedUsers(array $config, string $role): array
    {
        $users = [];
        foreach ($config as $key => $attrs) {
            $user = User::updateOrCreate(
                ['email' => $attrs['email']],
                ['name' => $attrs['name'], 'password' => Hash::make('password'), 'business_id' => app('current_business_id')]
            );
            $user->syncRoles([$role]);
            $users[$key] = $user;
        }

        return $users;
    }

    /** @return array<string, Service> */
    private function seedServices(): array
    {
        return collect(self::SERVICES)
            ->mapWithKeys(fn (array $attrs, string $key) => [
                $key => Service::updateOrCreate(
                    ['name' => $attrs['name'], 'business_id' => app('current_business_id')],
                    $attrs
                ),
            ])
            ->all();
    }

    /**
     * @param array<string, Service> $services
     * @param array<string, User>    $staff
     */
    private function attachServicesToStaff(array $services, array $staff): void
    {
        foreach (self::STAFF_SERVICES as $staffKey => $serviceKeys) {
            foreach ($serviceKeys as $svcKey) {
                $services[$svcKey]->staff()->syncWithoutDetaching([$staff[$staffKey]->id]);
            }
        }
    }

    /** @param array<string, User> $staff */
    private function seedAvailabilityRules(array $staff): void
    {
        foreach ($staff as $user) {
            AvailabilityRule::where('user_id', $user->id)->delete();
            foreach (self::AVAILABILITY as $day => [$s1, $e1, $s2, $e2]) {
                AvailabilityRule::create([
                    'user_id'      => $user->id,
                    'business_id'  => app('current_business_id'),
                    'day_of_week'  => $day,
                    'start_time'   => $s1,
                    'end_time'     => $e1,
                    'start_time_2' => $s2,
                    'end_time_2'   => $e2,
                    'is_available' => true,
                ]);
            }
        }
    }

    /**
     * @param array<string, User> $customers
     * @param array<string, User> $staff
     */
    private function seedPreferences(array $customers, array $staff): void
    {
        foreach ($customers as $key => $user) {
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                ['notification_channel' => 'email', 'phone_number' => self::CUSTOMERS[$key]['phone'] ?? null]
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
    private function seedAppointments(array $customers, array $staff, array $services): void
    {
        $now = now();

        $existingIds = Appointment::pluck('id');
        Payment::whereIn('appointment_id', $existingIds)->delete();
        Appointment::whereIn('id', $existingIds)->delete();

        for ($offset = 0; $offset < 3; $offset++) {
            $monthStart = $now->copy()->startOfMonth()->addMonths($offset);

            foreach (self::APPOINTMENT_TEMPLATES as [$custKey, $staffKey, $svcKey, $targetDay, $hour, $minute]) {
                $date     = $this->workdayOnOrAfter($monthStart, $targetDay);
                $dateTime = $date->setTime($hour, $minute);
                $status   = $dateTime->lt($now) ? 'completed' : 'confirmed';

                $appointment = Appointment::create([
                    'user_id'        => $customers[$custKey]->id,
                    'staff_id'       => $staff[$staffKey]->id,
                    'scheduled_date' => $dateTime,
                    'service_ids'    => [$services[$svcKey]->id],
                    'status'         => $status,
                    'final_price'    => $services[$svcKey]->price,
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

    private function workdayOnOrAfter(Carbon $baseDate, int $targetDay): Carbon
    {
        $date = $baseDate->copy()->day($targetDay);
        while (! in_array($date->dayOfWeek, self::WORK_DAYS)) {
            $date->addDay();
        }

        return $date;
    }
}
