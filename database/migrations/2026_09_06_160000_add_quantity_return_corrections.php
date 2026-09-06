<?php

use App\Domain\Enums\MovementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quantity_returns', function (Blueprint $table): void {
            $table->string('status', 20)->default(MovementStatus::Confirmed->value)->after('note');
            $table->string('void_reason')->nullable()->after('status');
            $table->index(['obligation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('quantity_returns', function (Blueprint $table): void {
            $table->dropIndex(['obligation_id', 'status']);
            $table->dropColumn(['status', 'void_reason']);
        });
    }
};
