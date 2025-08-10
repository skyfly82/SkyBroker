<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'shipments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'reference','status','carrier_code','service_code','tracking_number','price_pln',
        'receiver_name','receiver_phone','receiver_email','receiver_street','receiver_building_number',
        'receiver_apartment_number','receiver_city','receiver_postal_code','receiver_country_code',
        'sender_name','sender_phone','sender_email','sender_street','sender_building_number',
        'sender_apartment_number','sender_city','sender_postal_code','sender_country_code',
        'parcel_length_cm','parcel_width_cm','parcel_height_cm','parcel_weight_kg',
        'pickup_point_id','metadata'
    ];

    protected $casts = [
        'status' => ShipmentStatus::class,
        'price_pln' => 'decimal:2',
        'parcel_length_cm' => 'decimal:2',
        'parcel_width_cm' => 'decimal:2',
        'parcel_height_cm' => 'decimal:2',
        'parcel_weight_kg' => 'decimal:3',
        'metadata' => 'array',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function labels()
    {
        return $this->hasMany(ShipmentLabel::class);
    }

    public function trackingEvents()
    {
        return $this->hasMany(TrackingEvent::class);
    }
}
