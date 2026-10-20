<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('record_id')->constrained('records')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_name')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->string('disputed_name')->nullable();
            $table->text('dispute_note')->nullable();
            $table->timestamps();
            $table->index(['record_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_shares');
    }
};
