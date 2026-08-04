<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->string('payment_fingerprint', 64)->nullable()->unique();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            $role = (string) config('database.connections.pgsql.username');
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $role)) {
                throw new RuntimeException('DB_USERNAME is not a valid PostgreSQL role name.');
            }
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE notifications TO {$role}");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['payment_fingerprint']);
            $table->dropColumn(['idempotency_key', 'payment_fingerprint']);
        });
    }
};
