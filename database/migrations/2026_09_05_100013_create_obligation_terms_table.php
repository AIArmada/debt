<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_terms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('calculation_method', 40);
            $table->decimal('interest_rate', 12, 8)->nullable();
            $table->string('interest_period', 20)->nullable();
            $table->string('compounding_period', 20)->nullable();
            $table->bigInteger('late_fee_amount')->nullable();
            $table->decimal('late_fee_rate', 12, 8)->nullable();
            $table->bigInteger('storage_fee_amount')->nullable();
            $table->string('storage_fee_period', 20)->nullable();
            $table->bigInteger('fixed_installment_amount')->nullable();
            $table->jsonb('formula')->nullable();
            $table->jsonb('source_snapshot')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['obligation_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_terms');
    }
};
