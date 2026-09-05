<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->nullable()->constrained('financial_profiles')->nullOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('auditable_type', 120);
            $table->uuid('auditable_id');
            $table->string('action', 80);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['profile_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
