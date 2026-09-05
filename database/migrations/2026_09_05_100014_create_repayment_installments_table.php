<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayment_installments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->date('due_on');
            $table->bigInteger('expected_amount');
            $table->bigInteger('principal_amount')->nullable();
            $table->bigInteger('interest_amount')->nullable();
            $table->bigInteger('fee_amount')->nullable();
            $table->string('status', 30)->default('planned');
            $table->timestamps();
            $table->unique(['obligation_id', 'sequence']);
            $table->index(['due_on', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repayment_installments');
    }
};
