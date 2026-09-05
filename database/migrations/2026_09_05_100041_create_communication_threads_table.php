<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_threads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('status', 30)->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_threads');
    }
};
