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

        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON TABLE audit_logs FROM {$role}");
    }

    public function down(): void
    {
        // Intentionally keep audit history immutable for the runtime role.
    }
};
