<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StompMetric extends Model
{
    protected $table = 'stomp_metrics';

    public $timestamps = false;

    protected $fillable = [
        'collected_at',
        'connections_total',
        'connections_active',
        'connections_idle',
        'connections_failed',
        'messages_published',
        'messages_received',
        'messages_acked',
        'messages_nacked',
        'messages_pending_ack',
        'transactions_begun',
        'transactions_committed',
        'transactions_aborted',
        'subscriptions_total',
        'subscriptions_active',
        'avg_publish_latency_ms',
        'avg_ack_latency_ms',
        'messages_per_second',
        'errors_connection',
        'errors_publish',
        'errors_subscribe',
        'errors_timeout',
        'broker_stats',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'broker_stats' => 'array',
        'avg_publish_latency_ms' => 'decimal:2',
        'avg_ack_latency_ms' => 'decimal:2',
        'messages_per_second' => 'decimal:2',
    ];

    /**
     * Get metrics for a specific time range
     */
    public static function getMetricsForRange(\DateInterval $interval): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('collected_at', '>=', now()->sub($interval))
            ->orderBy('collected_at', 'desc')
            ->get();
    }

    /**
     * Get latest snapshot
     */
    public static function getLatest(): ?self
    {
        return self::orderBy('collected_at', 'desc')->first();
    }

    /**
     * Calculate message rate over time window
     */
    public static function calculateMessageRate(\DateInterval $interval): float
    {
        $metrics = self::getMetricsForRange($interval);
        
        if ($metrics->count() < 2) {
            return 0.0;
        }

        $first = $metrics->last();
        $last = $metrics->first();
        
        $messagesDelta = ($last->messages_published + $last->messages_received) - 
                        ($first->messages_published + $first->messages_received);
        
        $timeDelta = $last->collected_at->diffInSeconds($first->collected_at);
        
        return $timeDelta > 0 ? round($messagesDelta / $timeDelta, 2) : 0.0;
    }
}
