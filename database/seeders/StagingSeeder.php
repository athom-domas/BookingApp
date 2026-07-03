<?php

namespace Database\Seeders;

use App\Enums\BusinessStatus;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\SalonProfile;
use App\Models\SalonReview;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StagingSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'staff', 'customer', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach ([
            'appointments.view_all', 'appointments.create', 'appointments.edit', 'appointments.delete',
            'appointments.payments', 'customers.view', 'customers.create', 'customers.edit',
            'customers.delete', 'reports.view', 'reports.view_revenue',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@staging.test'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'business_id' => null]
        );
        $superAdmin->syncRoles(['super_admin']);

        $business = Business::withoutGlobalScopes()->firstOrCreate(
            ['subdomain' => 'staging-demo'],
            ['name' => 'Demo Barber Shop', 'status' => BusinessStatus::Active, 'trial_ends_at' => now()->addDays(365)],
        );
        app()->instance('current_business_id', $business->id);

        $this->seedSystemSettings($business->id);
        $this->seedSalonProfile();
        $this->call(PageBuilderSeeder::class);
        $admin    = $this->seedAdmin($business->id);
        $staff    = $this->seedStaff($business->id);
        $allStaff = array_merge(['nicola' => $admin], $staff);
        $services  = $this->seedServices();
        $this->attachServicesToStaff($services, $allStaff);
        $this->seedAvailabilityRules($allStaff);
        $this->seedBios($allStaff);
        $this->seedReviews();
        $products  = $this->seedProducts();
        $customers = $this->seedCustomers($business->id);
        $this->seedAppointments($customers, $allStaff, $services);
        $this->seedProductOrders($customers, $products, $business->id);

        $this->command->info('');
        $this->command->info('Staging seeded — business_id=' . $business->id);
        $this->command->info('  Super Admin : superadmin@staging.test / password');
        $this->command->info('  Admin salone: admin@staging.test / password');
        $this->command->info('  Staff       : giuseppe@staging.test, giorgi@staging.test / password');
        $this->command->info('  Clienti     : giovanni@staging.test ... / password');
        $this->command->info('');
    }

    private function seedSystemSettings(int $businessId): void
    {
        SystemSetting::firstOrCreate(
            ['business_id' => $businessId],
            [
                'slot_generation_weeks'       => 4,
                'slot_granularity_minutes'    => 15,
                'timezone'                    => 'Europe/Rome',
                'booking_max_days_ahead'      => 60,
                'cancellation_deadline_hours' => 24,
                'reminder_count'              => 1,
                'reminder_1_hours'            => 24,
                'payment_mode'                => 'disabled',
                'reviews_enabled'             => true,
                'review_request_enabled'      => false,
                'loyalty_enabled'             => false,
                'loyalty_points_per_euro'     => 1,
                'loyalty_reward_threshold'    => 100,
                'loyalty_reward_percentage'   => 10,
                'follow_up_reminders_enabled' => false,
                'follow_up_reminder_days'     => 30,
            ]
        );
    }

    private function seedSalonProfile(): void
    {
        $profile = SalonProfile::firstOrCreate(
            ['business_id' => app('current_business_id')],
            ['name' => 'Demo Barber Shop']
        );

        $profile->update([
            'name'          => 'Demo Barber Shop',
            'phone'         => '349 00 00 000',
            'address'       => 'Via Demo, 1 - Città di Test',
            'opening_hours' => [
                'mon' => ['type' => 'closed'],
                'tue' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:30', 'afternoon_close' => '21:00'],
                'wed' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:30', 'afternoon_close' => '21:00'],
                'thu' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:30', 'afternoon_close' => '21:00'],
                'fri' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:30', 'afternoon_close' => '21:00'],
                'sat' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:30', 'afternoon_close' => '21:00'],
                'sun' => ['type' => 'closed'],
            ],
            'theme'             => 'luxury',
            'theme_mode'        => 'light',
            'border_style'      => 'rounded',
            'email_greeting'    => 'Ciao {nome},',
            'email_footer_note' => 'Grazie per aver scelto Demo Barber Shop.',
        ]);

        $this->addMediaSafely($profile, 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=1920&h=640&fit=crop&q=80', 'cover', 'cover.jpg');
        $this->addMediaSafely($profile, 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=200&h=200&fit=crop&q=80', 'logo', 'logo.jpg');
    }

    private function seedAdmin(int $businessId): User
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@staging.test'],
            ['name' => 'Nicola Demo', 'password' => Hash::make('password'), 'business_id' => $businessId]
        );
        $admin->syncRoles(['admin', 'staff']);
        $admin->businesses()->syncWithoutDetaching([$businessId]);

        return $admin;
    }

    /** @return array<string, User> */
    private function seedStaff(int $businessId): array
    {
        $users = [];

        foreach ([
            'giuseppe' => 'Giuseppe Demo',
            'giorgi'   => 'Giorgi Demo',
        ] as $key => $name) {
            $user = User::updateOrCreate(
                ['email' => "{$key}@staging.test"],
                ['name' => $name, 'password' => Hash::make('password'), 'business_id' => $businessId]
            );
            $user->syncRoles(['staff']);
            $user->businesses()->syncWithoutDetaching([$businessId]);
            $users[$key] = $user;
        }

        return $users;
    }

    /** @return array<string, Service> */
    private function seedServices(): array
    {
        return collect([
            'taglio'      => ['name' => 'Taglio Classico',   'duration_minutes' => 20, 'price' => 12.00],
            'rasatura'    => ['name' => 'Rasatura Barba',    'duration_minutes' => 20, 'price' => 10.00],
            'modellatura' => ['name' => 'Modellatura Barba', 'duration_minutes' => 20, 'price' =>  5.00],
            'bimbi'       => ['name' => 'Taglio Bimbi',      'duration_minutes' => 15, 'price' =>  8.00],
        ])->mapWithKeys(fn(array $attrs, string $key) => [
            $key => Service::firstOrCreate(
                ['name' => $attrs['name'], 'business_id' => app('current_business_id')],
                array_merge($attrs, ['active' => true])
            ),
        ])->all();
    }

    private function attachServicesToStaff(array $services, array $allStaff): void
    {
        $map = [
            'nicola'   => ['taglio', 'rasatura', 'modellatura', 'bimbi'],
            'giuseppe' => ['taglio', 'rasatura', 'modellatura', 'bimbi'],
            'giorgi'   => ['taglio', 'rasatura', 'modellatura', 'bimbi'],
        ];

        foreach ($map as $staffKey => $serviceKeys) {
            $user = $allStaff[$staffKey] ?? null;
            if (! $user) {
                continue;
            }
            foreach ($serviceKeys as $svcKey) {
                $services[$svcKey]->staff()->syncWithoutDetaching([$user->id]);
            }
        }
    }

    private function seedAvailabilityRules(array $allStaff): void
    {
        $schedule = [
            Carbon::TUESDAY   => ['09:00:00', '13:00:00', '15:30:00', '21:00:00'],
            Carbon::WEDNESDAY => ['09:00:00', '13:00:00', '15:30:00', '21:00:00'],
            Carbon::THURSDAY  => ['09:00:00', '13:00:00', '15:30:00', '21:00:00'],
            Carbon::FRIDAY    => ['09:00:00', '13:00:00', '15:30:00', '21:00:00'],
            Carbon::SATURDAY  => ['09:00:00', '13:00:00', '14:30:00', '21:00:00'],
        ];

        foreach ($allStaff as $user) {
            AvailabilityRule::where('user_id', $user->id)->delete();

            foreach ($schedule as $day => [$s1, $e1, $s2, $e2]) {
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

    private function seedBios(array $allStaff): void
    {
        $bios = [
            'nicola'   => ['bio' => 'Fondatore e titolare. Una passione nata da bambino, perfezionata negli anni.', 'avatar' => 'https://i.pravatar.cc/400?img=57'],
            'giuseppe' => ['bio' => 'Membro storico del team. Specializzato nel taglio classico e nella cura della barba.', 'avatar' => 'https://i.pravatar.cc/400?img=12'],
            'giorgi'   => ['bio' => 'Amava la moda sin da bambino. Nel barbiere ha trovato un mondo che evolve continuamente.', 'avatar' => 'https://i.pravatar.cc/400?img=33'],
        ];

        foreach ($bios as $key => $data) {
            $user = $allStaff[$key] ?? null;
            if (! $user) {
                continue;
            }
            $user->update(['bio' => $data['bio']]);
            $this->addMediaSafely($user, $data['avatar'], 'avatar', 'avatar.jpg');
        }
    }

    private function seedReviews(): void
    {
        foreach ([
            ['author_name' => 'Antonio V.',   'rating' => 5, 'body' => 'Taglio preciso, rasatura impeccabile. Non vado da nessun altro.',          'sort_order' => 1],
            ['author_name' => 'Giuseppe M.',  'rating' => 5, 'body' => 'Professionalità al massimo. Barba perfetta ogni volta.',                    'sort_order' => 2],
            ['author_name' => 'Francesco P.', 'rating' => 5, 'body' => 'Team preparato e appassionato. Ambiente curato e rilassante. Consigliato.', 'sort_order' => 3],
            ['author_name' => 'Salvatore D.', 'rating' => 5, 'body' => 'Da anni il mio punto di riferimento. Rasatura tradizionale impeccabile.',   'sort_order' => 4],
        ] as $data) {
            SalonReview::firstOrCreate(
                ['author_name' => $data['author_name'], 'business_id' => app('current_business_id')],
                [...$data, 'is_published' => true, 'seen_at' => now()]
            );
        }
    }

    /** @return array<string, Product> */
    private function seedProducts(): array
    {
        $products = [];

        foreach ([
            'cera'     => ['name' => 'Cera per capelli', 'description' => 'Tenuta forte, finish opaco.', 'price' => 12.00, 'stock' => 20],
            'shampoo'  => ['name' => 'Shampoo barba',    'description' => 'Ammorbidisce e nutre la barba.', 'price' => 9.50,  'stock' => 15],
            'olio'     => ['name' => 'Olio da barba',    'description' => 'Idratante e profumato.',        'price' => 14.00, 'stock' => 10],
            'rasoio'   => ['name' => 'Rasoio di sicurezza', 'description' => 'Acciaio inossidabile, manico in ottone.', 'price' => 28.00, 'stock' => 8],
        ] as $key => $data) {
            $products[$key] = Product::firstOrCreate(
                ['name' => $data['name'], 'business_id' => app('current_business_id')],
                [...$data, 'in_sale' => true, 'active' => true]
            );
        }

        return $products;
    }

    /** @return array<string, User> */
    private function seedCustomers(int $businessId): array
    {
        $customers = [];

        foreach ([
            'giovanni'   => 'Giovanni Esposito',
            'alessandro' => 'Alessandro Romano',
            'matteo'     => 'Matteo Ricci',
            'davide'     => 'Davide Gallo',
            'simone'     => 'Simone Marino',
        ] as $key => $name) {
            $user = User::updateOrCreate(
                ['email' => "{$key}@staging.test"],
                ['name' => $name, 'password' => Hash::make('password'), 'business_id' => $businessId]
            );
            $user->syncRoles(['customer']);
            $customers[$key] = $user;
        }

        return $customers;
    }

    private function seedAppointments(array $customers, array $allStaff, array $services): void
    {
        $nicola   = $allStaff['nicola'];
        $giuseppe = $allStaff['giuseppe'];
        $giorgi   = $allStaff['giorgi'];

        // Passati completati
        $past = [
            [$customers['giovanni'],   $nicola,   $services['taglio'],      Carbon::now()->subWeeks(3)->next(Carbon::TUESDAY)->setTime(10, 0),    'completed'],
            [$customers['alessandro'], $giuseppe, $services['rasatura'],    Carbon::now()->subWeeks(2)->next(Carbon::WEDNESDAY)->setTime(11, 30), 'completed'],
            [$customers['matteo'],     $giorgi,   $services['modellatura'], Carbon::now()->subWeeks(2)->next(Carbon::THURSDAY)->setTime(15, 0),   'completed'],
            [$customers['davide'],     $nicola,   $services['taglio'],      Carbon::now()->subWeeks(1)->next(Carbon::FRIDAY)->setTime(9, 0),      'completed'],
            [$customers['simone'],     $giuseppe, $services['rasatura'],    Carbon::now()->subDays(5)->setTime(16, 0),                            'completed'],
            [$customers['giovanni'],   $giorgi,   $services['modellatura'], Carbon::now()->subDays(10)->setTime(10, 30),                          'completed'],
            [$customers['alessandro'], $nicola,   $services['taglio'],      Carbon::now()->subWeeks(4)->next(Carbon::SATURDAY)->setTime(9, 30),   'completed'],
        ];

        foreach ($past as [$customer, $staff, $service, $date, $status]) {
            $apt = $this->upsertAppointment($customer, $staff, $service, $date, $status);
            $this->seedPayment($apt, 'completed');
        }

        // Cancellato
        $this->upsertAppointment($customers['matteo'], $nicola, $services['taglio'], Carbon::now()->subDays(4)->setTime(9, 0), 'cancelled');

        // Futuri confermati
        $future = [
            [$customers['giovanni'],   $nicola,   $services['taglio'],      Carbon::now()->next(Carbon::TUESDAY)->setTime(10, 0),    'confirmed'],
            [$customers['alessandro'], $giuseppe, $services['rasatura'],    Carbon::now()->next(Carbon::WEDNESDAY)->setTime(11, 30), 'confirmed'],
            [$customers['davide'],     $giorgi,   $services['taglio'],      Carbon::now()->next(Carbon::THURSDAY)->setTime(14, 0),   'confirmed'],
        ];

        foreach ($future as [$customer, $staff, $service, $date, $status]) {
            $this->upsertAppointment($customer, $staff, $service, $date, $status);
        }

        // In attesa
        $this->upsertAppointment($customers['matteo'], $nicola,   $services['modellatura'], Carbon::now()->next(Carbon::FRIDAY)->setTime(16, 0),    'pending');
        $this->upsertAppointment($customers['simone'], $giuseppe, $services['rasatura'],    Carbon::now()->next(Carbon::SATURDAY)->setTime(10, 30), 'pending');
    }

    private function upsertAppointment(User $customer, User $staff, Service $service, Carbon $date, string $status): Appointment
    {
        return Appointment::updateOrCreate(
            ['user_id' => $customer->id, 'staff_id' => $staff->id, 'scheduled_date' => $date],
            [
                'service_ids'  => [$service->id],
                'status'       => $status,
                'final_price'  => $status === 'cancelled' ? null : $service->price,
                'business_id'  => app('current_business_id'),
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
                'payment_method'        => 'cash',
                'stripe_transaction_id' => null,
            ]
        );
    }

    private function seedProductOrders(array $customers, array $products, int $businessId): void
    {
        $orders = [
            [$customers['giovanni'],   ['cera' => 2, 'shampoo' => 1], 'completed', 'cash'],
            [$customers['alessandro'], ['olio' => 1, 'rasoio' => 1],  'completed', 'cash'],
            [$customers['matteo'],     ['shampoo' => 2],               'completed', 'cash'],
            [$customers['davide'],     ['cera' => 1, 'olio' => 1],    'confirmed', 'cash'],
            [$customers['simone'],     ['rasoio' => 1],                'pending',   'cash'],
        ];

        foreach ($orders as [$customer, $items, $status, $method]) {
            $order = ProductOrder::create([
                'business_id'    => $businessId,
                'user_id'        => $customer->id,
                'status'         => $status,
                'payment_method' => $method,
                'payment_status' => $status === 'completed' ? 'paid' : 'pending',
            ]);

            foreach ($items as $key => $qty) {
                ProductOrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $products[$key]->id,
                    'quantity'   => $qty,
                    'unit_price' => $products[$key]->price,
                ]);
            }
        }
    }

    private function addMediaSafely(\Illuminate\Database\Eloquent\Model $model, string $url, string $collection, string $fileName): void
    {
        if (method_exists($model, 'getMedia') && $model->getMedia($collection)->isEmpty()) {
            try {
                $model->addMediaFromUrl($url)->usingFileName($fileName)->toMediaCollection($collection);
            } catch (\Throwable) {
            }
        }
    }
}
