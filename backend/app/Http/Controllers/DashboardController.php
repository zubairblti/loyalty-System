<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointsLedger;
use App\Models\CustomerMembership;
use App\Models\Subscription;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $id = $request->user()->business_id;

        return [
            'metrics' => [
                'customers' => Customer::where('business_id', $id)->count(),
                'orders' => Order::where('business_id', $id)->where('status', 'paid')->count(),
                'revenue' => (float) Order::where('business_id', $id)->where('status', 'paid')->sum('total'),
                'points_issued' => (int) PointsLedger::where('business_id', $id)->where('points', '>', 0)->sum('points'),
                'memberships' => CustomerMembership::where('business_id', $id)->whereNull('ended_at')->count(),
                'active_subscription' => Subscription::where('business_id', $id)->where('status', 'active')->where('ends_at', '>', now())->exists(),
                'today_activity' => Order::where('business_id', $id)->whereDate('created_at', today())->count(),
            ],
            'recent_orders' => Order::with('customer')->where('business_id', $id)->latest()->limit(8)->get(),
            'charts' => $this->charts($id),
        ];
    }

    private function charts(int $businessId): array
    {
        $months = collect(range(5, 0))->map(fn ($offset) => now()->startOfMonth()->subMonths($offset));

        return [
            'monthly' => $months->map(fn ($month) => [
                'label' => $month->format('M Y'),
                'revenue' => (float) Order::where('business_id', $businessId)->where('status', 'paid')
                    ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->sum('total'),
                'orders' => Order::where('business_id', $businessId)->where('status', 'paid')
                    ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count(),
                'customers' => Customer::where('business_id', $businessId)
                    ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count(),
            ])->values(),
        ];
    }
}
