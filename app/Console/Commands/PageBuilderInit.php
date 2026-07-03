<?php

namespace App\Console\Commands;

use App\Models\BlockDefault;
use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\PageBlocks\PageBlockRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PageBuilderInit extends Command
{
    protected $signature   = 'page-builder:init {--business= : ID of a single business to initialize} {--force : Re-initialize businesses that already have blocks}';
    protected $description = 'Initialize page builder blocks for all businesses from block_defaults.';

    public function handle(): int
    {
        $defaults = BlockDefault::orderBy('sort_order')->get();

        if ($defaults->isEmpty()) {
            $this->error('No block defaults found. Run: php artisan db:seed --class=PageBuilderSeeder');
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
                DB::transaction(function () use ($business, $defaults): void {
                    if ($this->option('force')) {
                        BusinessPageBlock::withoutGlobalScopes()
                            ->where('business_id', $business->id)
                            ->delete();
                    }

                    foreach ($defaults as $def) {
                        BusinessPageBlock::withoutGlobalScopes()->create([
                            'business_id'    => $business->id,
                            'block_type'     => $def->block_type,
                            'variant'        => $def->variant,
                            'sort_order'     => $def->sort_order,
                            'is_enabled'     => $def->is_enabled,
                            'is_required'    => $def->is_required,
                            'is_locked'      => $def->is_locked,
                            'content'        => $def->content ?? [],
                            'settings'       => $def->settings ?? [],
                            'schema_version' => $def->schema_version,
                        ]);
                    }
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
