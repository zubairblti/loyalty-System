<?php

namespace Tests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function useTenant(int $businessId): void
    {
        app(TenantContext::class)->activate($businessId);
    }

    protected function useSystemAccess(): void
    {
        app(TenantContext::class)->activateSystem();
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            app(TenantContext::class)->clear();
        }
        parent::tearDown();
    }
}
