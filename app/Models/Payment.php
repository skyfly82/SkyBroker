<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'id',
        'shipment_id',
        'provider',
        'status',
        'amount_pln',
        'currency',
        'initiated_at',
        'completed_at',
    ];

    protected $casts = [
        'amount_pln'   => 'decimal:2',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
