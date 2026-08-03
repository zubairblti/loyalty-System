<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->unique(['id', 'business_id'], 'customers_id_business_unique'));
        Schema::table('domains', fn (Blueprint $table) => $table->unique(['id', 'business_id'], 'domains_id_business_unique'));
        Schema::table('pos_terminals', fn (Blueprint $table) => $table->unique(['id', 'business_id'], 'pos_terminals_id_business_unique'));
        Schema::table('orders', fn (Blueprint $table) => $table->unique(['id', 'business_id'], 'orders_id_business_unique'));

        Schema::table('integrations', function (Blueprint $table) {
            $table->foreign(['domain_id', 'business_id'], 'integrations_domain_business_fk')
                ->references(['id', 'business_id'])->on('domains');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign(['customer_id', 'business_id'], 'orders_customer_business_fk')
                ->references(['id', 'business_id'])->on('customers');
            $table->foreign(['pos_terminal_id', 'business_id'], 'orders_terminal_business_fk')
                ->references(['id', 'business_id'])->on('pos_terminals');
        });
        Schema::table('points_ledger', function (Blueprint $table) {
            $table->foreign(['customer_id', 'business_id'], 'ledger_customer_business_fk')
                ->references(['id', 'business_id'])->on('customers');
            $table->foreign(['order_id', 'business_id'], 'ledger_order_business_fk')
                ->references(['id', 'business_id'])->on('orders');
        });
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->foreign(['pos_terminal_id', 'business_id'], 'qr_terminal_business_fk')
                ->references(['id', 'business_id'])->on('pos_terminals');
            $table->foreign(['order_id', 'business_id'], 'qr_order_business_fk')
                ->references(['id', 'business_id'])->on('orders');
            $table->foreign(['claimed_by', 'business_id'], 'qr_customer_business_fk')
                ->references(['id', 'business_id'])->on('customers');
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropForeign('qr_customer_business_fk');
            $table->dropForeign('qr_order_business_fk');
            $table->dropForeign('qr_terminal_business_fk');
        });
        Schema::table('points_ledger', function (Blueprint $table) {
            $table->dropForeign('ledger_order_business_fk');
            $table->dropForeign('ledger_customer_business_fk');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('orders_terminal_business_fk');
            $table->dropForeign('orders_customer_business_fk');
        });
        Schema::table('integrations', fn (Blueprint $table) => $table->dropForeign('integrations_domain_business_fk'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropUnique('orders_id_business_unique'));
        Schema::table('pos_terminals', fn (Blueprint $table) => $table->dropUnique('pos_terminals_id_business_unique'));
        Schema::table('domains', fn (Blueprint $table) => $table->dropUnique('domains_id_business_unique'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropUnique('customers_id_business_unique'));
    }
};
