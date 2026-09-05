<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transaction_parties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('financial_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('party_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 24);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['financial_transaction_id', 'party_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transaction_parties');
    }
};
