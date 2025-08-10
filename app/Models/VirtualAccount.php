<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualAccount extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'virtual_accounts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'client_id','iban','is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
