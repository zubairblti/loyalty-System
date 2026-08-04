<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $businessId = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $request?->user()?->id,
            'business_id' => $businessId ?? $subject?->business_id ?? ($subject instanceof Business ? $subject->id : null),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }
}
