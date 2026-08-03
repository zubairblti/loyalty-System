<?php

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);
            if ($context->hasSystemAccess()) {
                return;
            }

            $businessId = $context->businessId();
            $businessId
                ? $builder->where($builder->qualifyColumn('business_id'), $businessId)
                : $builder->whereRaw('1 = 0');
        });

        static::creating(function ($model): void {
            $context = app(TenantContext::class);
            if ($context->hasSystemAccess()) {
                return;
            }

            $businessId = $context->businessId();
            if (! $businessId) {
                throw new LogicException('Tenant context is required to create tenant-owned data.');
            }
            if ($model->business_id && (int) $model->business_id !== $businessId) {
                throw new LogicException('Cross-tenant writes are not allowed.');
            }
            $model->business_id = $businessId;
        });

        $guardExistingModel = function ($model): void {
            $context = app(TenantContext::class);
            if (! $context->hasSystemAccess() && (int) $model->business_id !== $context->businessId()) {
                throw new LogicException('Cross-tenant model changes are not allowed.');
            }
        };
        static::updating($guardExistingModel);
        static::deleting($guardExistingModel);
    }
}
