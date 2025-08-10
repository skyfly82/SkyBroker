<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('carrier_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('carrier_code');
            $table->string('name');
            $table->json('credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_accounts');
    }
};
