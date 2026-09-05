<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('preferred_name');
            $table->string('legal_name')->nullable();
            $table->jsonb('aliases')->nullable();
            $table->jsonb('identifiers')->nullable();
            $table->string('status', 24)->default('active');
            $table->string('verification_status', 24)->default('unverified');
            $table->string('source', 40)->default('user_entered');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'status', 'preferred_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
