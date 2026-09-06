<?php

use App\Domain\Enums\ApiTokenAbility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->json('abilities')
                ->default(json_encode([
                    ApiTokenAbility::Read->value,
                    ApiTokenAbility::Write->value,
                ], JSON_THROW_ON_ERROR))
                ->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->dropColumn('abilities');
        });
    }
};
