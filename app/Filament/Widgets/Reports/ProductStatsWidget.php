<?php

namespace App\Filament\Widgets\Reports;

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ProductStatsWidget extends Widget
{
    protected string $view                     = 'filament.widgets.reports.product-stats';
    protected static ?int $sort                = 7;
    protected static bool $isLazy              = false;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    public function getStats(): array
    {
        $from = \Carbon\Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to   = \Carbon\Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString())->endOfDay();

        $ordersQuery = ProductOrder::whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'pending']);

        $orderCount = $ordersQuery->count();

        $revenue = ProductOrderItem::join('product_orders', 'product_orders.id', '=', 'product_order_items.order_id')
            ->whereBetween('product_orders.created_at', [$from, $to])
            ->whereNotIn('product_orders.status', ['cancelled', 'pending'])
            ->sum(DB::raw('product_order_items.unit_price * product_order_items.quantity'));

        $topProductRow = ProductOrderItem::join('product_orders', 'product_orders.id', '=', 'product_order_items.order_id')
            ->join('products', 'products.id', '=', 'product_order_items.product_id')
            ->whereBetween('product_orders.created_at', [$from, $to])
            ->whereNotIn('product_orders.status', ['cancelled', 'pending'])
            ->select('product_order_items.product_id', 'products.name', DB::raw('SUM(product_order_items.quantity) as qty'))
            ->groupBy('product_order_items.product_id', 'products.name')
            ->orderByDesc('qty')
            ->first();

        return [
            'orderCount'     => (int) $orderCount,
            'revenue'        => (float) $revenue,
            'topProductName' => $topProductRow?->name ?? '—',
            'topProductQty'  => (int) ($topProductRow?->qty ?? 0),
        ];
    }
}
