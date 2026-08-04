<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Customer::where('business_id', $request->session()->get('customer_business_id'))
            ->find($request->session()->get('customer_id'));
        if (! $customer && $remember = $request->cookie('customer_remember')) {
            [$id, $token] = array_pad(explode('|', $remember, 2), 2, null);
            $candidate = $id ? Customer::find($id) : null;
            if ($candidate && $token && $candidate->remember_token && hash_equals($candidate->remember_token, hash('sha256', $token))) {
                $customer = $candidate;
                $request->session()->regenerate();
                $request->session()->put(['customer_id' => $customer->id, 'customer_business_id' => $customer->business_id]);
            }
        }

        abort_unless($customer, 401, 'Customer login required.');
        $request->attributes->set('customer', $customer);

        return $next($request);
    }
}
