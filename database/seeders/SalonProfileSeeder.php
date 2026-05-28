<?php

namespace Database\Seeders;

use App\Models\SalonProfile;
use App\Models\SalonReview;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalonProfileSeeder extends Seeder
{
    public function run(string $salonKey = 'rossini'): void
    {
        $this->seedProfile($salonKey);
        $this->seedReviews($salonKey);
        $this->seedStaffBios($salonKey);
    }

    private function seedProfile(string $salonKey): void
    {
        $profile = SalonProfile::current();

        $profile->update($this->getProfileData($salonKey));

        $coverUrl = $salonKey === 'chic'
            ? 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1920&h=640&fit=crop&q=80'
            : 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=1920&h=640&fit=crop&q=80';

        $logoUrl = $salonKey === 'chic'
            ? 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=200&h=200&fit=crop&q=80'
            : 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=200&h=200&fit=crop&q=80';

        $this->addMediaSafely($profile, $coverUrl, 'cover', 'cover.jpg');
        $this->addMediaSafely($profile, $logoUrl,  'logo',  'logo.jpg');

        if ($profile->getMedia('gallery')->isEmpty()) {
            foreach ($this->getGalleryUrls($salonKey) as $i => $url) {
                $this->addMediaSafely($profile, $url, 'gallery', "gallery_{$i}.jpg");
            }
        }
    }

    private function getProfileData(string $salonKey): array
    {
        if ($salonKey === 'chic') {
            return [
                'name'          => 'Chic Beauty Studio',
                'tagline'       => 'Bellezza su misura, ogni giorno',
                'primary_color' => '#c084a0',
                'phone'         => '+39 02 9876 5432',
                'address'       => 'Corso Buenos Aires 42, 20124 Milano',
                'website'       => 'https://chicbeautystudio.it',
                'description'   => '<p><strong>Chic Beauty Studio</strong> è il tuo rifugio di bellezza nel cuore di Milano. Specializzate in colorazione e styling, le nostre professioniste ti guidano verso il look che hai sempre desiderato.</p><p>Dall\'highlights alle tinte personalizzate, dalla piega perfetta al taglio su misura: ogni servizio è pensato per valorizzare la tua unicità.</p>',
                'cancellation_policy' => '<p>Le prenotazioni possono essere cancellate fino a <strong>24 ore prima</strong> dell\'appuntamento.</p>',
                'google_maps_embed'   => null,
                'opening_hours' => [
                    'mon' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:30', 'afternoon_close' => '19:00'],
                    'tue' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:30', 'afternoon_close' => '19:00'],
                    'wed' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:30', 'afternoon_close' => '19:00'],
                    'thu' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:30', 'afternoon_close' => '19:00'],
                    'fri' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:30', 'afternoon_close' => '20:00'],
                    'sat' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '17:00', 'afternoon_open' => '14:30', 'afternoon_close' => '17:00'],
                    'sun' => ['open' => false, 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:30', 'afternoon_close' => '19:00'],
                ],
                'instagram_url'   => 'https://instagram.com/chicbeautystudio',
                'facebook_url'    => null,
                'tiktok_url'      => null,
                'whatsapp_number' => '390298765432',
            ];
        }

        return [
            'name'          => 'Rossini Barbershop',
            'tagline'       => "L'arte del taglio perfetto dal 2008",
            'primary_color' => '#1d1d1d',
            'phone'         => '+39 02 8765 4321',
            'address'       => 'Via Brera 14, 20121 Milano',
            'website'       => 'https://rossinibarbershop.it',
            'description'   => '<p>Da oltre 15 anni, <strong>Rossini Barbershop</strong> è il punto di riferimento per chi vuole un taglio impeccabile nel cuore di Milano. Il nostro team di professionisti unisce tecniche tradizionali da barbiere con le tendenze più moderne, per un risultato che valorizza ogni tipo di capello e barba.</p><p>Ogni visita è un momento di cura dedicato a te: dalla rasatura con rasoio a mano libera all\'asciugamano caldo, fino alla modellatura della barba più complessa. Niente fretta, solo qualità.</p>',
            'cancellation_policy' => '<p>Le prenotazioni possono essere cancellate fino a <strong>24 ore prima</strong> dell\'appuntamento. Passata questa finestra, la cancellazione non è più possibile tramite il portale — per emergenze contattaci direttamente al telefono (<a href="tel:+390287654321">+390287654321</a>).</p><p>Se hai pagato online al momento della prenotazione, il rimborso viene elaborato automaticamente sul metodo di pagamento originale non appena la cancellazione viene confermata. I tempi di accredito dipendono dalla tua banca (di solito 3–5 giorni lavorativi). Nessun rimborso è previsto per cancellazioni effettuate a meno di 24 ore dall\'appuntamento.</p>',
            'google_maps_embed'   => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2797.849513706764!2d9.1846!3d45.4720!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4786c6aef34c60a5%3A0x8a79d3e0a4c84fa9!2sVia+Brera%2C+14%2C+20121+Milano!5e0!3m2!1sit!2sit!4v1700000000000',
            'opening_hours' => [
                'mon' => ['open' => false, 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                'tue' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                'wed' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                'thu' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
                'fri' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '20:00'],
                'sat' => ['open' => true,  'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '14:00', 'afternoon_close' => '18:00'],
                'sun' => ['open' => false, 'morning_open' => '09:00', 'morning_close' => '13:00', 'afternoon_open' => '15:00', 'afternoon_close' => '19:30'],
            ],
            'instagram_url'   => 'https://instagram.com/rossinibarbershop',
            'facebook_url'    => 'https://facebook.com/rossinibarbershop',
            'tiktok_url'      => null,
            'whatsapp_number' => '390287654321',
        ];
    }

    private function getGalleryUrls(string $salonKey): array
    {
        if ($salonKey === 'chic') {
            return [
                'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&h=600&fit=crop&q=80',
                'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=800&h=600&fit=crop&q=80',
                'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=800&h=600&fit=crop&q=80',
            ];
        }

        return [
            'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=800&h=600&fit=crop&q=80',
            'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=800&h=600&fit=crop&q=80',
            'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?w=800&h=600&fit=crop&q=80',
            'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=800&h=600&fit=crop&q=80',
            'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=800&h=600&fit=crop&q=80',
        ];
    }

    private function seedReviews(string $salonKey): void
    {
        $reviews = $salonKey === 'chic' ? [
            ['author_name' => 'Chiara M.',    'rating' => 5, 'body' => 'Sofia è un\'artista del colore. Ho finalmente la tinta che sognavo da anni. Studio accogliente e personale molto professionale.', 'sort_order' => 1],
            ['author_name' => 'Francesca R.', 'rating' => 5, 'body' => 'Taglio e piega perfetti. Elena capisce esattamente quello che vuoi. Tornerò sicuramente!', 'sort_order' => 2],
            ['author_name' => 'Valentina B.', 'rating' => 4, 'body' => 'Ottimo trattamento ristrutturante, capelli finalmente morbidissimi. Ambiente curato e rilassante.', 'sort_order' => 3],
        ] : [
            ['author_name' => 'Matteo C.',    'rating' => 5, 'body' => 'Taglio perfetto come sempre. Marco è un artista, sa esattamente cosa vuoi anche quando non riesci a spiegarlo bene. Ambiente curato e rilassante. Ci torno ogni tre settimane.', 'sort_order' => 1],
            ['author_name' => 'Luca F.',      'rating' => 5, 'body' => 'Finalmente un posto serio a Milano. Barba impeccabile, rasatura con il rasoio a mano libera fenomenale. Asciugamano caldo e prodotti di qualità. Non andrò mai più da nessun altro.', 'sort_order' => 2],
            ['author_name' => 'Davide R.',    'rating' => 5, 'body' => 'Ho provato tanti barbieri ma Rossini è un altro livello. Andrea è preciso, veloce e sa il fatto suo. Staff gentile e professionale. Prezzi più che onesti per la qualità offerta.', 'sort_order' => 3],
            ['author_name' => 'Alessandro M.','rating' => 4, 'body' => 'Ottimo barbiere, ottima location nel cuore di Brera. Ho preso la barba rimodellata da Filippo e sono uscito soddisfatissimo. Unico neo: a volte l\'attesa è un po\' lunga, ma ne vale assolutamente la pena.', 'sort_order' => 4],
            ['author_name' => 'Simone B.',    'rating' => 5, 'body' => 'Il migliore in zona senza dubbio. Sistema di prenotazione online comodissimo, staff puntuale e preciso. Non ci sono più scuse per non presentarsi con un taglio decente. 5 stelle meritatissime.', 'sort_order' => 5],
        ];

        foreach ($reviews as $data) {
            SalonReview::updateOrCreate(
                ['author_name' => $data['author_name']],
                [...$data, 'is_published' => true]
            );
        }
    }

    private function seedStaffBios(string $salonKey): void
    {
        $staffData = $salonKey === 'chic' ? [
            'sofia@chic.test' => [
                'bio'    => 'Colorista con 10 anni di esperienza, specializzata in balayage e tecniche di schiariture. Ha perfezionato la sua tecnica tra Parigi e Milano.',
                'avatar' => 'https://i.pravatar.cc/400?img=47',
            ],
            'elena@chic.test' => [
                'bio'    => 'Stilista versatile con una passione per i tagli donna e la piega perfetta. Ogni cliente è un progetto unico.',
                'avatar' => 'https://i.pravatar.cc/400?img=25',
            ],
        ] : [
            'marco@rossini.test'   => [
                'bio'    => 'Barbiere con 12 anni di esperienza, specializzato in tagli classici e moderni. Amante del rasoio a mano libera e della grande tradizione del barbiere italiano.',
                'avatar' => 'https://i.pravatar.cc/400?img=12',
            ],
            'andrea@rossini.test'  => [
                'bio'    => 'Esperto in barba e rasatura tradizionale. Ha perfezionato la sua tecnica tra Londra e Barcellona prima di tornare a Milano. Ogni visita è una consulenza.',
                'avatar' => 'https://i.pravatar.cc/400?img=33',
            ],
            'filippo@rossini.test' => [
                'bio'    => 'Il più versatile del team: dai tagli urban alle colorazioni più audaci. Filippo trasforma ogni appuntamento in una sessione di styling su misura.',
                'avatar' => 'https://i.pravatar.cc/400?img=57',
            ],
        ];

        foreach ($staffData as $email => $data) {
            $user = User::withoutGlobalScopes()->where('email', $email)->first();
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
}
