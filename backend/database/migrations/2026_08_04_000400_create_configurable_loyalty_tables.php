<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('loyalty_enabled')->default(false);
            $table->boolean('points_enabled')->default(false);
            $table->boolean('memberships_enabled')->default(false);
            $table->json('completed_tours')->nullable();
            $table->timestamps();
        });
        Schema::create('loyalty_point_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->decimal('purchase_amount', 12, 2);
            $table->unsignedInteger('earned_points');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'purchase_amount']);
        });
        Schema::create('membership_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('required_points');
            $table->string('badge_color', 7)->default('#e4b94e');
            $table->string('icon', 40)->default('badge');
            $table->unsignedInteger('display_order');
            $table->json('benefits')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'name']);
            $table->unique(['business_id', 'required_points']);
            $table->unique(['business_id', 'display_order']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $condition = "(coalesce(current_setting('app.is_super_admin', true), 'false') = 'true' OR business_id = nullif(current_setting('app.current_business_id', true), '')::bigint)";
            $role = (string) config('database.connections.pgsql.username');
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $role)) {
                throw new RuntimeException('DB_USERNAME is not a valid PostgreSQL role name.');
            }
            foreach (['loyalty_settings', 'loyalty_point_rules', 'membership_levels'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} FOR ALL USING ({$condition}) WITH CHECK ({$condition})");
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE {$table} TO {$role}");
                DB::statement("GRANT USAGE, SELECT ON SEQUENCE {$table}_id_seq TO {$role}");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_levels');
        Schema::dropIfExists('loyalty_point_rules');
        Schema::dropIfExists('loyalty_settings');
    }
};
