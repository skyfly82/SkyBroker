<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cod_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->decimal('amount_pln', 10, 2);
            $table->timestamp('received_at')->nullable();
            $table->timestamp('remitted_at')->nullable();
            $table->string('remittance_account')->nullable();
            $table->string('status', 32)->default('PENDING');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_payments');
    }
};
