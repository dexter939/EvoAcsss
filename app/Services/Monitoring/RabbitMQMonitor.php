<?php

namespace App\Services\Monitoring;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * RabbitMQ Management API client for broker introspection
 * 
 * Provides real-time broker statistics:
 * - Active connections
 * - Queue stats
 * - Message rates
 * - Node health
 */
class RabbitMQMonitor
{
    private Client $client;
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        $host = config('stomp.rabbitmq.management_host', 'localhost');
        $port = config('stomp.rabbitmq.management_port', 15672);
        
        $this->baseUrl = "http://{$host}:{$port}/api";
        $this->username = config('stomp.rabbitmq.username', 'guest');
        $this->password = config('stomp.rabbitmq.password', 'guest');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'auth' => [$this->username, $this->password],
            'timeout' => 5,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Get all connections
     */
    public function getConnections(): array
    {
        try {
            $response = $this->client->get('/connections');
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            \App\Services\Monitoring\StompMetricsCollector::increment('errors_broker_unavailable');
            Log::error("Failed to get RabbitMQ connections - broker unavailable", [
                'error' => $e->getMessage(),
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error("Failed to get RabbitMQ connections", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get connection count
     */
    public function getConnectionCount(): int
    {
        $connections = $this->getConnections();
        return count($connections);
    }

    /**
     * Get all queues
     * 
     * Safe method - catches exceptions and returns empty array
     */
    public function getQueues(): array
    {
        try {
            $response = $this->client->get('/queues');
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            \App\Services\Monitoring\StompMetricsCollector::increment('errors_broker_unavailable');
            Log::error("Failed to get RabbitMQ queues - broker unavailable", [
                'error' => $e->getMessage(),
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error("Failed to get RabbitMQ queues", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get overview statistics
     * 
     * @throws \GuzzleHttp\Exception\ConnectException
     * @throws \GuzzleHttp\Exception\RequestException
     */
    public function getOverview(): array
    {
        $response = $this->client->get('/overview');
        $data = json_decode($response->getBody()->getContents(), true);
        
        return [
            'connections' => $data['object_totals']['connections'] ?? 0,
            'channels' => $data['object_totals']['channels'] ?? 0,
            'queues' => $data['object_totals']['queues'] ?? 0,
            'consumers' => $data['object_totals']['consumers'] ?? 0,
            'messages' => $data['queue_totals']['messages'] ?? 0,
            'messages_ready' => $data['queue_totals']['messages_ready'] ?? 0,
            'messages_unacknowledged' => $data['queue_totals']['messages_unacknowledged'] ?? 0,
            'message_stats' => $data['message_stats'] ?? [],
        ];
    }

    /**
     * Get node information
     * 
     * Safe method - catches exceptions and returns empty array
     */
    public function getNodes(): array
    {
        try {
            $response = $this->client->get('/nodes');
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            \App\Services\Monitoring\StompMetricsCollector::increment('errors_broker_unavailable');
            Log::error("Failed to get RabbitMQ nodes - broker unavailable", [
                'error' => $e->getMessage(),
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error("Failed to get RabbitMQ nodes", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check broker health
     */
    public function isHealthy(): bool
    {
        try {
            $overview = $this->getOverview();
            return !empty($overview);
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Connection refused or network unreachable
            \App\Services\Monitoring\StompMetricsCollector::increment('errors_broker_unavailable');
            Log::warning("RabbitMQ broker unavailable", [
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Timeout or HTTP error
            if ($e->getCode() === CURLE_OPERATION_TIMEDOUT) {
                \App\Services\Monitoring\StompMetricsCollector::increment('errors_broker_timeout');
            } else {
                \App\Services\Monitoring\StompMetricsCollector::increment('errors_broker_unavailable');
            }
            Log::warning("RabbitMQ broker error", [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return false;
        } catch (\Exception $e) {
            \App\Services\Monitoring\StompMetricsCollector::increment('errors_broker_unavailable');
            Log::error("RabbitMQ health check failed", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get message rates (msg/sec)
     * 
     * Safe method - catches exceptions and returns zeros
     */
    public function getMessageRates(): array
    {
        try {
            $overview = $this->getOverview();
            $stats = $overview['message_stats'] ?? [];
            
            return [
                'publish_rate' => $stats['publish_details']['rate'] ?? 0.0,
                'deliver_rate' => $stats['deliver_details']['rate'] ?? 0.0,
                'ack_rate' => $stats['ack_details']['rate'] ?? 0.0,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get RabbitMQ message rates", [
                'error' => $e->getMessage(),
            ]);
            return [
                'publish_rate' => 0.0,
                'deliver_rate' => 0.0,
                'ack_rate' => 0.0,
            ];
        }
    }

    /**
     * Get comprehensive broker statistics
     * 
     * Safe method - handles broker unavailability gracefully
     */
    public function getBrokerStats(): array
    {
        try {
            $overview = $this->getOverview();
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            StompMetricsCollector::increment('errors_broker_unavailable');
            Log::warning("getBrokerStats - broker unavailable", [
                'error' => $e->getMessage(),
            ]);
            $overview = [];
        } catch (\Exception $e) {
            Log::error("getBrokerStats - overview failed", [
                'error' => $e->getMessage(),
            ]);
            $overview = [];
        }
        
        $nodes = $this->getNodes();
        $rates = $this->getMessageRates();
        
        return [
            'overview' => $overview,
            'nodes' => $nodes,
            'rates' => $rates,
            'healthy' => $this->isHealthy(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
