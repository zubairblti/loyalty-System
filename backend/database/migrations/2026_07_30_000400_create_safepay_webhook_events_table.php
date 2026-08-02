<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safepay_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_token')->unique();
            $table->string('type');
            $table->string('version')->nullable();
            $table->string('tracker')->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safepay_webhook_events');
    }
};
