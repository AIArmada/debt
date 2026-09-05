<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->string('verification_status', 30)->default('needs_review');
            $table->string('category', 40)->default('other');
            $table->string('evidence_type', 20)->default('file');
            $table->string('title', 160)->nullable();
            $table->string('source', 160)->nullable();
            $table->text('external_url')->nullable();
            $table->longText('content')->nullable();
            $table->date('captured_on')->nullable();
            $table->string('ocr_status', 30)->default('not_requested');
            $table->string('ocr_provider', 40)->nullable();
            $table->longText('extracted_text')->nullable();
            $table->text('ocr_error')->nullable();
            $table->timestamp('ocr_completed_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
