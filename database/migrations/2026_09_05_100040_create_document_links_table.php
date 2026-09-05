<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('record_id')->nullable()->constrained('records')->cascadeOnDelete();
            $table->foreignUuid('obligation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('financial_transaction_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('obligation_event_id')->nullable()->constrained('obligation_events')->cascadeOnDelete();
            $table->string('purpose', 40)->default('evidence');
            $table->timestamps();
            $table->index('document_id', 'document_links_document_id_index');
            $table->index('record_id', 'document_links_record_id_index');
            $table->index('obligation_id', 'document_links_obligation_id_index');
            $table->index('financial_transaction_id', 'document_links_financial_transaction_id_index');
            $table->index('obligation_event_id', 'document_links_obligation_event_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
    }
};
