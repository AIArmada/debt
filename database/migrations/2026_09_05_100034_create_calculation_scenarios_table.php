<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_scenarios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->bigInteger('extra_payment')->default(0);
            $table->string('payment_frequency', 20)->default('monthly');
            $table->unsignedSmallInteger('horizon_months')->default(12);
            $table->jsonb('result')->nullable();
            $table->timestamps();
            $table->index(['obligation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_scenarios');
    }
};
