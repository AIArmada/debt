<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('sensitivity', 20)->default('private');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->index(['profile_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
