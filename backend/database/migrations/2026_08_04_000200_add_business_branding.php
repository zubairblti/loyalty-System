<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('brand_name')->nullable();
            $table->string('brand_primary_color', 7)->default('#1d252b');
            $table->string('brand_accent_color', 7)->default('#e4b94e');
            $table->string('brand_logo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn([
            'brand_name', 'brand_primary_color', 'brand_accent_color', 'brand_logo_path',
        ]));
    }
};
