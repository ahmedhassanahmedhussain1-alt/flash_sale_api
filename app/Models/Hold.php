<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
 use App\Models\Product;

class Hold extends Model
{
   protected $guarded = ['id'];

   public function product()
   {
       return $this->belongsTo(Product::class);
   }
    public function order()
    {
        return $this->hasOne(Order::class);
    }

}
