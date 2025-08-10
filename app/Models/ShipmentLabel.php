<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentLabel extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'shipment_labels';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id','format','storage_path','mime_type','size_bytes'
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
