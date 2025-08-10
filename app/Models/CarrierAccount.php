<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarrierAccount extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'carrier_accounts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'carrier_code','name','credentials','is_active'
    ];

    protected $casts = [
        'credentials' => 'array',
        'is_active' => 'boolean',
    ];
}
