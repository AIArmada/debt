<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->string('provider', 80);
            $table->string('type', 40);
            $table->string('status', 30)->default('active');
            $table->text('credentials_encrypted')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['profile_id', 'provider', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
