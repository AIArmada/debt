<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('repayment_installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('repayment_plan_allocation_id')->nullable()->constrained('repayment_plan_allocations')->nullOnDelete();
            $table->foreignUuid('collection_schedule_id')->nullable()->constrained('collection_schedules')->nullOnDelete();
            $table->string('entry_type', 40)->default('payment');
            $table->string('balance_effect', 20)->default('decrease');
            $table->string('status', 30)->default('planned');
            $table->bigInteger('amount');
            $table->bigInteger('balance_before')->nullable();
            $table->bigInteger('balance_after')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('principal_amount')->nullable();
            $table->bigInteger('interest_amount')->nullable();
            $table->bigInteger('fee_amount')->nullable();
            $table->date('occurred_on')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('external_reference')->nullable();
            $table->jsonb('provider_payload')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['obligation_id', 'occurred_on']);
            $table->index(['status', 'submitted_at']);
            $table->index(['obligation_id', 'entry_type', 'status']);
            $table->index(['repayment_plan_allocation_id', 'status']);
            $table->index(['collection_schedule_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
