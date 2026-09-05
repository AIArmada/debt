<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_execution_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payment_authorisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->string('provider', 80);
            $table->bigInteger('amount');
            $table->string('currency', 3);
            $table->string('status', 30)->default('pending');
            $table->string('external_reference')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['payment_schedule_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_execution_attempts');
    }
};
