<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\SalonProfile;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPreference;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class LandingScreenshotSeeder extends Seeder
{
    private const SUBDOMAIN = 'atelier-chioma-demo';

    private const ADMIN = ['email' => 'lucia@atelierchioma.it', 'name' => 'Lucia Ferrario'];

    private const STAFF = [
        'sara'      => ['email' => 'sara@atelierchioma.it',      'name' => 'Sara Bianchi'],
        'valentina' => ['email' => 'valentina@atelierchioma.it', 'name' => 'Valentina Costa'],
    ];

    private const CUSTOMERS = [
        'giulia'    => ['email' => 'giulia.rossi@demo.test',    'name' => 'Giulia Rossi',     'phone' => '+39 347 1234567'],
        'marta'     => ['email' => 'marta.lombardi@demo.test',  'name' => 'Marta Lombardi',   'phone' => '+39 333 2345678'],
        'chiara'    => ['email' => 'chiara.esposito@demo.test', 'name' => 'Chiara Esposito',  'phone' => '+39 320 3456789'],
        'anna'      => ['email' => 'anna.deluca@demo.test',     'name' => 'Anna De Luca',     'phone' => '+39 393 4567890'],
        'elena'     => ['email' => 'elena.ricci@demo.test',     'name' => 'Elena Ricci',      'phone' => '+39 366 5678901'],
        'beatrice'  => ['email' => 'beatrice.moretti@demo.test','name' => 'Beatrice Moretti', 'phone' => '+39 329 6789012'],
        'sofia'     => ['email' => 'sofia.villa@demo.test',     'name' => 'Sofia Villa',      'phone' => '+39 351 7890123'],
        'paola'     => ['email' => 'paola.ferrari@demo.test',   'name' => 'Paola Ferrari',    'phone' => '+39 380 8901234'],
        'francesca' => ['email' => 'francesca.romano@demo.test','name' => 'Francesca Romano', 'phone' => '+39 335 9012345'],
        'irene'     => ['email' => 'irene.gallo@demo.test',     'name' => 'Irene Gallo',      'phone' => '+39 347 0123456'],
    ];

    private const SERVICES = [
        'piega'      => ['name' => 'Piega',                 'duration_minutes' =>  30, 'price' =>  25.00],
        'taglio'     => ['name' => 'Taglio + Piega',        'duration_minutes' =>  60, 'price' =>  45.00],
        'colore'     => ['name' => 'Colore',                'duration_minutes' => 120, 'price' =>  85.00],
        'balayage'   => ['name' => 'Balayage',              'duration_minutes' => 150, 'price' => 120.00],
        'keratina'   => ['name' => 'Trattamento Keratina',  'duration_minutes' =>  90, 'price' =>  70.00],
    ];

    public function run(): void
    {
        $this->ensureRolesExist();

        $business = $this->seedBusiness();
        app()->instance('current_business_id', $business->id);

        $this->seedSystemSettings();
        $this->seedSalonProfile();

        $lucia    = $this->seedAdmin($business->id);
        $staff    = $this->seedStaff($business->id);
        $allStaff = array_merge(['lucia' => $lucia], $staff);

        $customers = $this->seedCustomers($business->id);
        $services  = $this->seedServices();

        $this->attachServicesToStaff($services, $allStaff);
        $this->seedAvailabilityRules($allStaff);
        $this->seedAppointments($customers, $allStaff, $services);

        $this->command->info('');
        $this->command->info('Atelier Chioma demo seeded (business_id=' . $business->id . ', subdomain=' . self::SUBDOMAIN . ')');
        $this->command->info('  Admin : ' . self::ADMIN['email']);
        $this->command->info('  Staff : sara@atelierchioma.it, valentina@atelierchioma.it');
        $this->command->warn('  Password: passworddemo');
        $this->command->info('');
    }

    private function ensureRolesExist(): void
    {
        foreach (['admin', 'staff', 'customer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function seedBusiness(): Business
    {
        return Business::withoutGlobalScopes()->firstOrCreate(
            ['subdomain' => self::SUBDOMAIN],
            ['name' => 'Atelier Chioma', 'status' => 'active']
        );
    }

    private function seedSystemSettings(): void
    {
        SystemSetting::firstOrCreate(
            ['business_id' => app('current_business_id')],
            [
                'slot_generation_weeks'       => 4,
                'slot_granularity_minutes'    => 15,
                'timezone'                    => 'Europe/Rome',
                'booking_max_days_ahead'      => 60,
                'cancellation_deadline_hours' => 24,
                'reminder_count'              => 1,
                'reminder_1_hours'            => 24,
                'payment_mode'                => 'disabled',
                'reviews_enabled'             => false,
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
            ['name' => 'Atelier Chioma']
        );

        $profile->update([
            'name'        => 'Atelier Chioma',
            'tagline'     => 'Il tuo salone di fiducia a Milano',
            'phone'       => '+39 02 4567 8901',
            'address'     => 'Via Montenapoleone 12, 20121 Milano MI',
            'description' => '<p><strong>Atelier Chioma</strong> è il salone di riferimento nel cuore di Milano per chi vuole prendersi cura dei propri capelli con prodotti di alta qualità e un team di professionisti appassionati.</p><p>Specializati in colorazioni, balayage e trattamenti ricostruttivi, offriamo un servizio su misura per ogni tipo di capello.</p>',
            'opening_hours' => [
                'mon' => ['type' => 'continuous', 'open_time' => '09:00', 'close_time' => '19:30'],
                'tue' => ['type' => 'continuous', 'open_time' => '09:00', 'close_time' => '19:30'],
                'wed' => ['type' => 'continuous', 'open_time' => '09:00', 'close_time' => '19:30'],
                'thu' => ['type' => 'continuous', 'open_time' => '09:00', 'close_time' => '19:30'],
                'fri' => ['type' => 'continuous', 'open_time' => '09:00', 'close_time' => '19:30'],
                'sat' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:30', 'afternoon_close' => '18:00'],
                'sun' => ['type' => 'closed'],
            ],
            'instagram_url'        => 'https://www.instagram.com/atelierchioma',
            'facebook_url'         => 'https://www.facebook.com/atelierchioma',
            'whatsapp_number'      => '390245678901',
            'booking_button_label' => 'Prenota ora',
            'theme'                => 'luxury',
            'theme_mode'           => 'light',
            'border_style'         => 'rounded',
        ]);
    }

    private function seedAdmin(int $businessId): User
    {
        $user = User::updateOrCreate(
            ['email' => self::ADMIN['email']],
            ['name' => self::ADMIN['name'], 'password' => Hash::make('passworddemo'), 'business_id' => $businessId]
        );
        $user->syncRoles(['admin', 'staff']);
        $user->businesses()->syncWithoutDetaching([$businessId]);
        return $user;
    }

    /** @return array<string, User> */
    private function seedStaff(int $businessId): array
    {
        $users = [];
        foreach (self::STAFF as $key => $attrs) {
            $user = User::updateOrCreate(
                ['email' => $attrs['email']],
                ['name' => $attrs['name'], 'password' => Hash::make('passworddemo'), 'business_id' => $businessId]
            );
            $user->syncRoles(['staff']);
            $user->businesses()->syncWithoutDetaching([$businessId]);
            $users[$key] = $user;
        }
        return $users;
    }

    /** @return array<string, User> */
    private function seedCustomers(int $businessId): array
    {
        $users = [];
        foreach (self::CUSTOMERS as $key => $attrs) {
            $user = User::updateOrCreate(
                ['email' => $attrs['email']],
                ['name' => $attrs['name'], 'password' => Hash::make('passworddemo'), 'business_id' => $businessId]
            );
            $user->syncRoles(['customer']);

            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                ['notification_channel' => 'email', 'phone_number' => $attrs['phone']]
            );

            $users[$key] = $user;
        }
        return $users;
    }

    /** @return array<string, Service> */
    private function seedServices(): array
    {
        return collect(self::SERVICES)
            ->mapWithKeys(fn(array $attrs, string $key) => [
                $key => Service::firstOrCreate(
                    ['name' => $attrs['name'], 'business_id' => app('current_business_id')],
                    array_merge($attrs, ['active' => true])
                ),
            ])
            ->all();
    }

    /**
     * @param array<string, Service> $services
     * @param array<string, User>    $allStaff
     */
    private function attachServicesToStaff(array $services, array $allStaff): void
    {
        foreach ($allStaff as $user) {
            foreach ($services as $service) {
                $service->staff()->syncWithoutDetaching([$user->id]);
            }
        }
    }

    /** @param array<string, User> $allStaff */
    private function seedAvailabilityRules(array $allStaff): void
    {
        foreach ($allStaff as $user) {
            AvailabilityRule::where('user_id', $user->id)
                ->where('business_id', app('current_business_id'))
                ->delete();

            // Lun-Ven 09:00-19:30
            foreach ([Carbon::MONDAY, Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY] as $day) {
                AvailabilityRule::create([
                    'user_id'      => $user->id,
                    'business_id'  => app('current_business_id'),
                    'day_of_week'  => $day,
                    'start_time'   => '09:00:00',
                    'end_time'     => '19:30:00',
                    'is_available' => true,
                ]);
            }

            // Sab 09:00-13:00 e 14:30-18:00
            AvailabilityRule::create([
                'user_id'      => $user->id,
                'business_id'  => app('current_business_id'),
                'day_of_week'  => Carbon::SATURDAY,
                'start_time'   => '09:00:00',
                'end_time'     => '13:00:00',
                'start_time_2' => '14:30:00',
                'end_time_2'   => '18:00:00',
                'is_available' => true,
            ]);
        }
    }

    /**
     * @param array<string, User>    $customers
     * @param array<string, User>    $allStaff
     * @param array<string, Service> $services
     */
    private function seedAppointments(array $customers, array $allStaff, array $services): void
    {
        $today = Carbon::today();

        // ─── OGGI (per lo screenshot della lista) ───────────────────────────────
        $this->appt($customers['giulia'],    $allStaff['sara'],      $services['piega'],    $today->copy()->setTime( 9,  0), 'confirmed');
        $this->appt($customers['marta'],     $allStaff['valentina'], $services['taglio'],   $today->copy()->setTime( 9, 30), 'confirmed');
        $this->appt($customers['chiara'],    $allStaff['sara'],      $services['colore'],   $today->copy()->setTime(10,  0), 'confirmed');
        $this->appt($customers['anna'],      $allStaff['valentina'], $services['taglio'],   $today->copy()->setTime(10,  0), 'confirmed');
        $this->appt($customers['elena'],     $allStaff['lucia'],     $services['balayage'], $today->copy()->setTime(10, 30), 'confirmed');
        $this->appt($customers['beatrice'],  $allStaff['sara'],      $services['piega'],    $today->copy()->setTime(12,  0), 'pending');
        $this->appt($customers['sofia'],     $allStaff['valentina'], $services['taglio'],   $today->copy()->setTime(12,  0), 'confirmed');
        $this->appt($customers['paola'],     $allStaff['lucia'],     $services['keratina'], $today->copy()->setTime(14,  0), 'confirmed');
        $this->appt($customers['francesca'], $allStaff['sara'],      $services['taglio'],   $today->copy()->setTime(14, 30), 'confirmed');
        $this->appt($customers['irene'],     $allStaff['valentina'], $services['piega'],    $today->copy()->setTime(15,  0), 'pending');

        // ─── SETTIMANA CORRENTE (per lo screenshot del calendario) ──────────────
        $this->apptRelative($customers['giulia'],    $allStaff['sara'],      $services['piega'],    1, 10,  0, 'confirmed'); // +1 giorno
        $this->apptRelative($customers['marta'],     $allStaff['valentina'], $services['colore'],   1, 10, 30, 'confirmed');
        $this->apptRelative($customers['chiara'],    $allStaff['lucia'],     $services['balayage'], 1, 11,  0, 'pending');
        $this->apptRelative($customers['anna'],      $allStaff['sara'],      $services['taglio'],   2,  9,  0, 'confirmed'); // +2 giorni
        $this->apptRelative($customers['elena'],     $allStaff['valentina'], $services['keratina'], 2, 14,  0, 'confirmed');
        $this->apptRelative($customers['beatrice'],  $allStaff['lucia'],     $services['piega'],    2, 15,  0, 'confirmed');
        $this->apptRelative($customers['sofia'],     $allStaff['sara'],      $services['taglio'],   3, 10,  0, 'confirmed'); // +3 giorni
        $this->apptRelative($customers['paola'],     $allStaff['valentina'], $services['balayage'], 3, 11,  0, 'pending');
        $this->apptRelative($customers['francesca'], $allStaff['lucia'],     $services['taglio'],   3, 14, 30, 'confirmed');
        $this->apptRelative($customers['irene'],     $allStaff['sara'],      $services['keratina'], 4,  9, 30, 'confirmed'); // +4 giorni
        $this->apptRelative($customers['giulia'],    $allStaff['valentina'], $services['piega'],    4, 11,  0, 'confirmed');
        $this->apptRelative($customers['marta'],     $allStaff['lucia'],     $services['colore'],   4, 14,  0, 'confirmed');

        // ─── PASSATO (per dare storico al pannello) ──────────────────────────────
        $this->apptRelative($customers['chiara'],   $allStaff['sara'],      $services['taglio'],   -3, 10,  0, 'completed');
        $this->apptRelative($customers['anna'],     $allStaff['valentina'], $services['piega'],    -5, 11,  0, 'completed');
        $this->apptRelative($customers['giulia'],   $allStaff['lucia'],     $services['balayage'], -7,  9, 30, 'completed');
        $this->apptRelative($customers['beatrice'], $allStaff['sara'],      $services['taglio'],   -2, 14,  0, 'cancelled');
    }

    private function appt(User $customer, User $staff, Service $service, Carbon $date, string $status): void
    {
        Appointment::updateOrCreate(
            ['user_id' => $customer->id, 'staff_id' => $staff->id, 'scheduled_date' => $date],
            [
                'service_ids' => [$service->id],
                'status'      => $status,
                'final_price' => $status === 'cancelled' ? null : $service->price,
                'business_id' => app('current_business_id'),
            ]
        );
    }

    private function apptRelative(User $customer, User $staff, Service $service, int $daysFromToday, int $hour, int $minute, string $status): void
    {
        $date = Carbon::today()->addDays($daysFromToday)->setTime($hour, $minute);
        $this->appt($customer, $staff, $service, $date, $status);
    }
}
