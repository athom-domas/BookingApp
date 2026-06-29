<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\PageTemplate;
use App\Models\SalonProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PageBuilderInit extends Command
{
    protected $signature   = 'page-builder:init {--business= : ID of a single business to initialize} {--force : Re-initialize businesses that already have blocks}';
    protected $description = 'Initialize page builder blocks for businesses using the Default template snapshot.';

    public function handle(): int
    {
        $defaultTemplate = PageTemplate::where('is_default', true)->with('pageTemplateBlocks')->first();

        if (! $defaultTemplate) {
            $this->error('No default template found. Run: php artisan db:seed --class=PageBuilderSeeder');
            return self::FAILURE;
        }

        $query = Business::query();

        if ($this->option('business')) {
            $query->where('id', $this->option('business'));
        }

        if (! $this->option('force')) {
            $initializedIds = BusinessPageBlock::withoutGlobalScopes()
                ->select('business_id')
                ->distinct()
                ->pluck('business_id');

            $query->whereNotIn('id', $initializedIds);
        } else {
            if (! $this->confirm('--force will delete and recreate blocks for all targeted businesses. Continue?')) {
                return self::SUCCESS;
            }
        }

        $businesses = $query->get();

        if ($businesses->isEmpty()) {
            $this->info('No businesses to initialize.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($businesses->count());
        $bar->start();

        foreach ($businesses as $business) {
            try {
                DB::transaction(function () use ($business, $defaultTemplate) {
                    if ($this->option('force')) {
                        BusinessPageBlock::withoutGlobalScopes()
                            ->where('business_id', $business->id)
                            ->delete();
                    }

                    foreach ($defaultTemplate->pageTemplateBlocks as $templateBlock) {
                        BusinessPageBlock::withoutGlobalScopes()->create([
                            'business_id'            => $business->id,
                            'page_template_id'       => $defaultTemplate->id,
                            'page_template_block_id' => $templateBlock->id,
                            'block_type'             => $templateBlock->block_type,
                            'variant'                => $templateBlock->variant,
                            'sort_order'             => $templateBlock->sort_order,
                            'is_enabled'             => $templateBlock->is_enabled,
                            'is_required'            => $templateBlock->is_required,
                            'is_locked'              => $templateBlock->is_locked,
                            'content'                => $templateBlock->content,
                            'settings'               => $templateBlock->settings,
                            'schema_version'         => $templateBlock->schema_version,
                        ]);
                    }

                    SalonProfile::withoutGlobalScopes()
                        ->where('business_id', $business->id)
                        ->update(['page_template_id' => $defaultTemplate->id]);
                });
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Failed for business {$business->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Initialized {$businesses->count()} business(es).");

        return self::SUCCESS;
    }
}
