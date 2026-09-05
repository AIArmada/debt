<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->jsonb('permissions')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['profile_id', 'user_id']);
            $table->index(['user_id', 'revoked_at']);
            $table->index(['user_id', 'accepted_at', 'revoked_at', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_members');
    }
};
