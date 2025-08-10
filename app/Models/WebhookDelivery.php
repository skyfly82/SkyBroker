<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'webhook_deliveries';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'event_name','webhook_url','payload','signature','delivered_at','status','attempts','last_error'
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
