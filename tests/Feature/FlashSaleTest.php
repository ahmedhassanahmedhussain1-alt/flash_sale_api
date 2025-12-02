<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;
use App\Models\Payment;
use App\Jobs\ReleaseHoldJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

         $this->withoutMiddleware([
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        $this->product = Product::create([
            'name' => 'Flash Sale Product',
            'price' => 100,
            'stock' => 10,
            'sold' => 0,
            'reserved' => 0,
        ]); 
    }

     
    public function test_parallel_holds_no_oversell()
{
$holdRequests = 15;
$createdHolds = 0;

 
for ($i = 0; $i < $holdRequests; $i++) {
    $response = $this->postJson('/api/holds', [
        'product_id' => $this->product->id,
        'quantity' => 1
    ]);

    if ($response->status() === 201) {
        $createdHolds++;
    }
}

 $this->assertEquals(10, $createdHolds, 'Should create exactly 10 holds, overselling occurred!');

$this->product->refresh();

 $this->assertEquals(10, $this->product->reserved, 'Reserved units should equal 10');

 $available = $this->product->stock - $this->product->reserved - $this->product->sold;
$this->assertEquals(0, $available, 'Available stock should be 0');

$this->assertDatabaseCount('holds', $createdHolds);
 

}


    
    public function test_hold_expiry_returns_stock()
    {
         $hold = Hold::create([
            'uuid' => Str::uuid(),
            'product_id' => $this->product->id,
            'quantity' => 5,
            'expires_at' => Carbon::now()->subMinutes(2),  
            'status' => 'active',
        ]);

        $this->product->reserved = 5;
           $this->product->save();

$this->assertEquals(5, $this->product->fresh()->reserved, 'Reserved should be 5 after hold');
         $job = new ReleaseHoldJob();
        $job->handle();

         $this->product->refresh();
         $hold->refresh();

 $this->assertEquals(0, $this->product->reserved, 'Reserved should be 0 after hold expiry'); 
 $this->assertEquals('expired', $hold->status, 'Hold status should be expired');
 
$available = $this->product->stock - $this->product->reserved - $this->product->sold;
$this->assertEquals(10, $available, 'Available stock should be fully released');
    }

    
    public function test_webhook_idempotency()
    {
         $hold = Hold::create([
            'uuid' => Str::uuid(),
            'product_id' => $this->product->id,
            'quantity' => 2,
            'expires_at' => Carbon::now()->addMinutes(2),
            'status' => 'active',
        ]);

         $order = Order::create([
            'uuid' => Str::uuid(),
            'hold_id' => $hold->id,
            'product_id' => $this->product->id,   
            'quantity' => 2,
            'status' => 'prepayment'
        ]);

        $idempotencyKey = 'test_idempotency_key_12345';

        
        $response1 = $this->postJson('/api/payments/webhook', [
            'idempotency_key' => $idempotencyKey,
            'order_id' => $order->uuid,
            'status' => 'success',
        ]);
        $response1->assertStatus(200);

         $response2 = $this->postJson('/api/payments/webhook', [
            'idempotency_key' => $idempotencyKey,
             'order_id' => $order->uuid,
            'status' => 'success',
        ]);
        $response2->assertStatus(200);

         $response3 = $this->postJson('/api/payments/webhook', [
            'idempotency_key' => $idempotencyKey,
            'order_id' => $order->uuid,
            'status' => 'success',
        ]);
        $response3->assertStatus(200);

         $this->assertEquals(
            1, 
            Payment::where('idempotency_key', $idempotencyKey)->count(), 
            'Duplicate payment created - idempotency failed!'
        );

         $order->refresh();
        $this->assertEquals('paid', $order->status, 'Order status incorrect after webhook');

         $this->product->refresh();
        $this->assertEquals(2, $this->product->sold, 'Sold count incorrect');
    }
 
    public function test_webhook_before_order_creation()
    {
        $idempotencyKey = 'early_webhook_key_999';

         $response = $this->postJson('/api/payments/webhook', [
            'idempotency_key' => $idempotencyKey,
            'order_id' => Str::uuid(),   
            'status' => 'success',
        ]);

         $response->assertStatus(500);
        
         $response->assertJson([
            'error' => 'Order not found'
        ]);
    }
}