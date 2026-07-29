<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('domain_limit')->default(1);
            $table->unsignedInteger('qr_limit')->default(5);
            $table->unsignedInteger('terminal_limit')->default(1);
            $table->unsignedInteger('monthly_order_limit')->default(1000);
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('points_per_100')->default(1);
            $table->string('currency', 3)->default('PKR');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('id')->constrained();
            $table->string('role')->default('owner');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'phone']);
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('host');
            $table->string('verification_token');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'host']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('provider')->default('custom');
            $table->string('public_key')->unique();
            $table->text('secret');
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('branch')->nullable();
            $table->string('terminal_key')->unique();
            $table->text('secret');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('external_id');
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('payment_method')->nullable();
            $table->string('status')->default('paid');
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'source', 'external_id']);
        });

        Schema::create('points_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points');
            $table->string('type');
            $table->string('idempotency_key');
            $table->string('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key']);
        });

        Schema::create('qr_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('type');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('customers')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('points_ledger');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('pos_terminals');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('customers');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('business_id'));
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('plans');
    }
};
