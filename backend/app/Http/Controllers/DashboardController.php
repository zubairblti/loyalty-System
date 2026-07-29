<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointsLedger;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $id = $request->user()->business_id;

        return [
            'metrics' => [
                'customers' => Customer::where('business_id', $id)->count(),
                'orders' => Order::where('business_id', $id)->count(),
                'revenue' => (float) Order::where('business_id', $id)->where('status', 'paid')->sum('total'),
                'points_issued' => (int) PointsLedger::where('business_id', $id)->where('points', '>', 0)->sum('points'),
            ],
            'recent_orders' => Order::with('customer')->where('business_id', $id)->latest()->limit(8)->get(),
        ];
    }
}
