<?php

use App\Domain\Enums\ImportRowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->string('source_hash', 64);
            $table->string('filename', 255);
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamps();
            $table->unique(['profile_id', 'source_hash']);
        });

        Schema::create('import_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_batch_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->text('description')->nullable();
            $table->string('status', 30)->default(ImportRowStatus::Pending->value);
            $table->foreignUuid('suggested_obligation_id')->nullable()->constrained('obligations')->nullOnDelete();
            $table->string('suggested_entry', 30)->nullable();
            $table->foreignUuid('matched_movement_id')->nullable()->constrained('money_movements')->nullOnDelete();
            $table->timestamps();
            $table->index(['import_batch_id', 'status']);
        });
        DB::statement("ALTER TABLE import_rows ADD CONSTRAINT import_rows_status_valid CHECK (status IN ('".implode("','", array_column(ImportRowStatus::cases(), 'value'))."'))");
        DB::statement('ALTER TABLE import_rows ADD CONSTRAINT import_rows_amount_positive CHECK (amount_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
