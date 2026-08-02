<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('monthly_price', 12, 2)->default(0);
            $table->unsignedTinyInteger('yearly_discount_percent')->default(30);
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->change();
        });

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('purpose');
            $table->string('code_hash');
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['email', 'purpose', 'created_at']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('billing_cycle');
            $table->decimal('amount_paid', 12, 2);
            $table->string('status')->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['business_id', 'status', 'ends_at']);
        });

        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('billing_cycle');
            $table->string('method');
            $table->decimal('amount', 12, 2);
            $table->string('transaction_reference')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('verification_codes');
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable(false)->change();
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['monthly_price', 'yearly_discount_percent', 'features', 'active']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
