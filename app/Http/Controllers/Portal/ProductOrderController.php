<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = ProductOrder::where('user_id', $request->user()->id)
            ->with('items.product')
            ->latest()
            ->get();

        return view('portal.orders.index', compact('orders'));
    }
}
