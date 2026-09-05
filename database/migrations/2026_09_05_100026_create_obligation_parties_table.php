<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_parties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('party_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 40);
            $table->string('share_basis', 24)->default('unspecified');
            $table->decimal('share_percent', 12, 8)->nullable();
            $table->bigInteger('share_amount')->nullable();
            $table->string('share_currency', 3)->nullable();
            $table->string('status', 24)->default('active');
            $table->text('notes')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->unique(['obligation_id', 'party_id', 'role']);
            $table->index(['obligation_id', 'role', 'status']);
            $table->index('party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_parties');
    }
};
