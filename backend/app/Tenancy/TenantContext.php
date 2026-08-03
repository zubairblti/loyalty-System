<?php

namespace App\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

class TenantContext
{
    private ?int $businessId = null;

    private bool $systemAccess = false;

    private bool $databaseRoleValidated = false;

    public function activate(int $businessId): void
    {
        $this->businessId = $businessId;
        $this->systemAccess = false;
        $this->syncDatabaseContext();
    }

    public function activateSystem(): void
    {
        $this->businessId = null;
        $this->systemAccess = true;
        $this->syncDatabaseContext();
    }

    public function clear(): void
    {
        $this->businessId = null;
        $this->systemAccess = false;
        $this->syncDatabaseContext();
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    public function hasSystemAccess(): bool
    {
        return $this->systemAccess;
    }

    public function runAsSystem(Closure $callback): mixed
    {
        $businessId = $this->businessId;
        $systemAccess = $this->systemAccess;
        $this->activateSystem();

        try {
            return $callback();
        } finally {
            if ($systemAccess) {
                $this->activateSystem();
            } elseif ($businessId) {
                $this->activate($businessId);
            } else {
                $this->clear();
            }
        }
    }

    private function syncDatabaseContext(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! $this->databaseRoleValidated) {
            $role = DB::selectOne('select rolsuper, rolbypassrls from pg_roles where rolname = current_user');
            if ($role?->rolsuper || $role?->rolbypassrls) {
                throw new LogicException('The runtime database role cannot enforce RLS because it is privileged.');
            }
            $this->databaseRoleValidated = true;
        }

        DB::statement("select set_config('app.current_business_id', ?, false)", [$this->businessId ? (string) $this->businessId : '']);
        DB::statement("select set_config('app.is_super_admin', ?, false)", [$this->systemAccess ? 'true' : 'false']);
    }
}
