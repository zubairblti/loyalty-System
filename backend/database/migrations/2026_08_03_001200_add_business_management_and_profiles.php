<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('status')->default('pending')->index();
            $table->string('address')->nullable();
            $table->string('category')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamp('profile_completed_at')->nullable();
            $table->foreignId('profile_completed_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('users', fn (Blueprint $table) => $table->unique('phone'));
        DB::table('businesses')->where('active', true)->update(['status' => 'active']);
        DB::table('businesses')->where('active', false)->update(['status' => 'suspended']);

        Schema::table('plans', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_months')->default(1);
            $table->boolean('public')->default(true)->index();
            $table->unsignedSmallInteger('display_order')->default(0)->index();
            $table->softDeletes();
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->string('currency', 3)->default('PKR');
            $table->timestamp('payment_date')->nullable();
            $table->string('activation_reason')->nullable();
        });
        DB::table('payment_submissions')->where('status', 'approved')->update(['status' => 'paid']);
        DB::table('payment_submissions')->where('status', 'rejected')->update(['status' => 'failed']);
        DB::table('payment_submissions')->where('status', 'paid')->whereNull('payment_date')->update(['payment_date' => DB::raw('reviewed_at')]);

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['phone']));
        Schema::table('payment_submissions', fn (Blueprint $table) => $table->dropColumn(['currency', 'payment_date', 'activation_reason']));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['description', 'duration_months', 'public', 'display_order', 'deleted_at']));
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_completed_by');
            $table->dropColumn(['status', 'address', 'category', 'city', 'country', 'profile_completed_at']);
        });
    }
};
