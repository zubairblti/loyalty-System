<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_submissions')
            ->where('status', 'processing')
            ->where('created_at', '<', now()->subHour())
            ->update(['status' => 'abandoned', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Abandoned checkout sessions must not be restored as active payments.
    }
};
