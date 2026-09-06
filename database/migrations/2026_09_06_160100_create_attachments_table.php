<?php

use App\Domain\Enums\AttachmentCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->string('attachable_type', 120);
            $table->uuid('attachable_id');
            $table->text('disk_path')->nullable();
            $table->text('link_url')->nullable();
            $table->string('original_name', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('category', 30);
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->timestamp('deleted_at')->nullable();
            $table->index(['attachable_type', 'attachable_id']);
            $table->index(['profile_id', 'created_at']);
        });

        DB::statement('ALTER TABLE attachments ADD CONSTRAINT attachments_single_source CHECK ((disk_path IS NOT NULL) <> (link_url IS NOT NULL))');
        DB::statement("ALTER TABLE attachments ADD CONSTRAINT attachments_category_valid CHECK (category IN ('".implode("','", array_column(AttachmentCategory::cases(), 'value'))."'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
