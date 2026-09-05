<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 30);
            $table->string('label', 120);
            $table->string('provider', 100)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('account_holder_name')->nullable();
            $table->text('account_identifier_encrypted')->nullable();
            $table->string('account_identifier_last4', 4)->nullable();
            $table->string('verification_status', 30)->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->index(['profile_id', 'status', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_accounts');
    }
};
