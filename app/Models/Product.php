<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Hold;
use App\Models\Order;

class Product extends Model
{
    protected $guarded = ['id'];
    public function holds()
    {
        return $this->hasMany(Hold::class);
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

}
