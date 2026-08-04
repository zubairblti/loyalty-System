<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaidOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\QrCode;
use App\Support\PhoneNumber;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    public function index(Request $request)
    {
        return QrCode::where('business_id', $request->user()->business_id)->latest()->get();
    }

    public function store(Request $request)
    {
        $business = $request->user()->business->load('plan');
        abort_if(QrCode::where('business_id', $business->id)->where('active', true)->count() >= $business->plan->qr_limit, 422, 'Plan QR limit reached.');
        $data = $request->validate(['label' => ['required', 'max:100'], 'type' => ['required', 'in:static,dynamic'], 'order_id' => ['nullable', 'integer']]);
        if ($data['type'] === 'dynamic') {
            abort_unless(isset($data['order_id']), 422, 'Dynamic QR requires an order.');
            Order::where('business_id', $business->id)->findOrFail($data['order_id']);
        }
        $token = Str::random(64);
        $qr = QrCode::create([
            ...$data, 'id' => (string) Str::uuid(), 'business_id' => $business->id,
            'token_hash' => hash('sha256', $token), 'expires_at' => $data['type'] === 'dynamic' ? now()->addMinutes(15) : null,
        ]);

        return response()->json(['qr' => $qr, 'claim_url' => config('app.frontend_url', 'http://localhost:5173')."/claim/{$token}"], 201);
    }

    public function claim(Request $request, string $token, NotificationService $notifications)
    {
        $data = $request->validate(['phone' => ['required', 'max:30'], 'name' => ['nullable', 'max:100'], 'order_reference' => ['nullable', 'max:100']]);

        return DB::transaction(function () use ($token, $data, $notifications) {
            $qr = QrCode::where('token_hash', hash('sha256', $token))->lockForUpdate()->firstOrFail();
            abort_if(! $qr->active || $qr->claimed_at || ($qr->expires_at && $qr->expires_at->isPast()), 410, 'QR is expired or already claimed.');
            $customer = Customer::firstOrCreate(['business_id' => $qr->business_id, 'phone' => PhoneNumber::validated($data['phone'])], ['name' => $data['name'] ?? null]);
            $qr->update(['claimed_at' => now(), 'claimed_by' => $customer->id]);
            $business = $qr->business()->with('owner')->first();
            if ($owner = $business?->owner) {
                $notifications->send($owner, 'qr_activity', 'QR code claimed', ($customer->name ?: $customer->phone)." claimed {$qr->label}.", '/#QR%20codes', "qr:{$qr->id}:claimed");
                if ($customer->wasRecentlyCreated) {
                    $notifications->send($owner, 'customer_registered', 'New customer added', ($customer->name ?: $customer->phone).' joined through QR.', '/#Customers', "customer:{$customer->id}:created");
                }
            }
            if ($qr->order_id) {
                $order = Order::findOrFail($qr->order_id);
                $order->update(['customer_id' => $customer->id]);
                ProcessPaidOrder::dispatch($order->id, $qr->business_id);
            }

            return ['claimed' => true, 'customer_id' => $customer->id];
        });
    }
}
