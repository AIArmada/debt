<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quantity_subjects', function (Blueprint $table): void {
            $table->dropColumn('returned');
        });
    }

    public function down(): void
    {
        Schema::table('quantity_subjects', function (Blueprint $table): void {
            $table->decimal('returned', 18, 4)->default(0)->after('total');
        });
    }
};
