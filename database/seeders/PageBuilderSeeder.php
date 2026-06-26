<?php

namespace Database\Seeders;

use App\Models\PageTemplate;
use App\Models\PageTemplateBlock;
use App\PageBlocks\PageBlockRegistry;
use Illuminate\Database\Seeder;

class PageBuilderSeeder extends Seeder
{
    public function run(): void
    {
        $this->createTemplate('default', 'Default', 'Template standard per tutti i saloni.', true, $this->defaultBlocks());
        $this->createTemplate('minimal', 'Minimal', 'Layout essenziale e pulito.', false, $this->minimalBlocks());
        $this->createTemplate('premium', 'Premium / Luxury', 'Layout elegante ad alto impatto visivo.', false, $this->premiumBlocks());
    }

    private function createTemplate(string $slug, string $name, string $description, bool $isDefault, array $blocks): void
    {
        $template = PageTemplate::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => $description, 'is_active' => true, 'is_default' => $isDefault]
        );

        if ($template->pageTemplateBlocks()->count() === 0) {
            foreach ($blocks as $i => $blockDef) {
                $blockClass = PageBlockRegistry::find($blockDef['block_type']);
                $template->pageTemplateBlocks()->create([
                    'block_type'     => $blockDef['block_type'],
                    'variant'        => $blockDef['variant'],
                    'sort_order'     => ($i + 1) * 10,
                    'is_enabled'     => true,
                    'is_required'    => $blockDef['is_required'] ?? false,
                    'is_locked'      => false,
                    'content'        => $blockClass ? $blockClass::defaultContent() : [],
                    'settings'       => array_merge(
                        $blockClass ? $blockClass::defaultSettings() : [],
                        $blockDef['settings'] ?? []
                    ),
                    'schema_version' => 1,
                ]);
            }
        }
    }

    private function defaultBlocks(): array
    {
        return [
            ['block_type' => 'hero',         'variant' => 'classic',    'is_required' => true],
            ['block_type' => 'services',     'variant' => 'grid_cards'],
            ['block_type' => 'about',        'variant' => 'centered'],
            ['block_type' => 'staff',        'variant' => 'cards'],
            ['block_type' => 'gallery',      'variant' => 'grid_3col'],
            ['block_type' => 'contact_info', 'variant' => 'with_map',   'is_required' => true],
            ['block_type' => 'reviews',      'variant' => 'cards'],
            ['block_type' => 'faq',          'variant' => 'accordion'],
            ['block_type' => 'cta',          'variant' => 'simple'],
            ['block_type' => 'map',          'variant' => 'full_width'],
        ];
    }

    private function minimalBlocks(): array
    {
        return [
            ['block_type' => 'hero',         'variant' => 'centered',   'is_required' => true],
            ['block_type' => 'services',     'variant' => 'compact_list'],
            ['block_type' => 'contact_info', 'variant' => 'simple',     'is_required' => true],
            ['block_type' => 'cta',          'variant' => 'simple'],
        ];
    }

    private function premiumBlocks(): array
    {
        return [
            ['block_type' => 'hero',         'variant' => 'editorial',  'is_required' => true],
            ['block_type' => 'about',        'variant' => 'split_image'],
            ['block_type' => 'services',     'variant' => 'price_list'],
            ['block_type' => 'staff',        'variant' => 'editorial'],
            ['block_type' => 'gallery',      'variant' => 'masonry'],
            ['block_type' => 'reviews',      'variant' => 'carousel'],
            ['block_type' => 'contact_info', 'variant' => 'with_map',   'is_required' => true],
        ];
    }
}
