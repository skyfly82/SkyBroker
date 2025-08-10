<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipment_labels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('format', 8)->default('A6');
            $table->string('storage_path');
            $table->string('mime_type')->default('application/pdf');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_labels');
    }
};
