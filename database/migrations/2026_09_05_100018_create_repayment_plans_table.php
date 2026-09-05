<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayment_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('budget_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('strategy', 40);
            $table->bigInteger('available_amount');
            $table->string('currency', 3);
            $table->string('status', 30)->default('active');
            $table->text('review_reason')->nullable();
            $table->timestamp('review_required_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'status']);
            $table->index(['profile_id', 'budget_period_id', 'currency', 'status'], 'repayment_plans_activation_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repayment_plans');
    }
};
