<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_parties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('record_id')->constrained('records')->cascadeOnDelete();
            $table->foreignUuid('party_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 40);
            $table->boolean('is_primary')->default(false);
            $table->string('responsibility_scope', 24)->default('record');
            $table->string('status', 24)->default('active');
            $table->text('notes')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('visibility', 24)->default('private');
            $table->timestamps();
            $table->unique(['record_id', 'party_id', 'role']);
            $table->index(['record_id', 'role', 'status']);
            $table->index('party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_parties');
    }
};
