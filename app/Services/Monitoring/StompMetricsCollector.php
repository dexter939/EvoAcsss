<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Persistent STOMP metrics collector using Redis
 * 
 * Provides atomic counters that persist across PHP processes
 */
class StompMetricsCollector
{
    private const PREFIX = 'stomp:metrics:';
    
    private const COUNTERS = [
        'connections_total',
        'connections_active',
        'messages_published',
        'messages_received',
        'messages_acked',
        'messages_nacked',
        'transactions_begun',
        'transactions_committed',
        'transactions_aborted',
        'errors_connection',
        'errors_publish',
        'errors_subscribe',
    ];

    /**
     * Increment a counter atomically
     */
    public static function increment(string $counter, int $amount = 1): int
    {
        if (!in_array($counter, self::COUNTERS)) {
            throw new \InvalidArgumentException("Invalid counter: {$counter}");
        }

        try {
            $key = self::PREFIX . $counter;
            return Redis::incrby($key, $amount);
        } catch (\Exception $e) {
            Log::error("Failed to increment STOMP counter", [
                'counter' => $counter,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Decrement a counter atomically
     */
    public static function decrement(string $counter, int $amount = 1): int
    {
        return self::increment($counter, -$amount);
    }

    /**
     * Get current value of a counter
     */
    public static function get(string $counter): int
    {
        if (!in_array($counter, self::COUNTERS)) {
            throw new \InvalidArgumentException("Invalid counter: {$counter}");
        }

        try {
            $key = self::PREFIX . $counter;
            $value = Redis::get($key);
            return (int) ($value ?? 0);
        } catch (\Exception $e) {
            Log::error("Failed to get STOMP counter", [
                'counter' => $counter,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get all counters
     */
    public static function getAll(): array
    {
        $metrics = [];
        
        foreach (self::COUNTERS as $counter) {
            $metrics[$counter] = self::get($counter);
        }

        return $metrics;
    }

    /**
     * Reset a counter
     */
    public static function reset(string $counter): void
    {
        if (!in_array($counter, self::COUNTERS)) {
            throw new \InvalidArgumentException("Invalid counter: {$counter}");
        }

        try {
            $key = self::PREFIX . $counter;
            Redis::del($key);
        } catch (\Exception $e) {
            Log::error("Failed to reset STOMP counter", [
                'counter' => $counter,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset all counters
     */
    public static function resetAll(): void
    {
        foreach (self::COUNTERS as $counter) {
            self::reset($counter);
        }
    }

    /**
     * Record timing metric
     */
    public static function recordTiming(string $metric, float $milliseconds): void
    {
        try {
            $key = self::PREFIX . "timing:{$metric}";
            
            // Store last 100 timings for average calculation
            Redis::lpush($key, $milliseconds);
            Redis::ltrim($key, 0, 99);
            Redis::expire($key, 3600); // 1 hour TTL
        } catch (\Exception $e) {
            Log::error("Failed to record STOMP timing", [
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get average timing
     */
    public static function getAverageTiming(string $metric): float
    {
        try {
            $key = self::PREFIX . "timing:{$metric}";
            $timings = Redis::lrange($key, 0, -1);
            
            if (empty($timings)) {
                return 0.0;
            }

            $sum = array_sum($timings);
            return round($sum / count($timings), 2);
        } catch (\Exception $e) {
            Log::error("Failed to get STOMP timing average", [
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }
}
