<?php

namespace Database\Seeders;

use App\Enums\BusinessStatus;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\Payment;
use App\Models\SalonProfile;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPreference;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProductionSeeder extends Seeder
{
    private const DEMO_SUBDOMAIN = 'demo';

    private const DEMO_STAFF = [
        'marco'  => ['email' => 'marco@demo.salone',  'name' => 'Marco Russo'],
        'andrea' => ['email' => 'andrea@demo.salone', 'name' => 'Andrea Conti'],
    ];

    private const DEMO_CUSTOMERS = [
        'giovanni' => ['email' => 'giovanni@demo.salone', 'name' => 'Giovanni Esposito', 'phone' => '+39 333 1234567'],
        'luca'     => ['email' => 'luca@demo.salone',     'name' => 'Luca Ferraro',       'phone' => '+39 329 6789012'],
    ];

    private const DEMO_SERVICES = [
        'taglio'       => ['name' => 'Taglio capelli',        'duration_minutes' => 30, 'price' => 18.00],
        'taglio_barba' => ['name' => 'Taglio + Barba',        'duration_minutes' => 50, 'price' => 28.00],
        'rasatura'     => ['name' => 'Rasatura tradizionale', 'duration_minutes' => 30, 'price' => 15.00],
        'barba'        => ['name' => 'Rimodellamento barba',  'duration_minutes' => 20, 'price' => 12.00],
    ];

    private const DEMO_STAFF_SERVICES = [
        'marco'  => ['taglio', 'taglio_barba', 'barba'],
        'andrea' => ['taglio', 'taglio_barba', 'rasatura', 'barba'],
    ];

    // [customer, staff, service, day-of-month, hour, minute]
    private const DEMO_APPOINTMENT_TEMPLATES = [
        ['giovanni', 'marco',  'taglio',       3,  9,  0],
        ['giovanni', 'andrea', 'taglio_barba', 12, 15,  0],
        ['luca',     'andrea', 'taglio',       3,  9, 30],
        ['luca',     'marco',  'taglio_barba', 12, 15, 30],
        ['giovanni', 'marco',  'barba',        20,  10,  0],
        ['luca',     'andrea', 'rasatura',     20,  11,  0],
    ];

    private const DEMO_AVAILABILITY = [
        Carbon::TUESDAY   => ['09:00:00', '13:00:00', '15:00:00', '19:30:00'],
        Carbon::WEDNESDAY => ['09:00:00', '13:00:00', '15:00:00', '19:30:00'],
        Carbon::THURSDAY  => ['09:00:00', '13:00:00', '15:00:00', '19:30:00'],
        Carbon::FRIDAY    => ['09:00:00', '13:00:00', '15:00:00', '19:30:00'],
        Carbon::SATURDAY  => ['09:00:00', '13:00:00', '14:00:00', '18:00:00'],
    ];

    public function run(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedSuperAdmin();
        $this->seedDemoBusiness();
    }

    private function seedRolesAndPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'staff', 'customer', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach ([
            'appointments.view_all',
            'appointments.create',
            'appointments.edit',
            'appointments.delete',
            'appointments.payments',
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            'reports.view',
            'reports.view_revenue',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        Permission::where('name', 'payments.manage')->where('guard_name', 'web')->delete();
    }

    private function seedSuperAdmin(): void
    {
        $email    = env('SUPER_ADMIN_EMAIL') or abort(1, 'SUPER_ADMIN_EMAIL non impostato nel .env');
        $password = env('SUPER_ADMIN_PASSWORD') or abort(1, 'SUPER_ADMIN_PASSWORD non impostato nel .env');

        $superAdmin = User::updateOrCreate(
            ['email' => $email],
            ['name' => env('SUPER_ADMIN_NAME', 'Super Admin'), 'password' => Hash::make($password), 'business_id' => null]
        );
        $superAdmin->syncRoles(['super_admin']);
    }

    private function seedDemoBusiness(): void
    {
        $business = Business::withoutGlobalScopes()->firstOrCreate(
            ['subdomain' => self::DEMO_SUBDOMAIN],
            ['name' => 'Salone Demo', 'status' => BusinessStatus::Active, 'trial_ends_at' => now()->addYears(10)],
        );

        app()->instance('current_business_id', $business->id);

        $this->seedSystemSettings();
        $this->seedDemoSalonProfile();

        $staff     = $this->seedUsers(self::DEMO_STAFF, 'staff');
        $customers = $this->seedUsers(self::DEMO_CUSTOMERS, 'customer');
        $services  = $this->seedServices();

        $this->attachServicesToStaff($services, $staff);
        $this->seedAvailabilityRules($staff);
        $this->seedPreferences($customers);
        $this->seedAppointments($customers, $staff, $services);
    }

    private function seedSystemSettings(): void
    {
        if (! SystemSetting::where('business_id', app('current_business_id'))->exists()) {
            SystemSetting::create([
                'business_id'                 => app('current_business_id'),
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
        }
    }

    private function seedDemoSalonProfile(): void
    {
        SalonProfile::firstOrCreate(
            ['business_id' => app('current_business_id')],
            [
                'name'        => 'Salone Demo',
                'tagline'     => 'Il tuo salone di fiducia',
                'phone'       => '+39 02 1234567',
                'address'     => 'Via Roma 1, Milano',
                'description' => '<p>Benvenuto nel nostro salone. Offriamo servizi professionali di taglio, barba e trattamenti capelli in un ambiente accogliente e moderno.</p>',
                'opening_hours' => [
                    'mon' => ['type' => 'closed'],
                    'tue' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                    'wed' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                    'thu' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                    'fri' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                    'sat' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:00', 'afternoon_close' => '18:00'],
                    'sun' => ['type' => 'closed'],
                ],
            ]
        );
    }

    /** @return array<string, User> */
    private function seedUsers(array $config, string $role): array
    {
        $users = [];
        foreach ($config as $key => $attrs) {
            $user = User::updateOrCreate(
                ['email' => $attrs['email']],
                ['name' => $attrs['name'], 'password' => Hash::make('demo1234'), 'business_id' => app('current_business_id')]
            );
            $user->syncRoles([$role]);
            $users[$key] = $user;
        }

        return $users;
    }

    /** @return array<string, Service> */
    private function seedServices(): array
    {
        return collect(self::DEMO_SERVICES)
            ->mapWithKeys(fn (array $attrs, string $key) => [
                $key => Service::firstOrCreate(
                    ['name' => $attrs['name'], 'business_id' => app('current_business_id')],
                    array_merge($attrs, ['active' => true])
                ),
            ])
            ->all();
    }

    /** @param array<string, Service> $services @param array<string, User> $staff */
    private function attachServicesToStaff(array $services, array $staff): void
    {
        foreach (self::DEMO_STAFF_SERVICES as $staffKey => $serviceKeys) {
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
            foreach (self::DEMO_AVAILABILITY as $day => [$s1, $e1, $s2, $e2]) {
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

    /** @param array<string, User> $customers */
    private function seedPreferences(array $customers): void
    {
        foreach ($customers as $key => $user) {
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                ['notification_channel' => 'email', 'phone_number' => self::DEMO_CUSTOMERS[$key]['phone'] ?? null]
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
        $now        = now();
        $monthStart = $now->copy()->startOfMonth();

        for ($offset = 0; $offset < 3; $offset++) {
            $base = $monthStart->copy()->addMonths($offset);

            foreach (self::DEMO_APPOINTMENT_TEMPLATES as [$custKey, $staffKey, $svcKey, $targetDay, $hour, $minute]) {
                $dateTime = $base->copy()->day($targetDay)->setTime($hour, $minute);
                $status   = $dateTime->lt($now) ? 'completed' : 'confirmed';

                $existing = Appointment::where('user_id', $customers[$custKey]->id)
                    ->where('staff_id', $staff[$staffKey]->id)
                    ->whereDate('scheduled_date', $dateTime->toDateString())
                    ->first();

                if ($existing) {
                    continue;
                }

                $appointment = Appointment::create([
                    'user_id'        => $customers[$custKey]->id,
                    'staff_id'       => $staff[$staffKey]->id,
                    'scheduled_date' => $dateTime,
                    'service_ids'    => [$services[$svcKey]->id],
                    'status'         => $status,
                    'final_price'    => $services[$svcKey]->price,
                    'business_id'    => app('current_business_id'),
                ]);

                if ($status === 'completed') {
                    Payment::firstOrCreate(
                        ['appointment_id' => $appointment->id],
                        [
                            'user_id'               => $appointment->user_id,
                            'amount'                => $appointment->final_price,
                            'status'                => 'completed',
                            'stripe_transaction_id' => 'pi_demo_' . $appointment->id,
                            'stripe_response'       => ['id' => 'pi_demo_' . $appointment->id, 'object' => 'payment_intent', 'status' => 'succeeded', 'livemode' => false],
                        ]
                    );
                }
            }
        }
    }
}
