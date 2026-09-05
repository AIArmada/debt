<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label')->nullable();
            $table->text('address_line_1');
            $table->text('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('purpose', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('visibility', 24)->default('private');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['party_id', 'purpose', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_addresses');
    }
};
