<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OrderPrintController extends Controller
{
    public function print(Request $request)
{
    $range = $request->range;
    $query = Order::query();

    // Filter by name if available
    if ($request->filled('name')) {
        $query->where('name', 'like', '%' . $request->name . '%');
    }

    // Filter by date range
    switch ($range) {
        case 'today':
            $query->whereDate('created_at', \Carbon\Carbon::today());
            break;
        case 'this_week':
            $query->whereBetween('created_at', [
                \Carbon\Carbon::now()->startOfWeek(),
                \Carbon\Carbon::now()->endOfWeek()
            ]);
            break;
        case 'this_month':
            $query->whereMonth('created_at', \Carbon\Carbon::now()->month)
                  ->whereYear('created_at', \Carbon\Carbon::now()->year);
            break;
        case 'custom':
            if ($request->start && $request->end) {
                $query->whereBetween('created_at', [
                    \Carbon\Carbon::parse($request->start)->startOfDay(),
                    \Carbon\Carbon::parse($request->end)->endOfDay(),
                ]);
            }
            break;
    }

    $orders = $query->with(['sizeModel', 'user'])->get();

    return view('orders.print', compact('orders', 'range'));
}

}
