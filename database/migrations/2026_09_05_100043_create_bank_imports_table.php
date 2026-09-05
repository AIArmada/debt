<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('format', 20)->default('csv');
            $table->string('currency', 3);
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_imports');
    }
};
