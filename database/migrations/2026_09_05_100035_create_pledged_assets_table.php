<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pledged_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type', 60);
            $table->string('description')->nullable();
            $table->decimal('quantity', 20, 4)->nullable();
            $table->string('quantity_mode', 20)->default('countable');
            $table->string('quantity_unit', 30)->nullable();
            $table->bigInteger('estimated_value')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('storage_location')->nullable();
            $table->date('pledged_on')->nullable();
            $table->date('matures_on')->nullable();
            $table->string('status', 30)->default('pledged');
            $table->timestamps();
            $table->index(['matures_on', 'status']);
            $table->index('obligation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pledged_assets');
    }
};
