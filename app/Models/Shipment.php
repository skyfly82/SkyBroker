<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $table = 'shipments';

    protected $fillable = [
        'id',
        'service_code',
        'carrier',
        'tracking_number',
        'status',
        'price_pln',
        'receiver_name',
        'receiver_phone',
        'receiver_street',
        'receiver_city',
        'receiver_postal_code',
        'receiver_country_code',
        'sender_name',
        'sender_phone',
        'sender_street',
        'sender_city',
        'sender_postal_code',
        'sender_country_code',
        'parcel_weight_kg',
    ];

    protected $casts = [
        'status'       => ShipmentStatus::class,
        'price_pln'    => 'decimal:2',
        'parcel_weight_kg' => 'decimal:3',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
