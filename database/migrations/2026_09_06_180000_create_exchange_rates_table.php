<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->date('rated_on');
            $table->string('source', 255);
            $table->timestamps();
            $table->unique(['from_currency', 'to_currency', 'rated_on']);
            $table->index(['from_currency', 'to_currency', 'rated_on']);
        });

        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_rate_positive CHECK (rate > 0)');
        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_distinct_currencies CHECK (from_currency <> to_currency)');
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
