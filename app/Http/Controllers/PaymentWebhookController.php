<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Hold;
use App\Models\Product;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'idempotency_key' => 'required|string|max:255',
            'order_id' => 'required|uuid',
            'status'          => 'required|in:success,failed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'    => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $idempotencyKey = $request->idempotency_key;
        $orderUuid      = $request->order_id;
        $status         = $request->status;

         if (Payment::where('idempotency_key', $idempotencyKey)->exists()) {
            return response()->json(['message' => 'Already processed'], 200);
        }

        if (!Cache::add("payment:processing:{$idempotencyKey}", true, 300)) {
            return response()->json(['message' => 'Already processing'], 200);
        }

        try {
            DB::transaction(function () use ($orderUuid, $status, $idempotencyKey, $request) {

                $order = Order::where('uuid', $orderUuid)->lockForUpdate()->first();

                if (!$order) {
                    throw new ModelNotFoundException("Order not found");
                }

                 if (in_array($order->status, ['paid', 'cancelled'])) {
                    return;
                }

                $hold = Hold::where('id', $order->hold_id)->lockForUpdate()->first();
                if (!$hold) {
                    throw new \Exception("Associated hold not found");
                }

                if ($hold->status !== 'active') {
                    throw new \Exception("Hold is not active");
                }

                $product = Product::where('id', $hold->product_id)->lockForUpdate()->first();
                if (!$product) {
                    throw new \Exception("Product not found");
                }

                if ($status === 'success') {

                    $order->status = 'paid';
                    $hold->status  = 'used';

                     $product->reserved = max(0, $product->reserved - $hold->quantity);
                    $product->sold     = $product->sold + $hold->quantity;

                } else {

                    $order->status = 'cancelled';
                    $hold->status  = 'expired';

                     $product->reserved = max(0, $product->reserved - $hold->quantity);
                }

                $order->save();
                $hold->save();
                $product->save();

                Payment::create([
                    'idempotency_key' => $idempotencyKey,
                    'order_id'        => $order->id,
                    'status'          => $status,
                    'webhook_data'    => json_encode($request->all()),
                ]);

            $available = $product->stock - $product->reserved - $product->sold;
             Cache::put("product:{$product->id}:available", $available, 60);
            });

            Cache::forget("payment:processing:{$idempotencyKey}");

            return response()->json([
                'message'   => 'Webhook processed',
                'order_id'  => $orderUuid,
                'status'    => $status
            ], 200);

        } catch (ModelNotFoundException $e) {

            Cache::forget("payment:processing:{$idempotencyKey}");
            return response()->json(['error' => 'Order not found'], 500);

        } catch (\Exception $e) {

            Cache::forget("payment:processing:{$idempotencyKey}");

            Log::error("Payment webhook error", [
                'error' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey
            ]);

            return response()->json(['error' => 'Webhook failed'], 500);
        }
    }
}
