<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 30);
            $table->string('status', 30)->default('active');
            $table->bigInteger('amount');
            $table->string('currency', 3);
            $table->string('frequency', 30);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_runs_on')->nullable();
            $table->bigInteger('per_payment_limit')->nullable();
            $table->bigInteger('period_limit')->nullable();
            $table->string('period_limit_frequency', 20)->nullable();
            $table->timestamp('authorised_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_runs_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
