<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Payment_Webhook;

class Order extends Model
{
    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }
    public function paymentWebhooks()
    {
        return $this->hasMany(Payment_Webhook::class);
    }
}
