<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Models\Hold;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Utils\Metric;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HoldController extends Controller
{
  public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'product_id' => 'required|integer|exists:products,id',
        'quantity' => 'required|integer|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'error' => 'Validation failed',
            'messages' => $validator->errors()
        ], 422);
    }

    $productId = $request->product_id;
    $quantity = $request->quantity;

    $hold = null;

    return DB::transaction(function () use ($productId, $quantity, &$hold) {

        $product = Product::where('id', $productId)
            ->lockForUpdate()
            ->first();

        $available = $product->stock - $product->reserved - $product->sold;

        if ($available < $quantity) {
            abort(409, 'Insufficient stock available');
        }

        $product->reserved += $quantity;
        $product->save();

        $hold = Hold::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $productId,
            'quantity' => $quantity,
            'expires_at' => now()->addMinutes(2),
            'status' => 'active',
        ]);

        return response()->json([
            'hold_id' => $hold->uuid,
            'expires_at' => $hold->expires_at,
            'status' => $hold->status,
        ], 201);

    }, 5);
}
}
?>
