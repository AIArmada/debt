<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('record_id')->constrained('records')->cascadeOnDelete();
            $table->string('direction', 20);
            $table->string('obligation_kind', 20)->default('money');
            $table->string('category', 60);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->string('tracking_mode', 20)->default('snapshot');
            $table->string('currency', 3)->nullable();
            $table->bigInteger('original_amount')->nullable();
            $table->bigInteger('current_principal_balance')->nullable();
            $table->bigInteger('current_total_balance')->nullable();
            $table->jsonb('currency_opening_balances')->nullable();
            $table->jsonb('currency_balances')->nullable();
            $table->bigInteger('minimum_payment_amount')->nullable();
            $table->date('started_on')->nullable();
            $table->date('due_on')->nullable();
            $table->date('next_due_on')->nullable();
            $table->string('data_confidence', 20)->default('partial');
            $table->boolean('is_interest_bearing')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->string('subject_name')->nullable();
            $table->decimal('subject_quantity', 20, 4)->nullable();
            $table->decimal('current_subject_quantity', 20, 4)->nullable();
            $table->string('quantity_mode', 20)->default('countable');
            $table->string('subject_unit', 60)->nullable();
            $table->string('subject_condition', 60)->nullable();
            $table->text('subject_details')->nullable();
            $table->string('asset_type', 40)->nullable();
            $table->string('service_type', 40)->nullable();
            $table->bigInteger('estimated_value')->nullable();
            $table->string('estimated_value_currency', 3)->nullable();
            $table->text('completion_criteria')->nullable();
            $table->boolean('is_conditional')->default(false);
            $table->text('condition_description')->nullable();
            $table->date('condition_triggered_on')->nullable();
            $table->timestamps();
            $table->index(['record_id', 'direction', 'status']);
            $table->index(['record_id', 'next_due_on']);
            $table->index(['record_id', 'obligation_kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligations');
    }
};
