<?php

namespace App\Utils;

use Illuminate\Support\Facades\Log;

class Metric
{
    public static function increment(string $key): void
    {
        // Dummy implementation - just log
        Log::debug("Metric incremented: {$key}");
    }
}