<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->string('currency', 3);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->bigInteger('emergency_reserve_amount')->default(0);
            $table->bigInteger('available_for_obligations_amount')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamps();
            $table->unique(['profile_id', 'starts_on', 'ends_on']);
            $table->index(['profile_id', 'status', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_periods');
    }
};
