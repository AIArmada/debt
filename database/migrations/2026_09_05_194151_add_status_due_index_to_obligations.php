<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligations', function (Blueprint $table): void {
            $table->index(['status', 'next_due_on']);
        });
    }

    public function down(): void
    {
        Schema::table('obligations', function (Blueprint $table): void {
            $table->dropIndex(['status', 'next_due_on']);
        });
    }
};
