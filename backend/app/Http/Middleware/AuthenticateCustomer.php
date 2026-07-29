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

        abort_unless($customer, 401, 'Customer login required.');
        $request->attributes->set('customer', $customer);

        return $next($request);
    }
}
