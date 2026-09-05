<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40);
            $table->string('base_currency', 3);
            $table->string('timezone')->default('UTC');
            $table->string('locale', 16)->nullable();
            $table->boolean('is_islamic_mode_enabled')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->index(['owner_user_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_profiles');
    }
};
