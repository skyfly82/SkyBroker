<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference')->nullable();
            $table->string('status', 32)->index();
            $table->string('carrier_code')->nullable();
            $table->string('service_code');
            $table->string('tracking_number')->nullable()->index();
            $table->decimal('price_pln', 10, 2)->nullable();

            // receiver
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->string('receiver_email')->nullable();
            $table->string('receiver_street');
            $table->string('receiver_building_number')->nullable();
            $table->string('receiver_apartment_number')->nullable();
            $table->string('receiver_city');
            $table->string('receiver_postal_code');
            $table->string('receiver_country_code', 2);

            // sender
            $table->string('sender_name');
            $table->string('sender_phone');
            $table->string('sender_email')->nullable();
            $table->string('sender_street');
            $table->string('sender_building_number')->nullable();
            $table->string('sender_apartment_number')->nullable();
            $table->string('sender_city');
            $table->string('sender_postal_code');
            $table->string('sender_country_code', 2);

            // parcel
            $table->decimal('parcel_length_cm', 10, 2)->nullable();
            $table->decimal('parcel_width_cm', 10, 2)->nullable();
            $table->decimal('parcel_height_cm', 10, 2)->nullable();
            $table->decimal('parcel_weight_kg', 10, 3);

            $table->string('pickup_point_id')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
