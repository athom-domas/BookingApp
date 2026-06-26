<?php

namespace App\View\Components;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\PageBlocks\PageBlockRegistry;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;

class PageBlock extends Component
{
    public readonly ?string $blockClass;
    public readonly string $resolvedVariant;
    public readonly array $blockData;

    public function __construct(
        public readonly Business $business,
        public readonly BusinessPageBlock $block,
    ) {
        $this->blockClass = PageBlockRegistry::find($block->block_type);

        if ($this->blockClass === null) {
            Log::warning('PageBlock: unknown block type', [
                'block_id'   => $block->id,
                'block_type' => $block->block_type,
            ]);
            $this->resolvedVariant = '';
            $this->blockData = [];

            return;
        }

        if (PageBlockRegistry::isValidVariant($block->block_type, $block->variant)) {
            $this->resolvedVariant = $block->variant;
        } else {
            $fallback = PageBlockRegistry::defaultVariant($block->block_type);
            Log::warning('PageBlock: invalid variant, falling back', [
                'block_id'        => $block->id,
                'old_variant'     => $block->variant,
                'fallback_variant'=> $fallback,
            ]);
            $this->resolvedVariant = $fallback;
        }

        $this->blockData = ($this->blockClass)::resolveData($business, $block);
    }

    public function render(): View|Closure|string
    {
        return view('components.page-block');
    }
}
