<?php

namespace Database\Seeders;

use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\Product;
use App\Models\SalonProfile;
use App\Models\SalonReview;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MarinoBarberShopSeeder extends Seeder
{
    private const BUSINESS_ID = 3;

    private const ADMIN = ['email' => 'nicola@marinobarbershop.it', 'name' => 'Nicola Marino'];

    private const STAFF = [
        'giuseppe' => ['email' => 'giuseppe@marinobarbershop.it', 'name' => 'Giuseppe Rinaldi'],
        'giorgi'   => ['email' => 'giorgi@marinobarbershop.it',   'name' => 'Giorgi Chirkovi'],
        'michele'  => ['email' => 'michele@marinobarbershop.it',  'name' => 'Michele Picciallo Ariani'],
    ];

    private const SERVICES = [
        'taglio'      => ['name' => 'Taglio Classico',             'duration_minutes' => 20, 'price' => 12.00],
        'rasatura'    => ['name' => "Rasatura Barba",              'duration_minutes' => 20, 'price' => 10.00],
        'modellatura' => ['name' => 'Modellatura Barba',           'duration_minutes' => 20, 'price' =>  5.00],
        'bimbi'       => ['name' => 'Taglio Bimbi',                'duration_minutes' => 15, 'price' =>  8.00],
    ];

    // Nicola fa tutto; Michele (apprendista 22 anni) non fa ancora rasatura tradizionale
    private const STAFF_SERVICES = [
        'nicola'   => ['taglio', 'rasatura', 'modellatura', 'bimbi'],
        'giuseppe' => ['taglio', 'rasatura', 'modellatura', 'bimbi'],
        'giorgi'   => ['taglio', 'rasatura', 'modellatura', 'bimbi'],
        'michele'  => ['taglio', 'modellatura', 'bimbi'],
    ];

    private const AVAILABILITY = [
        Carbon::TUESDAY   => ['09:00:00', '13:00:00', '15:30:00', '21:00:00'],
        Carbon::WEDNESDAY => ['09:00:00', '13:00:00', '15:30:00', '21:00:00'],
        Carbon::THURSDAY  => ['09:00:00', '13:00:00', '15:30:00', '21:00:00'],
        Carbon::FRIDAY    => ['09:00:00', '13:00:00', '15:30:00', '21:00:00'],
        Carbon::SATURDAY  => ['09:00:00', '13:00:00', '14:30:00', '21:00:00'],
    ];

    private const PRODUCTS = [
        [
            'name'        => '911 – Eau De Parfum al Caffè e Fiori',
            'description' => 'Fragranza intensa e avvolgente con note di caffè e fiori. Formato 50 ml.',
            'price'       => 39.90,
            'stock'       => 10,
            'image'       => 'https://www.marinobarbershop.it/wp-content/uploads/2024/12/911-Nero-caffè-e-fiori-300x300.jpeg',
            'filename'    => '911-caffe-fiori.jpg',
        ],
        [
            'name'        => '911 – Eau de Parfum al Latte e Incenso',
            'description' => 'Profumo delicato e sofisticato con note cremose di latte e incenso. Formato 50 ml.',
            'price'       => 39.90,
            'stock'       => 10,
            'image'       => 'https://www.marinobarbershop.it/wp-content/uploads/2024/12/911-Bianco-Latte-300x300.jpeg',
            'filename'    => '911-latte-incenso.jpg',
        ],
        [
            'name'        => '911 – Eau de Parfum al Muschio di Quercia e Tabacco',
            'description' => 'Fragranza maschile profonda con note legnose di muschio di quercia e tabacco. Formato 50 ml.',
            'price'       => 39.90,
            'stock'       => 10,
            'image'       => 'https://www.marinobarbershop.it/wp-content/uploads/2024/12/911-Marrone-Quercia-e-tabacco-300x300.jpeg',
            'filename'    => '911-muschio-tabacco.jpg',
        ],
        [
            'name'        => '4711 Acqua di Colonia 200 ml',
            'description' => "Il classico senza tempo. L'originale Acqua di Colonia 4711, fresca e agrumata. Formato 200 ml.",
            'price'       => 26.00,
            'stock'       => 15,
            'image'       => 'https://www.marinobarbershop.it/wp-content/uploads/2022/01/maurer-wirtz-4711-original-eau-de-cologne-200-ml-300x300.jpg',
            'filename'    => '4711-200ml.jpg',
        ],
        [
            'name'        => '4711 Acqua di Colonia 400 ml',
            'description' => "Il classico senza tempo in formato maxi. L'originale Acqua di Colonia 4711. Formato 400 ml.",
            'price'       => 40.00,
            'stock'       => 8,
            'image'       => 'https://www.marinobarbershop.it/wp-content/uploads/2022/01/maurer-wirtz-4711-original-eau-de-cologne-200-ml-300x300.jpg',
            'filename'    => '4711-400ml.jpg',
        ],
        [
            'name'        => '4711 Sapone alla Crema Originale',
            'description' => "Il sapone da toeletta 4711 con la fragranza dell'iconico Eau de Cologne originale.",
            'price'       => 10.00,
            'stock'       => 20,
            'image'       => 'https://www.marinobarbershop.it/wp-content/uploads/2022/03/4011700740475_4-300x300.jpg',
            'filename'    => '4711-sapone.jpg',
        ],
    ];

    private const BIOS = [
        'nicola' => [
            'bio'    => 'Fondatore e titolare di Marino Barber Shop. Una passione nata da bambino, perfezionata all\'Accademia di Milano. Tornato a Gravina nel 2004 per aprire la sua bottega, oggi è anche formatore: nei suoi ragazzi cerca la stessa luce negli occhi che sapeva di avere lui.',
            'avatar' => 'https://i.pravatar.cc/400?img=57',
        ],
        'giuseppe' => [
            'bio'    => 'Diplomato alla Pascal di Matera, ha conosciuto Nicola subito dopo gli studi e non se n\'è più andato. Salone e staff sono diventati la sua famiglia, e l\'idea di accontentare ogni nuovo cliente è ciò che lo spinge a fare sempre meglio.',
            'avatar' => 'https://i.pravatar.cc/400?img=12',
        ],
        'giorgi' => [
            'bio'    => 'Amava la moda sin da bambino. Nel barbiere ha trovato un mondo che evolve continuamente — barba e capelli fanno tendenza, e le carte cambiano spessissimo. Arrivato da Nicola dopo altre esperienze, qui ha trovato squadra vera e si studia ancora.',
            'avatar' => 'https://i.pravatar.cc/400?img=33',
        ],
        'michele' => [
            'bio'    => 'Nipote di Nicola, ha iniziato quasi per gioco tra i profumi del salone da ragazzino. A 22 anni è ufficialmente parte del team. La sua specialità: prima lo studio della fisionomia del cliente, poi la consulenza sul taglio più adatto al suo viso e stile.',
            'avatar' => 'https://i.pravatar.cc/400?img=68',
        ],
    ];

    public function run(): void
    {
        $business = Business::withoutGlobalScopes()->findOrFail(self::BUSINESS_ID);
        app()->instance('current_business_id', $business->id);

        $this->seedSystemSettings();
        $this->seedSalonProfile();

        $nicola = $this->seedAdmin($business->id);
        $staff  = $this->seedStaff($business->id);

        $allStaff = array_merge(['nicola' => $nicola], $staff);

        $services = $this->seedServices();
        $this->attachServicesToStaff($services, $allStaff);
        $this->seedAvailabilityRules($allStaff);
        $this->seedBios($allStaff);
        $this->seedReviews();
        $this->seedProducts();

        $this->command->info('');
        $this->command->info('Marino Barber Shop seeded (business_id=' . $business->id . ')');
        $this->command->info('  Admin  : ' . self::ADMIN['email']);
        $this->command->info('  Staff  : ' . implode(', ', array_column(self::STAFF, 'email')));
        $this->command->warn('  Password temporanea: passwordmarino — da cambiare subito dal pannello!');
        $this->command->info('');
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
                'payment_mode'                => 'disabled',
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

    private function seedSalonProfile(): void
    {
        $profile = SalonProfile::firstOrCreate(
            ['business_id' => app('current_business_id')],
            ['name' => 'Marino Barber Shop']
        );

        $profile->update([
            'name'        => 'Marino Barber Shop',
            'tagline'     => "L'arte del barbiere tradizionale a Gravina in Puglia",
            'phone'       => '349 57 07 906',
            'address'     => 'Via Giotto, 11 - Gravina in Puglia (BA)',
            'description' => '<p><strong>Marino Barber Shop</strong> è il barbiere di riferimento a Gravina in Puglia. Nicola Marino porta avanti con passione la tradizione del barbiere italiano: taglio classico, rasatura con rasoio a mano libera e panno caldo, cura della barba con prodotti di altissima qualità.</p><p>Una passione nata da bambino tra le botteghe degli anni \'80, perfezionata all\'Accademia di Milano, portata a casa nel 2004. Oggi formiamo giovani barbieri con la stessa fame di conoscenza. Distribuiamo ufficialmente in Italia i prodotti da barba francesi <strong>Le Barbier de Famille</strong>.</p>',
            'google_maps_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3012.8!2d16.4189!3d40.8189!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1347f5c1c1c1c1c1%3A0xabcdef1234567890!2sVia+Giotto%2C+11%2C+70024+Gravina+in+Puglia+BA!5e0!3m2!1sit!2sit!4v1700000000002',
            'opening_hours' => [
                'mon' => ['type' => 'closed'],
                'tue' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:30', 'afternoon_close' => '21:00'],
                'wed' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:30', 'afternoon_close' => '21:00'],
                'thu' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:30', 'afternoon_close' => '21:00'],
                'fri' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:30', 'afternoon_close' => '21:00'],
                'sat' => ['type' => 'split', 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:30', 'afternoon_close' => '21:00'],
                'sun' => ['type' => 'closed'],
            ],
            'instagram_url'        => 'https://www.instagram.com/marino_barber_shop',
            'facebook_url'         => 'https://www.facebook.com/marinobarbershop',
            'whatsapp_number'      => '393495707906',
            'booking_button_label' => 'Prenota il tuo taglio',
            'email_greeting'       => 'Ciao {nome},',
            'email_footer_note'    => 'Grazie per aver scelto Marino Barber Shop. Ti aspettiamo a Gravina!',
            'email_accent_color'   => null,
            'owner_signature'      => "Nicola Marino\nMarino Barber Shop\nVia Giotto, 11 - Gravina in Puglia\n349 57 07 906",
            'theme'                => 'luxury',
            'theme_mode'           => 'light',
            'border_style'         => 'rounded',
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
            foreach (
                [
                    'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=800&h=600&fit=crop&q=80',
                    'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=800&h=600&fit=crop&q=80',
                    'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?w=800&h=600&fit=crop&q=80',
                    'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=800&h=600&fit=crop&q=80',
                    'https://images.unsplash.com/photo-1493256338651-d82f7acb2b38?w=800&h=600&fit=crop&q=80',
                ] as $i => $url
            ) {
                $this->addMediaSafely($profile, $url, 'gallery', "gallery_{$i}.jpg");
            }
        }
    }

    private function seedAdmin(int $businessId): User
    {
        $password = env('MARINO_ADMIN_PASSWORD', 'passwordmarino');

        $admin = User::updateOrCreate(
            ['email' => self::ADMIN['email']],
            ['name' => self::ADMIN['name'], 'password' => Hash::make($password), 'business_id' => $businessId]
        );

        $admin->syncRoles(['admin', 'staff']);
        $admin->businesses()->syncWithoutDetaching([$businessId]);

        return $admin;
    }

    /** @return array<string, User> */
    private function seedStaff(int $businessId): array
    {
        $users = [];

        foreach (self::STAFF as $key => $attrs) {
            $user = User::updateOrCreate(
                ['email' => $attrs['email']],
                ['name' => $attrs['name'], 'password' => Hash::make('passwordmarino'), 'business_id' => $businessId]
            );
            $user->syncRoles(['staff']);
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
        foreach (self::STAFF_SERVICES as $staffKey => $serviceKeys) {
            $user = $allStaff[$staffKey] ?? null;
            if (! $user) {
                continue;
            }
            foreach ($serviceKeys as $svcKey) {
                $services[$svcKey]->staff()->syncWithoutDetaching([$user->id]);
            }
        }
    }

    /** @param array<string, User> $allStaff */
    private function seedAvailabilityRules(array $allStaff): void
    {
        foreach ($allStaff as $user) {
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

    /** @param array<string, User> $allStaff */
    private function seedBios(array $allStaff): void
    {
        foreach (self::BIOS as $key => $data) {
            $user = $allStaff[$key] ?? null;
            if (! $user) {
                continue;
            }

            $user->update(['bio' => $data['bio']]);

            if ($user->getMedia('avatar')->isEmpty()) {
                $this->addMediaSafely($user, $data['avatar'], 'avatar', 'avatar.jpg');
            }
        }
    }

    private function seedReviews(): void
    {
        $reviews = [
            ['author_name' => 'Antonio V.',   'rating' => 5, 'body' => 'Nicola è semplicemente il migliore. Taglio preciso, rasatura impeccabile con il panno caldo. Non vado da nessun altro da anni.', 'sort_order' => 1],
            ['author_name' => 'Giuseppe M.',  'rating' => 5, 'body' => 'Professionalità al massimo. I prodotti Le Barbier de Famille sono una scoperta. Barba perfetta ogni volta.', 'sort_order' => 2],
            ['author_name' => 'Francesco P.', 'rating' => 5, 'body' => 'Giorgi mi ha fatto un taglio fantastico. Team giovane, preparato e appassionato. Ambiente curato e rilassante. Consigliato a tutti.', 'sort_order' => 3],
            ['author_name' => 'Salvatore D.', 'rating' => 5, 'body' => 'Da 13 anni Nicola è il mio punto di riferimento. Rasatura tradizionale con la schiuma calda, come una volta. Impeccabile.', 'sort_order' => 4],
            ['author_name' => 'Michele R.',   'rating' => 4, 'body' => 'Ottimo servizio, prezzi onestissimi. Giuseppe sa ascoltare quello che vuoi e il risultato è sempre quello giusto.', 'sort_order' => 5],
        ];

        foreach ($reviews as $data) {
            SalonReview::firstOrCreate(
                ['author_name' => $data['author_name'], 'business_id' => app('current_business_id')],
                [...$data, 'is_published' => true, 'seen_at' => now()]
            );
        }
    }

    private function seedProducts(): void
    {
        foreach (self::PRODUCTS as $data) {
            $product = Product::firstOrCreate(
                ['name' => $data['name'], 'business_id' => app('current_business_id')],
                [
                    'description' => $data['description'],
                    'price'       => $data['price'],
                    'stock'       => $data['stock'],
                    'in_sale'     => true,
                    'active'      => true,
                ]
            );

            if ($product->getMedia('photo')->isEmpty()) {
                $this->addMediaSafely($product, $data['image'], 'photo', $data['filename']);
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
        }
    }
}
