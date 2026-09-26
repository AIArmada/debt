<?php

use App\Domain\Enums\PartyRelationshipKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('party_relationships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('from_party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignUuid('to_party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('kind', 50);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['from_party_id', 'to_party_id', 'kind']);
            $table->index(['profile_id', 'from_party_id']);
            $table->index(['profile_id', 'to_party_id']);
        });

        Schema::getConnection()->statement("ALTER TABLE party_relationships ADD CONSTRAINT party_relationships_kind_valid CHECK (kind IN ('".implode("','", array_column(PartyRelationshipKind::cases(), 'value'))."'))");
        Schema::getConnection()->statement('ALTER TABLE party_relationships ADD CONSTRAINT party_relationships_not_self CHECK (from_party_id <> to_party_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('party_relationships');
    }
};
