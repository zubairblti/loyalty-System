<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $role = (string) config('database.connections.pgsql.username');
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $role)) {
            throw new RuntimeException('DB_USERNAME is not a valid PostgreSQL role name.');
        }

        DB::statement("GRANT SELECT, INSERT ON TABLE audit_logs TO {$role}");
        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON TABLE audit_logs FROM {$role}");
        DB::statement("GRANT USAGE, SELECT ON SEQUENCE audit_logs_id_seq TO {$role}");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $role = (string) config('database.connections.pgsql.username');
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $role)) {
            DB::statement("REVOKE SELECT, INSERT ON TABLE audit_logs FROM {$role}");
            DB::statement("GRANT UPDATE, DELETE, TRUNCATE ON TABLE audit_logs TO {$role}");
            DB::statement("REVOKE USAGE, SELECT ON SEQUENCE audit_logs_id_seq FROM {$role}");
        }
    }
};
