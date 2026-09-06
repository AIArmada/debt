<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX parties_profile_display_name_active_unique ON parties (profile_id, LOWER(display_name)) WHERE status = 'active' AND display_name IS NOT NULL");

        Schema::create('money_subjects', function (Blueprint $table): void {
            $table->uuid('obligation_id')->primary();
            $table->foreign('obligation_id')->references('id')->on('obligations')->cascadeOnDelete();
            $table->string('currency', 3);
        });

        Schema::create('quantity_subjects', function (Blueprint $table): void {
            $table->uuid('obligation_id')->primary();
            $table->foreign('obligation_id')->references('id')->on('obligations')->cascadeOnDelete();
            $table->string('name', 255);
            $table->decimal('total', 18, 4);
            $table->decimal('returned', 18, 4)->default(0);
            $table->string('unit', 60);
            $table->boolean('is_fractionable');
        });

        Schema::create('commitment_subjects', function (Blueprint $table): void {
            $table->uuid('obligation_id')->primary();
            $table->foreign('obligation_id')->references('id')->on('obligations')->cascadeOnDelete();
            $table->text('done_criteria');
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_note')->nullable();
        });

        Schema::create('money_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->string('entry', 30);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->date('occurred_on');
            $table->string('status', 20)->default('confirmed');
            $table->string('void_reason')->nullable();
            $table->string('note', 4000)->nullable();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['obligation_id', 'status', 'currency']);
            $table->index(['obligation_id', 'occurred_on']);
        });
        DB::statement('ALTER TABLE money_movements ADD CONSTRAINT money_movements_amount_positive CHECK (amount_minor > 0)');

        Schema::create('quantity_returns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->date('occurred_on');
            $table->string('note', 4000)->nullable();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['obligation_id', 'occurred_on']);
        });
        DB::statement('ALTER TABLE quantity_returns ADD CONSTRAINT quantity_returns_quantity_positive CHECK (quantity > 0)');

        Schema::create('activity_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 120);
            $table->uuid('subject_id');
            $table->string('action', 80);
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['subject_type', 'subject_id']);
            $table->index(['profile_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_entries');
        Schema::dropIfExists('quantity_returns');
        Schema::dropIfExists('money_movements');
        Schema::dropIfExists('commitment_subjects');
        Schema::dropIfExists('quantity_subjects');
        Schema::dropIfExists('money_subjects');
        DB::statement('DROP INDEX IF EXISTS parties_profile_display_name_active_unique');

    }
};
