<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayment_plan_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repayment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('priority_rank');
            $table->bigInteger('minimum_amount')->default(0);
            $table->bigInteger('extra_amount')->default(0);
            $table->bigInteger('total_amount');
            $table->bigInteger('carried_paid_amount')->default(0);
            $table->text('priority_reason');
            $table->date('projected_completion_on')->nullable();
            $table->timestamps();
            $table->unique(['repayment_plan_id', 'obligation_id']);
            $table->index(['repayment_plan_id', 'priority_rank']);
            $table->index(['repayment_plan_id', 'currency']);
            $table->index('obligation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repayment_plan_allocations');
    }
};
