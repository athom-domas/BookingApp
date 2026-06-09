<?php

namespace App\Filament\Widgets\Reports;

use App\Models\ProductOrderItem;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ProductBreakdownChartWidget extends ChartWidget
{
    protected ?string $heading     = 'Prodotti più venduti';
    protected static bool $isLazy  = false;
    protected static ?int $sort    = 8;
    protected int | string | array $columnSpan = 1;

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    protected function getData(): array
    {
        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to   = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString())->endOfDay();

        $rows = ProductOrderItem::join('product_orders', 'product_orders.id', '=', 'product_order_items.order_id')
            ->join('products', 'products.id', '=', 'product_order_items.product_id')
            ->whereBetween('product_orders.created_at', [$from, $to])
            ->whereNotIn('product_orders.status', ['cancelled', 'pending'])
            ->select('products.name', DB::raw('SUM(product_order_items.quantity) as qty'))
            ->groupBy('products.name')
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        $palette = [
            'rgba(99,102,241,0.85)',
            'rgba(244,63,94,0.85)',
            'rgba(16,185,129,0.85)',
            'rgba(245,158,11,0.85)',
            'rgba(139,92,246,0.85)',
            'rgba(14,165,233,0.85)',
            'rgba(251,146,60,0.85)',
            'rgba(20,184,166,0.85)',
        ];

        $labels   = $rows->pluck('name')->toArray();
        $data     = $rows->pluck('qty')->map(fn ($v) => (int) $v)->toArray();
        $bgColors = array_map(fn ($i) => $palette[$i % count($palette)], range(0, max(0, count($data) - 1)));

        return [
            'datasets' => [[
                'data'            => $data,
                'backgroundColor' => $bgColors,
                'borderWidth'     => 0,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout'  => '60%',
            'plugins' => [
                'legend' => ['position' => 'right', 'labels' => ['boxWidth' => 10, 'padding' => 10]],
            ],
        ];
    }
}
