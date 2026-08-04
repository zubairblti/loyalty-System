<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('membership_downgrade_grace_days')->nullable()->after('memberships_enabled');
        });

        Schema::create('customer_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_level_id')->constrained()->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('grace_expires_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('assignment_reason', 40);
            $table->string('end_reason', 40)->nullable();
            $table->timestamps();
            $table->index(['business_id', 'customer_id', 'ended_at']);
            $table->index(['grace_expires_at', 'ended_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX customer_memberships_one_current ON customer_memberships (business_id, customer_id) WHERE ended_at IS NULL');
            $condition = "(coalesce(current_setting('app.is_super_admin', true), 'false') = 'true' OR business_id = nullif(current_setting('app.current_business_id', true), '')::bigint)";
            $role = (string) config('database.connections.pgsql.username');
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $role)) {
                throw new RuntimeException('DB_USERNAME is not a valid PostgreSQL role name.');
            }
            DB::statement('ALTER TABLE customer_memberships ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE customer_memberships FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation ON customer_memberships FOR ALL USING ({$condition}) WITH CHECK ({$condition})");
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE customer_memberships TO {$role}");
            DB::statement("GRANT USAGE, SELECT ON SEQUENCE customer_memberships_id_seq TO {$role}");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_memberships');
        Schema::table('loyalty_settings', fn (Blueprint $table) => $table->dropColumn('membership_downgrade_grace_days'));
    }
};
