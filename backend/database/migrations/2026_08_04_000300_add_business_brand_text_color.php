<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('brand_text_color', 7)->default('#ffffff');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn('brand_text_color'));
    }
};
