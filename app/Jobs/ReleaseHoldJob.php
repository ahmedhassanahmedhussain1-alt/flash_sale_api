<?php

namespace App\Jobs;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ReleaseHoldJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info('ReleaseHoldJob started');

        $now = Carbon::now();
        $releasedCount = 0;

        $expiredHolds = Hold::where('status', 'active')
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($expiredHolds as $hold) {
            try {
                DB::transaction(function () use ($hold, &$releasedCount) {

                    $lockedHold = Hold::where('id', $hold->id)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();

                    if (!$lockedHold) {
                        Log::warning('Hold already processed or missing', ['hold_id' => $hold->id]);
                        return;
                    }

                    $product = Product::where('id', $lockedHold->product_id)
                        ->lockForUpdate()
                        ->first();

                    Log::info('Before releasing hold', [
                        'hold_id' => $lockedHold->id,
                        'product_id' => $product->id,
                        'reserved_before' => $product->reserved
                    ]);

                    $product->reserved = max(0, $product->reserved - $lockedHold->quantity);
                    $product->save();

                    $lockedHold->update(['status' => 'expired']);

                    Log::info('After releasing hold', [
                        'hold_id' => $lockedHold->id,
                        'product_id' => $product->id,
                        'reserved_after' => $product->reserved,
                        'hold_status' => $lockedHold->status
                    ]);

                    $available = $product->stock - $product->reserved - $product->sold;
                    Cache::put("product:{$product->id}:available", $available, 60);

                    $releasedCount++;

                }, 5); // retry transaction
            } catch (\Exception $e) {
                Log::error('Failed to release expired hold', [
                    'hold_id' => $hold->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('ReleaseHoldJob completed', [
            'released_count' => $releasedCount
        ]);
    }
}
?>
