<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persistent STOMP metrics collector using Database
 * 
 * Provides atomic counters that persist across PHP processes
 */
class StompMetricsCollector
{
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
     * Increment a counter atomically using database
     */
    public static function increment(string $counter, int $amount = 1): int
    {
        if (!in_array($counter, self::COUNTERS)) {
            throw new \InvalidArgumentException("Invalid counter: {$counter}");
        }

        try {
            DB::table('stomp_counters')
                ->where('counter_name', $counter)
                ->update([
                    'value' => DB::raw("value + {$amount}"),
                    'updated_at' => now(),
                ]);
            
            return self::get($counter);
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
            $result = DB::table('stomp_counters')
                ->where('counter_name', $counter)
                ->value('value');
            
            return (int) ($result ?? 0);
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
            DB::table('stomp_counters')
                ->where('counter_name', $counter)
                ->update(['value' => 0, 'updated_at' => now()]);
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
     * Record timing metric (stored in cache table)
     */
    public static function recordTiming(string $metric, float $milliseconds): void
    {
        try {
            // Store in cache table with 1 hour TTL
            $key = "stomp_timing_{$metric}";
            $existing = DB::table('cache')->where('key', $key)->value('value');
            
            $timings = $existing ? json_decode($existing, true) : [];
            $timings[] = $milliseconds;
            
            // Keep last 100 timings
            if (count($timings) > 100) {
                $timings = array_slice($timings, -100);
            }
            
            DB::table('cache')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => json_encode($timings),
                    'expiration' => now()->addHour()->timestamp,
                ]
            );
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
            $key = "stomp_timing_{$metric}";
            $value = DB::table('cache')->where('key', $key)->value('value');
            
            if (!$value) {
                return 0.0;
            }

            $timings = json_decode($value, true);
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
