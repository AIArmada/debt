<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_import_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_import_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('obligation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('row_number');
            $table->date('occurred_on')->nullable();
            $table->text('description')->nullable();
            $table->bigInteger('amount');
            $table->string('currency', 3);
            $table->string('suggested_direction', 20)->nullable();
            $table->string('external_reference')->nullable();
            $table->string('status', 30)->default('unmatched');
            $table->jsonb('raw_data')->nullable();
            $table->timestamps();
            $table->unique(['bank_import_id', 'row_number']);
            $table->index(['obligation_id', 'status']);
            $table->index(['financial_transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_import_rows');
    }
};
