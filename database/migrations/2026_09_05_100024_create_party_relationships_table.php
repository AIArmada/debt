<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_relationships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('from_party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignUuid('to_party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('relationship_type', 40);
            $table->string('title')->nullable();
            $table->string('status', 24)->default('active');
            $table->text('notes')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'relationship_type', 'status']);
            $table->unique(['from_party_id', 'to_party_id', 'relationship_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_relationships');
    }
};
