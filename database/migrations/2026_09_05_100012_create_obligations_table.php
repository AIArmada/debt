<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('record_id')->constrained('records')->cascadeOnDelete();
            $table->string('direction', 20);
            $table->string('title');
            $table->date('due_on')->nullable();
            $table->string('status', 30)->default('open');
            $table->string('subject_type', 20)->default('money');
            $table->timestamps();
            $table->index(['record_id', 'direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligations');
    }
};
