<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $businessIds = DB::table('audit_logs')
            ->where('action', 'business.created_by_admin')
            ->whereNotNull('business_id')
            ->distinct()
            ->pluck('business_id');

        if ($businessIds->isNotEmpty()) {
            DB::table('users')->whereIn('business_id', $businessIds)
                ->where('role', 'owner')->whereNull('email_verified_at')
                ->update(['email_verified_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Verification is intentionally not revoked on rollback.
    }
};
