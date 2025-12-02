<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
 

class ProductController extends Controller
{
    
    public function show(int $id)
    {
         
        $product = Cache::remember("product_{$id}", 5, function() use ($id) {
            return Product::find($id);
        });

         if (!$product) {
            return response()->json([
                'error' => 'Product not found'
            ], 404);
        }

         return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
             'available_stock' => $product->stock - $product->reserved - $product->sold,
        ], 200);
    }
}