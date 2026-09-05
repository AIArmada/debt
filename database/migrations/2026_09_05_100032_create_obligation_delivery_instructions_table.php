<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_delivery_instructions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('address_id')->nullable()->constrained('party_addresses')->nullOnDelete();
            $table->foreignUuid('recipient_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 30)->default('delivery');
            $table->string('label', 120);
            $table->text('instructions')->nullable();
            $table->string('status', 30)->default('active');
            $table->string('verification_status', 30)->default('needs_review');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->jsonb('shown_snapshot')->nullable();
            $table->timestamps();
            $table->index(['obligation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_delivery_instructions');
    }
};
