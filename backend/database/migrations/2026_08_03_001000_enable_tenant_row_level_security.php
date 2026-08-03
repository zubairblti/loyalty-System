<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'customers',
        'customer_otps',
        'domains',
        'integrations',
        'pos_terminals',
        'orders',
        'points_ledger',
        'qr_codes',
        'subscriptions',
        'payment_submissions',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $condition = "(coalesce(current_setting('app.is_super_admin', true), 'false') = 'true' OR business_id = nullif(current_setting('app.current_business_id', true), '')::bigint)";
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("CREATE POLICY tenant_isolation ON {$table} FOR ALL USING ({$condition}) WITH CHECK ({$condition})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
