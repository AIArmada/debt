<?php

use App\Domain\Enums\PaymentDestinationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_destinations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('label', 255);
            $table->text('details_encrypted');
            $table->boolean('is_verified')->default(false);
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['party_id', 'kind']);
        });

        Schema::getConnection()->statement("ALTER TABLE payment_destinations ADD CONSTRAINT payment_destinations_kind_valid CHECK (kind IN ('".implode("','", array_column(PaymentDestinationKind::cases(), 'value'))."'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_destinations');
    }
};
