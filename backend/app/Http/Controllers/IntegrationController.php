<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaidOrder;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Integration;
use App\Models\Order;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        $id = $request->user()->business_id;

        return ['domains' => Domain::where('business_id', $id)->get(), 'integrations' => Integration::where('business_id', $id)->get()];
    }

    public function storeDomain(Request $request)
    {
        $business = $request->user()->business->load('plan');
        abort_if(Domain::where('business_id', $business->id)->count() >= $business->plan->domain_limit, 422, 'Plan domain limit reached.');
        $host = strtolower(parse_url($request->validate(['url' => ['required', 'url']])['url'], PHP_URL_HOST));

        return Domain::firstOrCreate(['business_id' => $business->id, 'host' => $host], ['verification_token' => 'loyalty-verify='.Str::random(32)]);
    }

    public function createKey(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'max:100'], 'domain_id' => ['nullable', 'integer']]);
        $secret = Str::random(48);
        $integration = Integration::create([...$data, 'business_id' => $request->user()->business_id, 'public_key' => 'lk_'.Str::random(24), 'secret' => $secret]);

        return response()->json(['integration' => $integration, 'secret' => $secret], 201);
    }

    public function ingest(Request $request)
    {
        $integration = $request->attributes->get('integration');
        $data = $request->validate([
            'order_id' => ['required', 'string', 'max:100'], 'total' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'], 'status' => ['required', 'in:paid,refunded,cancelled'],
            'customer.phone' => ['required', 'string', 'max:30'], 'customer.name' => ['nullable', 'string', 'max:100'],
        ]);
        $customer = Customer::firstOrCreate(
            ['business_id' => $integration->business_id, 'phone' => PhoneNumber::validated($data['customer']['phone'], 'customer.phone')],
            ['name' => $data['customer']['name'] ?? null],
        );
        $order = Order::updateOrCreate(
            ['business_id' => $integration->business_id, 'source' => $integration->provider, 'external_id' => $data['order_id']],
            ['customer_id' => $customer->id, 'total' => $data['total'], 'currency' => $data['currency'] ?? 'PKR', 'status' => $data['status'], 'paid_at' => $data['status'] === 'paid' ? now() : null],
        );
        if ($order->status === 'paid') {
            ProcessPaidOrder::dispatch($order->id, $integration->business_id);
        }

        return response()->json(['accepted' => true, 'order_id' => $order->id], 202);
    }
}
