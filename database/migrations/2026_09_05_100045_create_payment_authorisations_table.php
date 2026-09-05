<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_authorisations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('payment_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('integration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('authorised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 80)->default('sandbox');
            $table->string('status', 30)->default('pending');
            $table->bigInteger('max_amount')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['payment_schedule_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_authorisations');
    }
};
