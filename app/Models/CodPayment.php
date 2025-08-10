<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodPayment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'cod_payments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id','amount_pln','received_at','remitted_at','remittance_account','status','metadata'
    ];

    protected $casts = [
        'amount_pln' => 'decimal:2',
        'received_at' => 'datetime',
        'remitted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
