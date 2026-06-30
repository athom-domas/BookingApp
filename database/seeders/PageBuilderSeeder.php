<?php

namespace Database\Seeders;

use App\Models\BlockDefault;
use App\Models\BusinessPageBlock;
use App\PageBlocks\PageBlockRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class PageBuilderSeeder extends Seeder
{
    private const BLOCKS = [
        ['block_type' => 'hero',         'variant' => 'classic',    'is_required' => true,  'is_enabled' => true],
        ['block_type' => 'services',     'variant' => 'grid_cards', 'is_required' => false, 'is_enabled' => true],
        ['block_type' => 'about',        'variant' => 'centered',   'is_required' => false, 'is_enabled' => true],
        ['block_type' => 'staff',        'variant' => 'cards',      'is_required' => false, 'is_enabled' => true],
        ['block_type' => 'gallery',      'variant' => 'grid_3col',  'is_required' => false, 'is_enabled' => true],
        ['block_type' => 'contact_info', 'variant' => 'with_map',   'is_required' => true,  'is_enabled' => true],
        ['block_type' => 'reviews',      'variant' => 'cards',      'is_required' => false, 'is_enabled' => false],
        ['block_type' => 'faq',          'variant' => 'accordion',  'is_required' => false, 'is_enabled' => false],
        ['block_type' => 'cta',          'variant' => 'simple',     'is_required' => false, 'is_enabled' => false],
        ['block_type' => 'map',          'variant' => 'full_width', 'is_required' => false, 'is_enabled' => false],
    ];

    public function run(): void
    {
        foreach (self::BLOCKS as $i => $def) {
            $blockClass = PageBlockRegistry::find($def['block_type']);

            BlockDefault::updateOrCreate(
                ['block_type' => $def['block_type']],
                [
                    'variant'        => $def['variant'],
                    'sort_order'     => ($i + 1) * 10,
                    'is_enabled'     => $def['is_enabled'],
                    'is_required'    => $def['is_required'],
                    'is_locked'      => false,
                    'content'        => $blockClass ? $blockClass::defaultContent() : [],
                    'settings'       => $blockClass ? $blockClass::defaultSettings() : [],
                    'schema_version' => 1,
                ]
            );
        }

        Artisan::call('page-builder:init');

        if (app()->bound('current_business_id')) {
            $this->seedRossiniBlockContent((int) app('current_business_id'));
        }
    }

    private function seedRossiniBlockContent(int $businessId): void
    {
        $blocks = [
            'hero' => [
                'variant' => 'editorial',
                'content' => [
                    'title'     => 'Rossini Barbershop',
                    'subtitle'  => "L'arte del taglio perfetto dal 2008",
                    'cta_label' => 'Prenota ora',
                    'image'     => null,
                ],
                'settings'   => ['show_cta' => true, 'image_preset' => 'barber'],
                'is_enabled' => true,
            ],
            'services' => [
                'variant' => 'grid_cards',
                'content' => [
                    'title'    => 'I nostri servizi',
                    'subtitle' => 'Taglio, rasatura e cura della barba: ogni servizio pensato per valorizzarti.',
                ],
                'settings'   => ['show_prices' => true, 'show_duration' => true, 'featured_only' => false],
                'is_enabled' => true,
            ],
            'about' => [
                'variant' => 'split_image',
                'content' => [
                    'title'           => 'Il Salone',
                    'body'            => '<p>Da oltre 15 anni, <strong>Rossini Barbershop</strong> è il punto di riferimento per chi vuole un taglio impeccabile nel cuore di Milano. Il nostro team unisce tecniche tradizionali da barbiere con le tendenze più moderne.</p><p>Dalla rasatura con rasoio a mano libera all\'asciugamano caldo: ogni visita è un\'esperienza di cura dedicata a te.</p>',
                    'images'          => [],
                    'owner_signature' => 'Marco Rossini',
                ],
                'settings'   => [],
                'is_enabled' => true,
            ],
            'staff' => [
                'variant' => 'cards',
                'content' => [
                    'title'    => 'Il nostro team',
                    'subtitle' => 'Professionisti appassionati, sempre al tuo servizio.',
                ],
                'settings'   => [],
                'is_enabled' => true,
            ],
            'gallery' => [
                'variant'    => 'grid_3col',
                'content'    => ['title' => 'Galleria', 'subtitle' => 'I nostri lavori', 'images' => []],
                'settings'   => [],
                'is_enabled' => false,
            ],
            'contact_info' => [
                'variant' => 'with_map',
                'content' => [
                    'title'    => 'Orari e contatti',
                    'subtitle' => 'Siamo in Via Brera 14, Milano',
                ],
                'settings'   => ['show_phone' => true, 'show_address' => true, 'show_hours' => true, 'contacts_position' => 'right'],
                'is_enabled' => true,
            ],
            'reviews' => [
                'variant' => 'cards',
                'content' => [
                    'title'    => 'Cosa dicono di noi',
                    'subtitle' => 'Le recensioni dei nostri clienti',
                ],
                'settings'   => [],
                'is_enabled' => true,
            ],
            'faq' => [
                'variant' => 'accordion',
                'content' => [
                    'title' => 'Domande frequenti',
                    'items' => [
                        ['question' => 'Devo prenotare in anticipo?',          'answer' => 'Sì, consigliamo di prenotare almeno 2-3 giorni prima, specialmente nel weekend. Puoi prenotare comodamente online 24/7.'],
                        ['question' => 'Quanto dura un taglio classico?',       'answer' => 'Un taglio classico richiede circa 20-30 minuti. La rasatura completa con asciugamano caldo circa 30-40 minuti.'],
                        ['question' => 'Posso venire senza prenotazione?',      'answer' => 'Accettiamo anche i walk-in se c\'è disponibilità, ma per garantirti un posto ti consigliamo la prenotazione online.'],
                        ['question' => 'Quali metodi di pagamento accettate?', 'answer' => 'Accettiamo contanti, carta di credito/debito e pagamenti via Satispay.'],
                    ],
                ],
                'settings'   => [],
                'is_enabled' => true,
            ],
            'cta' => [
                'variant' => 'simple',
                'content' => [
                    'title'        => 'Pronto per il tuo prossimo taglio?',
                    'subtitle'     => 'Prenota in pochi click, senza telefonate.',
                    'button_label' => 'Prenota ora',
                    'image'        => null,
                ],
                'settings'   => ['alignment' => 'center'],
                'is_enabled' => true,
            ],
            'map' => [
                'variant'    => 'contained',
                'content'    => ['title' => 'Dove siamo'],
                'settings'   => ['height' => 'md', 'show_directions_link' => true],
                'is_enabled' => false,
            ],
        ];

        foreach ($blocks as $blockType => $data) {
            BusinessPageBlock::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('block_type', $blockType)
                ->update([
                    'variant'    => $data['variant'],
                    'content'    => $data['content'],
                    'settings'   => $data['settings'],
                    'is_enabled' => $data['is_enabled'],
                ]);
        }
    }
}
