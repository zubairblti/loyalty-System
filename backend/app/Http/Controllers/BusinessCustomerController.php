<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class BusinessCustomerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);

        return Customer::query()->with(['currentMembership.level'])->withCount('orders')
            ->withSum(['ledger as points_balance' => fn ($query) => $query], 'points')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.strtolower($search).'%';
                $query->where(fn ($nested) => $nested->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$term])->orWhere('phone', 'like', $term));
            })->latest()->paginate(25);
    }
}
