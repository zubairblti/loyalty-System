<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['receipt_number', 'cheque_number'] as $column) {
            if (Schema::hasColumn('payment_submissions', $column)) {
                Schema::table('payment_submissions', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payment_submissions', 'receipt_number')) {
            Schema::table('payment_submissions', fn (Blueprint $table) => $table->string('receipt_number')->nullable());
        }
        if (! Schema::hasColumn('payment_submissions', 'cheque_number')) {
            Schema::table('payment_submissions', fn (Blueprint $table) => $table->string('cheque_number')->nullable());
        }
    }
};
