<?php

namespace Database\Seeders;

use App\Enums\BusinessStatus;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\Payment;
use App\Models\SalonProfile;
use App\Models\SalonReview;
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

    private const DEMO_ADMIN = ['email' => 'admin@demo.salone', 'name' => 'Luca Ferretti'];

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
        $this->seedDemoAdmin($business->id);

        $staff     = $this->seedUsers(self::DEMO_STAFF, 'staff');
        $customers = $this->seedUsers(self::DEMO_CUSTOMERS, 'customer');
        $services  = $this->seedServices();

        $this->attachServicesToStaff($services, $staff);
        $this->seedAvailabilityRules($staff);
        $this->seedPreferences($customers);
        $this->seedAppointments($customers, $staff, $services);
        $this->seedDemoReviews();
        $this->seedDemoStaffBios($staff);
    }

    private function seedDemoAdmin(int $businessId): void
    {
        $admin = User::updateOrCreate(
            ['email' => self::DEMO_ADMIN['email']],
            ['name' => self::DEMO_ADMIN['name'], 'password' => Hash::make('demo1234'), 'business_id' => $businessId]
        );
        $admin->syncRoles(['admin']);
        $admin->businesses()->syncWithoutDetaching([$businessId]);
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
        $profile = SalonProfile::firstOrCreate(
            ['business_id' => app('current_business_id')],
            ['name' => 'Barbiere Demo']
        );

        $profile->update([
            'name'    => 'Barbiere Demo',
            'tagline' => "L'arte del taglio perfetto, dal 2009",
            'phone'   => '+39 02 8345 6721',
            'address' => 'Via Tortona 18, 20144 Milano',
            'description' => '<p><strong>Barbiere Demo</strong> è il punto di riferimento per chi vuole un taglio impeccabile nel cuore di Milano. Il nostro team unisce tecniche tradizionali da barbiere con le tendenze più moderne, per un risultato che valorizza ogni tipo di capello e barba.</p><p>Dalla rasatura con rasoio a mano libera all\'asciugamano caldo, fino alla modellatura della barba più complessa: ogni visita è un momento di cura dedicato a te. Niente fretta, solo qualità.</p>',
            'google_maps_embed'   => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2798.4582!2d9.1640!3d45.4549!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4786c6c0c0c0c0c0%3A0x1234567890abcdef!2sVia+Tortona%2C+18%2C+20144+Milano!5e0!3m2!1sit!2sit!4v1700000000001',
            'opening_hours' => [
                'mon' => ['type' => 'closed'],
                'tue' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                'wed' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                'thu' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                'fri' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '20:00'],
                'sat' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:00', 'afternoon_close' => '18:00'],
                'sun' => ['type' => 'closed'],
            ],
            'instagram_url'   => 'https://instagram.com/barbierodemo',
            'facebook_url'    => 'https://facebook.com/barbierodemo',
            'whatsapp_number' => '390283456721',
            'booking_button_label' => 'Prenota il tuo taglio',
            'email_greeting'      => 'Ciao {nome},',
            'email_footer_note'   => 'Grazie per aver scelto Barbiere Demo. Ti aspettiamo!',
            'email_accent_color'  => null,
            'owner_signature'     => "Il team di Barbiere Demo\nVia Tortona 18, Milano\n+39 02 8345 6721",
            'theme'       => 'luxury',
            'theme_mode'  => 'light',
            'border_style' => 'rounded',
        ]);

        if ($profile->getMedia('cover')->isEmpty()) {
            $this->addMediaSafely($profile, 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=1920&h=640&fit=crop&q=80', 'cover', 'cover.jpg');
        }

        if ($profile->getMedia('logo')->isEmpty()) {
            $this->addMediaSafely($profile, 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=200&h=200&fit=crop&q=80', 'logo', 'logo.jpg');
        }

        if ($profile->getMedia('favicon')->isEmpty()) {
            $this->addMediaSafely($profile, 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=64&h=64&fit=crop&q=80', 'favicon', 'favicon.jpg');
        }

        if ($profile->getMedia('gallery')->isEmpty()) {
            foreach ([
                'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=800&h=600&fit=crop&q=80',
                'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=800&h=600&fit=crop&q=80',
                'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?w=800&h=600&fit=crop&q=80',
                'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=800&h=600&fit=crop&q=80',
                'https://images.unsplash.com/photo-1493256338651-d82f7acb2b38?w=800&h=600&fit=crop&q=80',
            ] as $i => $url) {
                $this->addMediaSafely($profile, $url, 'gallery', "gallery_{$i}.jpg");
            }
        }
    }

    private function seedDemoReviews(): void
    {
        $reviews = [
            ['author_name' => 'Matteo C.',      'rating' => 5, 'body' => 'Taglio perfetto come sempre. Marco sa esattamente cosa vuoi anche quando non riesci a spiegarlo bene. Ambiente curato e rilassante, ci torno ogni tre settimane.', 'sort_order' => 1],
            ['author_name' => 'Luca F.',         'rating' => 5, 'body' => 'Finalmente un posto serio a Milano. Barba impeccabile, rasatura con il rasoio a mano libera fenomenale. Asciugamano caldo e prodotti di qualità. Non andrò mai più da nessun altro.', 'sort_order' => 2],
            ['author_name' => 'Davide R.',       'rating' => 5, 'body' => 'Ho provato tanti barbieri ma qui è un altro livello. Andrea è preciso, veloce e sa il fatto suo. Staff gentile e professionale, prezzi più che onesti per la qualità offerta.', 'sort_order' => 3],
            ['author_name' => 'Alessandro M.',   'rating' => 4, 'body' => 'Ottimo barbiere, ottima location. Ho preso la barba rimodellata e sono uscito soddisfatissimo. Unico neo: a volte l\'attesa è un po\' lunga, ma ne vale la pena.', 'sort_order' => 4],
            ['author_name' => 'Simone B.',       'rating' => 5, 'body' => 'Il migliore in zona senza dubbio. Sistema di prenotazione online comodissimo, staff puntuale e preciso. 5 stelle meritatissime.', 'sort_order' => 5],
        ];

        foreach ($reviews as $data) {
            SalonReview::firstOrCreate(
                ['author_name' => $data['author_name'], 'business_id' => app('current_business_id')],
                [...$data, 'is_published' => true, 'seen_at' => now()]
            );
        }
    }

    /** @param array<string, User> $staff */
    private function seedDemoStaffBios(array $staff): void
    {
        $bios = [
            'marco'  => ['bio' => 'Barbiere con 12 anni di esperienza, specializzato in tagli classici e moderni. Amante del rasoio a mano libera e della grande tradizione del barbiere italiano.', 'avatar' => 'https://i.pravatar.cc/400?img=12'],
            'andrea' => ['bio' => 'Esperto in barba e rasatura tradizionale. Ha perfezionato la sua tecnica tra Londra e Barcellona prima di tornare a Milano. Ogni visita è una consulenza.', 'avatar' => 'https://i.pravatar.cc/400?img=33'],
        ];

        foreach ($bios as $key => $data) {
            $user = $staff[$key] ?? null;
            if (! $user) {
                continue;
            }

            $user->update(['bio' => $data['bio']]);

            if ($user->getMedia('avatar')->isEmpty()) {
                $this->addMediaSafely($user, $data['avatar'], 'avatar', 'avatar.jpg');
            }
        }
    }

    private function addMediaSafely(\Illuminate\Database\Eloquent\Model $model, string $url, string $collection, string $fileName): void
    {
        try {
            $model->addMediaFromUrl($url)
                ->usingFileName($fileName)
                ->toMediaCollection($collection);
        } catch (\Throwable) {
            // URL non raggiungibile — immagine saltata
        }
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
