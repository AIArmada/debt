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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
    }
};
