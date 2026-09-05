<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_access_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('activate_after')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_access_requests');
    }
};
