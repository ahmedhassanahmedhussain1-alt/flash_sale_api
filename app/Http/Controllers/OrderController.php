<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Hold;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Utils\Metric;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'hold_id' => 'required|uuid|exists:holds,uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $holdUuid = $request->hold_id;

         $hold = Hold::where('uuid', $holdUuid)->first();

        if (!$hold) {
            return response()->json(['error' => 'Hold not found'], 500);
        }

        if ($hold->expires_at <= now()) {
            return response()->json(['error' => 'Hold expired'], 410);
        }

        if (Order::where('hold_id', $hold->id)->exists()) {
            return response()->json(['error' => 'Hold already used'], 409);
        }
        if ($hold->status !== 'active') {
    return response()->json(['error' => 'Hold is not active'], 409);
         }

        try {
             $order = DB::transaction(function () use ($hold) {
                return Order::create([
                    'uuid'    => Str::uuid(),
                    'hold_id' => $hold->id,
                    'status'  => 'prepayment',
                ]);
            });

            
            Metric::increment('orders.created');

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'hold_id'  => $hold->id,
            ]);

            return response()->json([
                'order_id' => $order->uuid,
                'status'   => $order->status,
                'hold_id'  => $hold->uuid,
                'quantity' => $hold->quantity,
            ], 201);

        } catch (\Exception $e) {

            Metric::increment('orders.failed');

            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'hold_id' => $holdUuid,
            ]);

            return response()->json([
                'error' => 'Failed to create order',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
