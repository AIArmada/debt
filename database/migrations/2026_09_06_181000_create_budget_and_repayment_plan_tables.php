<?php

use App\Domain\Enums\PlanStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedBigInteger('income_minor');
            $table->unsignedBigInteger('essential_minor');
            $table->unsignedBigInteger('reserve_minor');
            $table->string('currency', 3);
            $table->timestamps();
            $table->unique(['profile_id', 'starts_on', 'ends_on']);
        });

        Schema::create('repayment_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_period_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default(PlanStatus::Draft->value);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->index(['budget_period_id', 'status']);
        });
        DB::statement("ALTER TABLE repayment_plans ADD CONSTRAINT repayment_plans_status_valid CHECK (status IN ('".implode("','", array_column(PlanStatus::cases(), 'value'))."'))");

        Schema::create('plan_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repayment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('planned_minor');
            $table->timestamps();
            $table->unique(['repayment_plan_id', 'obligation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_allocations');
        Schema::dropIfExists('repayment_plans');
        Schema::dropIfExists('budget_periods');
    }
};
