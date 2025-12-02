<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       Product::Create([
           'name' => 'Iphone 14 Pro -Flash Sale',
            'price' => 11.000,
           'stock' => 10,
           'reserved' => 0,
           'sold' => 0,
       ]);
        
    }
}
