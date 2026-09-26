<?php

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
        Schema::create('party_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('label', 255);
            $table->string('value', 255);
            $table->boolean('is_primary')->default(false);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['party_id', 'is_primary']);
        });

        Schema::getConnection()->statement('CREATE UNIQUE INDEX party_contacts_primary_unique ON party_contacts (party_id) WHERE is_primary = TRUE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::getConnection()->statement('DROP INDEX IF EXISTS party_contacts_primary_unique');
        Schema::dropIfExists('party_contacts');
    }
};
