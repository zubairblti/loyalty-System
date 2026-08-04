<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusinessProfileController extends Controller
{
    public function show(Request $request)
    {
        return $request->user()->business->only([
            'id', 'name', 'slug', 'address', 'category', 'city', 'country',
            'profile_completed', 'profile_completed_at',
        ]);
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', Rule::in(['PK'])],
        ]);
        $business = $request->user()->business;

        return DB::transaction(function () use ($business, $data, $request, $audit) {
            $wasComplete = $business->profile_completed;
            $old = $business->only(array_keys($data));
            $business->update([
                ...$data,
                'profile_completed_at' => now(),
                'profile_completed_by' => $request->user()->id,
            ]);
            $audit->log($wasComplete ? 'profile.updated' : 'profile.completed', $business, $old, $data, $business->id, $request);

            return $business->fresh();
        });
    }
}
