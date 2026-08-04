<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyPointRule;
use App\Models\LoyaltySetting;
use App\Models\MembershipLevel;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoyaltyConfigurationController extends Controller
{
    public function show(Request $request)
    {
        $business = $request->user()->business;
        $settings = $this->settings($business->id);

        return [
            'settings' => $settings,
            'rules' => LoyaltyPointRule::orderBy('purchase_amount')->get(),
            'levels' => MembershipLevel::orderBy('display_order')->get(),
            'onboarding' => [
                'profile' => $business->profile_completed,
                'loyalty' => $settings->loyalty_enabled,
                'points_rule' => ! $settings->points_enabled || LoyaltyPointRule::where('active', true)->exists(),
                'membership_level' => ! $settings->memberships_enabled || MembershipLevel::where('active', true)->exists(),
                'qr' => $business->qrCodes()->exists(),
                'integration' => $business->integrations()->exists(),
            ],
        ];
    }

    public function updateSettings(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'loyalty_enabled' => ['required', 'boolean'],
            'points_enabled' => ['required', 'boolean'],
            'memberships_enabled' => ['required', 'boolean'],
            'membership_downgrade_grace_days' => ['nullable', Rule::in([15, 30, 60])],
        ]);
        if (! $data['loyalty_enabled']) {
            $data['points_enabled'] = false;
            $data['memberships_enabled'] = false;
        }
        $settings = $this->settings($request->user()->business_id);
        $old = $settings->only(array_keys($data));
        $settings->update($data);
        $audit->log($data['loyalty_enabled'] ? 'loyalty.enabled' : 'loyalty.disabled', $settings, $old, $data, $request->user()->business_id, $request);

        return response()->json($settings);
    }

    public function storeRule(Request $request, AuditLogger $audit)
    {
        $this->requireFeature($request->user()->business_id, 'points_enabled', 'Enable Customer Points before configuring rules.');
        $data = $request->validate([
            'purchase_amount' => ['required', 'numeric', 'min:1', Rule::unique('loyalty_point_rules')->where('business_id', $request->user()->business_id)],
            'earned_points' => ['required', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $rule = LoyaltyPointRule::create([...$data, 'business_id' => $request->user()->business_id]);
        $audit->log('loyalty.points_rule_created', $rule, [], $rule->toArray(), null, $request);

        return response()->json($rule, 201);
    }

    public function updateRule(Request $request, int $rule, AuditLogger $audit)
    {
        $this->requireFeature($request->user()->business_id, 'points_enabled', 'Enable Customer Points before configuring rules.');
        $rule = LoyaltyPointRule::findOrFail($rule);
        abort_unless($rule->business_id === $request->user()->business_id, 404);
        $data = $request->validate([
            'purchase_amount' => ['required', 'numeric', 'min:1', Rule::unique('loyalty_point_rules')->where('business_id', $request->user()->business_id)->ignore($rule)],
            'earned_points' => ['required', 'integer', 'min:1'],
            'active' => ['required', 'boolean'],
        ]);
        $old = $rule->toArray();
        $rule->update($data);
        $audit->log('loyalty.points_rule_updated', $rule, $old, $rule->fresh()->toArray(), null, $request);

        return $rule->fresh();
    }

    public function deleteRule(Request $request, int $rule, AuditLogger $audit)
    {
        $rule = LoyaltyPointRule::findOrFail($rule);
        abort_unless($rule->business_id === $request->user()->business_id, 404);
        $audit->log('loyalty.points_rule_deleted', $rule, $rule->toArray(), [], null, $request);
        $rule->delete();

        return response()->noContent();
    }

    public function storeLevel(Request $request, AuditLogger $audit)
    {
        $this->requireFeature($request->user()->business_id, 'memberships_enabled', 'Enable Membership Levels before configuring tiers.');
        $level = DB::transaction(function () use ($request, $audit) {
            $data = $this->levelData($request);
            $level = MembershipLevel::create([...$data, 'business_id' => $request->user()->business_id]);
            $this->validateLevelOrder();
            $audit->log('loyalty.tier_created', $level, [], $level->toArray(), null, $request);

            return $level;
        });

        return response()->json($level, 201);
    }

    public function updateLevel(Request $request, int $level, AuditLogger $audit)
    {
        $this->requireFeature($request->user()->business_id, 'memberships_enabled', 'Enable Membership Levels before configuring tiers.');
        $level = MembershipLevel::findOrFail($level);
        abort_unless($level->business_id === $request->user()->business_id, 404);

        return DB::transaction(function () use ($request, $level, $audit) {
            $old = $level->toArray();
            $level->update($this->levelData($request, $level));
            $this->validateLevelOrder();
            $audit->log($level->active ? 'loyalty.tier_updated' : 'loyalty.tier_deactivated', $level, $old, $level->fresh()->toArray(), null, $request);

            return $level->fresh();
        });
    }

    public function deleteLevel(Request $request, int $level, AuditLogger $audit)
    {
        $level = MembershipLevel::findOrFail($level);
        abort_unless($level->business_id === $request->user()->business_id, 404);
        $old = $level->toArray();
        $level->update(['active' => false]);
        $audit->log('loyalty.tier_deactivated', $level, $old, [
            'active' => false,
            'resolution' => 'Existing assignments are preserved; this tier is unavailable for future assignments and upgrades.',
        ], null, $request);

        return response()->noContent();
    }

    public function completeTour(Request $request)
    {
        $tour = $request->validate(['tour' => ['required', Rule::in(['loyalty', 'membership', 'rewards'])]])['tour'];
        $settings = $this->settings($request->user()->business_id);
        $settings->update(['completed_tours' => array_values(array_unique([...($settings->completed_tours ?? []), $tour]))]);

        return $settings;
    }

    private function levelData(Request $request, ?MembershipLevel $level = null): array
    {
        $data = $request->validate([
            'name' => ['required', Rule::in(['Silver', 'Gold', 'Platinum', 'Diamond', 'VIP']), Rule::unique('membership_levels')->where('business_id', $request->user()->business_id)->ignore($level)],
            'required_points' => ['required', 'integer', 'min:0', Rule::unique('membership_levels')->where('business_id', $request->user()->business_id)->ignore($level)],
            'badge_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => ['string', 'max:120'],
            'active' => ['required', 'boolean'],
        ]);
        $order = array_search($data['name'], ['Silver', 'Gold', 'Platinum', 'Diamond', 'VIP'], true) + 1;

        return [...$data, 'display_order' => $order, 'icon' => match ($data['name']) {
            'Gold' => 'star', 'Platinum' => 'crown', 'Diamond' => 'diamond', 'VIP' => 'shield', default => 'badge',
        }];
    }

    private function validateLevelOrder(): void
    {
        $levels = MembershipLevel::orderBy('display_order')->get();
        foreach ($levels as $index => $level) {
            if ($index && $level->required_points <= $levels[$index - 1]->required_points) {
                throw ValidationException::withMessages(['required_points' => 'Required points must increase with display order.']);
            }
        }
    }

    private function settings(int $businessId): LoyaltySetting
    {
        return LoyaltySetting::firstOrCreate(['business_id' => $businessId], [
            'loyalty_enabled' => false,
            'points_enabled' => false,
            'memberships_enabled' => false,
        ]);
    }

    private function requireFeature(int $businessId, string $feature, string $message): void
    {
        $settings = $this->settings($businessId);
        abort_unless($settings->loyalty_enabled && $settings->{$feature}, 409, $message);
    }
}
