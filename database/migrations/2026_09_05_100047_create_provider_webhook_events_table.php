<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 80);
            $table->string('external_event_id', 180);
            $table->string('event_type', 100);
            $table->string('status', 30)->default('received');
            $table->jsonb('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_webhook_events');
    }
};
