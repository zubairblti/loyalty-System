<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessBrandingController extends Controller
{
    public function show(Request $request)
    {
        return $this->payload($request->user()->business);
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:80'],
            'brand_primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_text_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $business = $request->user()->business;
        $old = $business->only(['brand_name', 'brand_primary_color', 'brand_accent_color', 'brand_text_color']);
        $update = [
            'brand_name' => $data['brand_name'] ?: null,
            'brand_primary_color' => strtolower($data['brand_primary_color']),
            'brand_accent_color' => strtolower($data['brand_accent_color']),
            'brand_text_color' => strtolower($data['brand_text_color']),
        ];
        if ($request->hasFile('logo')) {
            if ($business->brand_logo_path) {
                Storage::disk('local')->delete($business->brand_logo_path);
            }
            $update['brand_logo_path'] = $request->file('logo')->store("branding/{$business->id}", 'local');
        }
        $business->update($update);
        $audit->log('branding.updated', $business, $old, [
            ...$business->only(['brand_name', 'brand_primary_color', 'brand_accent_color', 'brand_text_color']),
            'logo_updated' => $request->hasFile('logo'),
        ], $business->id, $request);

        return $this->payload($business->fresh());
    }

    public function reset(Request $request, AuditLogger $audit)
    {
        $business = $request->user()->business;
        $old = $business->only(['brand_name', 'brand_primary_color', 'brand_accent_color', 'brand_text_color']);
        if ($business->brand_logo_path) {
            Storage::disk('local')->delete($business->brand_logo_path);
        }
        $business->update([
            'brand_name' => null,
            'brand_primary_color' => '#1d252b',
            'brand_accent_color' => '#e4b94e',
            'brand_text_color' => '#ffffff',
            'brand_logo_path' => null,
        ]);
        $audit->log('branding.reset', $business, $old, $business->only([
            'brand_name', 'brand_primary_color', 'brand_accent_color', 'brand_text_color',
        ]), $business->id, $request);

        return $this->payload($business->fresh());
    }

    public function logo(Request $request)
    {
        $path = $request->user()->business->brand_logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=3600']);
    }

    private function payload($business): array
    {
        return [
            'brand_name' => $business->brand_name ?: $business->name,
            'brand_primary_color' => $business->brand_primary_color ?: '#1d252b',
            'brand_accent_color' => $business->brand_accent_color ?: '#e4b94e',
            'brand_text_color' => $business->brand_text_color ?: '#ffffff',
            'logo_url' => $business->brand_logo_path ? '/api/business/branding/logo?v='.rawurlencode(basename($business->brand_logo_path)) : null,
        ];
    }
}
