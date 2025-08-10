<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manifest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'manifests';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'carrier_code','manifest_date','file_path'
    ];

    protected $casts = [
        'manifest_date' => 'date',
    ];
}
