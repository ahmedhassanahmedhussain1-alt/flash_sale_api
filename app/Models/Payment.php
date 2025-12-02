<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
     protected $table = 'payments';

     protected $fillable = [
        'order_id',
        'idempotency_key',
        'status',
        'webhook_data'
    ];

     protected $casts = [
        'webhook_data' => 'array',
    ];

     public function order()
    {
        return $this->belongsTo(Order::class);
    }
}