<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'payments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id','provider','status','amount_pln','currency','external_payment_id',
        'initiated_at','paid_at','metadata'
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount_pln' => 'decimal:2',
        'initiated_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
