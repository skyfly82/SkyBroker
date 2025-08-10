<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('tracking_number')->index();
            $table->string('code', 64);
            $table->string('description')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('location')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
