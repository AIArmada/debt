<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->decimal('quantity', 20, 4)->nullable();
            $table->string('quantity_effect', 20)->nullable();
            $table->string('unit', 60)->nullable();
            $table->date('occurred_on')->nullable();
            $table->text('note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['obligation_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_events');
    }
};
