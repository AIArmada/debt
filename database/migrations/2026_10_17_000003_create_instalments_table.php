<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instalments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained('obligations')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->date('due_on');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->timestamps();
            $table->unique(['obligation_id', 'sequence']);
            $table->index(['obligation_id', 'due_on']);
        });
        DB::statement('ALTER TABLE instalments ADD CONSTRAINT instalments_amount_positive CHECK (amount_minor > 0)');
        DB::statement('ALTER TABLE instalments ADD CONSTRAINT instalments_paid_nonnegative CHECK (paid_minor >= 0)');

        Schema::create('instalment_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('money_movement_id')->constrained('money_movements')->cascadeOnDelete();
            $table->foreignUuid('instalment_id')->constrained('instalments')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['money_movement_id', 'instalment_id']);
        });
        DB::statement('ALTER TABLE instalment_allocations ADD CONSTRAINT instalment_allocations_amount_positive CHECK (amount_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('instalment_allocations');
        Schema::dropIfExists('instalments');
    }
};
