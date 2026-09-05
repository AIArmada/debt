<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_payment_instructions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payment_destination_id')->nullable()->constrained('party_payment_destinations')->nullOnDelete();
            $table->foreignUuid('beneficiary_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignUuid('payee_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('currency', 3)->nullable();
            $table->string('reference')->nullable();
            $table->jsonb('shown_snapshot')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();
            $table->index(['obligation_id', 'status', 'superseded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_payment_instructions');
    }
};
