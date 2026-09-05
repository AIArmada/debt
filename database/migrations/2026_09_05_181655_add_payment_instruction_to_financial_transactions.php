<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->foreignUuid('payment_instruction_id')->nullable()->constrained('obligation_payment_instructions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            // dropConstrainedForeignIdFor removes the constraint and the column.
            $table->dropConstrainedForeignIdFor('payment_instruction_id');
        });
    }
};
