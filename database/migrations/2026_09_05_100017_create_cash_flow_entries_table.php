<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_period_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('category', 60);
            $table->string('name');
            $table->bigInteger('amount');
            $table->boolean('is_essential')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->string('frequency', 30)->nullable();
            $table->date('occurred_on')->nullable();
            $table->timestamps();
            $table->index('budget_period_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_entries');
    }
};
