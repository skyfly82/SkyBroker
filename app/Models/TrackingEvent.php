<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'tracking_events';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id','tracking_number','code','description','occurred_at','location','raw'
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'raw' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
