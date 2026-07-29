<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaidOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PosTerminal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function terminals(Request $request)
    {
        return PosTerminal::where('business_id', $request->user()->business_id)->get();
    }

    public function createTerminal(Request $request)
    {
        $business = $request->user()->business->load('plan');
        abort_if(PosTerminal::where('business_id', $business->id)->count() >= $business->plan->terminal_limit, 422, 'Plan terminal limit reached.');
        $data = $request->validate(['name' => ['required', 'max:100'], 'branch' => ['nullable', 'max:100']]);
        $secret = Str::random(48);
        $terminal = PosTerminal::create([...$data, 'business_id' => $business->id, 'terminal_key' => 'pos_'.Str::random(24), 'secret' => $secret]);

        return response()->json(['terminal' => $terminal, 'secret' => $secret], 201);
    }

    public function sale(Request $request)
    {
        $data = $request->validate([
            'terminal_id' => ['required', 'integer'], 'total' => ['required', 'numeric', 'min:1'],
            'phone' => ['nullable', 'string', 'max:30'], 'customer_name' => ['nullable', 'max:100'],
            'payment_method' => ['required', 'in:cash,card,jazzcash,easypaisa'],
        ]);
        $businessId = $request->user()->business_id;
        $terminal = PosTerminal::where('business_id', $businessId)->findOrFail($data['terminal_id']);
        $customer = empty($data['phone']) ? null : Customer::firstOrCreate(
            ['business_id' => $businessId, 'phone' => $data['phone']],
            ['name' => $data['customer_name'] ?? null],
        );
        $order = Order::create([
            'business_id' => $businessId, 'customer_id' => $customer?->id, 'pos_terminal_id' => $terminal->id,
            'source' => 'mini_pos', 'external_id' => 'POS-'.Str::upper(Str::random(10)), 'total' => $data['total'],
            'payment_method' => $data['payment_method'], 'status' => 'paid', 'paid_at' => now(),
        ]);
        if ($customer) {
            ProcessPaidOrder::dispatch($order->id);
        }

        return response()->json($order->load('customer'), 201);
    }
}
