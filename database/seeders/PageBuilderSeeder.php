<?php

namespace Database\Seeders;

use App\Models\BlockDefault;
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
    }
}
