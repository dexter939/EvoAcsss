<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TR262Service;
use App\Models\CpeDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TR262ServiceTest extends TestCase
{
    use RefreshDatabase;

    private TR262Service $service;
    private CpeDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TR262Service::class);
        
        $this->device = CpeDevice::factory()->create([
            'serial_number' => 'TEST-TR262-001',
            'protocol_type' => 'tr069',
        ]);
    }

    public function test_connect_establishes_stomp_connection(): void
    {
        $config = [
            'host' => 'localhost',
            'port' => 61613,
            'login' => 'admin',
            'passcode' => 'secret',
            'version' => '1.2',
        ];

        $result = $this->service->connect($this->device, $config);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('connection_id', $result);
        $this->assertArrayHasKey('session_id', $result);
        $this->assertEquals('1.2', $result['protocol_version']);
    }

    public function test_connect_with_ssl_enabled(): void
    {
        $config = [
            'host' => 'secure.example.com',
            'port' => 61614,
            'login' => 'admin',
            'passcode' => 'secret',
            'ssl' => true,
        ];

        $result = $this->service->connect($this->device, $config);

        $this->assertEquals('success', $result['status']);
    }

    public function test_connect_rejects_unsupported_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported STOMP version');

        $config = [
            'host' => 'localhost',
            'port' => 61613,
            'version' => '2.0',
        ];

        $this->service->connect($this->device, $config);
    }

    public function test_publish_sends_message_to_destination(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];

        $publishResult = $this->service->publish(
            $connectionId,
            '/topic/cpe-management',
            'Test message payload',
            ['priority' => '5']
        );

        $this->assertEquals('success', $publishResult['status']);
        $this->assertArrayHasKey('message_id', $publishResult);
        $this->assertEquals('/topic/cpe-management', $publishResult['destination']);
    }

    public function test_publish_with_invalid_connection_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid connection ID');

        $this->service->publish('invalid_conn_id', '/topic/test', 'message');
    }

    public function test_subscribe_creates_subscription(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];

        $subscribeResult = $this->service->subscribe(
            $connectionId,
            '/topic/device-events',
            ['ack' => 'client']
        );

        $this->assertEquals('success', $subscribeResult['status']);
        $this->assertArrayHasKey('subscription_id', $subscribeResult);
        $this->assertEquals('/topic/device-events', $subscribeResult['destination']);
        $this->assertEquals('client', $subscribeResult['ack_mode']);
    }

    public function test_subscribe_with_selector_filter(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];

        $subscribeResult = $this->service->subscribe(
            $connectionId,
            '/topic/alarms',
            ['selector' => "severity > 3"]
        );

        $this->assertEquals('success', $subscribeResult['status']);
    }

    public function test_unsubscribe_removes_subscription(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];
        
        $subscribeResult = $this->service->subscribe($connectionId, '/topic/test');
        $subscriptionId = $subscribeResult['subscription_id'];

        $unsubscribeResult = $this->service->unsubscribe($subscriptionId);

        $this->assertEquals('success', $unsubscribeResult['status']);
        $this->assertEquals($subscriptionId, $unsubscribeResult['subscription_id']);
    }

    public function test_ack_acknowledges_message(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];
        
        $subscribeResult = $this->service->subscribe($connectionId, '/topic/test', ['ack' => 'client']);
        $subscriptionId = $subscribeResult['subscription_id'];

        $ackResult = $this->service->ack('msg_12345', $subscriptionId);

        $this->assertEquals('success', $ackResult['status']);
        $this->assertEquals('msg_12345', $ackResult['message_id']);
        $this->assertArrayHasKey('acknowledged_at', $ackResult);
    }

    public function test_nack_rejects_message(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];
        
        $subscribeResult = $this->service->subscribe($connectionId, '/topic/test', ['ack' => 'client']);
        $subscriptionId = $subscribeResult['subscription_id'];

        $nackResult = $this->service->nack('msg_12345', $subscriptionId);

        $this->assertEquals('success', $nackResult['status']);
        $this->assertEquals('msg_12345', $nackResult['message_id']);
        $this->assertArrayHasKey('rejected_at', $nackResult);
    }

    public function test_begin_transaction_creates_transaction(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];

        $txResult = $this->service->beginTransaction($connectionId);

        $this->assertEquals('success', $txResult['status']);
        $this->assertArrayHasKey('transaction_id', $txResult);
        $this->assertStringStartsWith('tx_', $txResult['transaction_id']);
    }

    public function test_commit_transaction_completes_transaction(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];
        
        $txResult = $this->service->beginTransaction($connectionId);
        $transactionId = $txResult['transaction_id'];

        $commitResult = $this->service->commitTransaction($transactionId);

        $this->assertEquals('success', $commitResult['status']);
        $this->assertEquals($transactionId, $commitResult['transaction_id']);
        $this->assertArrayHasKey('committed_at', $commitResult);
    }

    public function test_abort_transaction_rolls_back_transaction(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];
        
        $txResult = $this->service->beginTransaction($connectionId);
        $transactionId = $txResult['transaction_id'];

        $abortResult = $this->service->abortTransaction($transactionId);

        $this->assertEquals('success', $abortResult['status']);
        $this->assertEquals($transactionId, $abortResult['transaction_id']);
        $this->assertArrayHasKey('aborted_at', $abortResult);
    }

    public function test_disconnect_closes_connection(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];

        $disconnectResult = $this->service->disconnect($connectionId);

        $this->assertEquals('success', $disconnectResult['status']);
        $this->assertEquals($connectionId, $disconnectResult['connection_id']);
        $this->assertArrayHasKey('disconnected_at', $disconnectResult);
    }

    public function test_disconnect_removes_all_subscriptions(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];
        
        $this->service->subscribe($connectionId, '/topic/test1');
        $this->service->subscribe($connectionId, '/topic/test2');

        $this->service->disconnect($connectionId);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->disconnect($connectionId);
    }

    public function test_get_all_parameters_returns_bbf_compliant_structure(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $this->service->connect($this->device, $config);

        $result = $this->service->getAllParameters($this->device);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Device.STOMP.Enable', $result);
        $this->assertArrayHasKey('Device.STOMP.ConnectionNumberOfEntries', $result);
        $this->assertEquals('true', $result['Device.STOMP.Enable']);
    }

    public function test_get_all_parameters_includes_connection_details(): void
    {
        $config = ['host' => 'stomp.example.com', 'port' => 61613, 'login' => 'test_user'];
        $this->service->connect($this->device, $config);

        $result = $this->service->getAllParameters($this->device);

        $connectionParams = array_filter(array_keys($result), function($key) {
            return str_contains($key, 'Device.STOMP.Connection.');
        });

        $this->assertNotEmpty($connectionParams, 'Should have STOMP Connection parameters');
    }

    public function test_get_connection_stats_returns_statistics(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];
        
        $this->service->subscribe($connectionId, '/topic/test1');
        $this->service->subscribe($connectionId, '/topic/test2');

        $stats = $this->service->getConnectionStats($connectionId);

        $this->assertArrayHasKey('connection_id', $stats);
        $this->assertArrayHasKey('state', $stats);
        $this->assertArrayHasKey('subscriptions_count', $stats);
        $this->assertEquals('connected', $stats['state']);
        $this->assertEquals(2, $stats['subscriptions_count']);
        $this->assertArrayHasKey('uptime_seconds', $stats);
    }

    public function test_get_connection_stats_with_invalid_connection_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid connection ID');

        $this->service->getConnectionStats('invalid_connection_id');
    }

    public function test_is_valid_parameter_accepts_stomp_parameters(): void
    {
        $this->assertTrue($this->service->isValidParameter('Device.STOMP.Enable'));
        $this->assertTrue($this->service->isValidParameter('Device.STOMP.Connection.1.ServerAddress'));
    }

    public function test_is_valid_parameter_rejects_non_stomp_parameters(): void
    {
        $this->assertFalse($this->service->isValidParameter('Device.WiFi.Radio.1.Enable'));
        $this->assertFalse($this->service->isValidParameter('Device.Services.VoiceService.1.Enable'));
    }

    public function test_multiple_connections_from_same_device(): void
    {
        $config1 = ['host' => 'broker1.example.com', 'port' => 61613];
        $config2 = ['host' => 'broker2.example.com', 'port' => 61614];

        $result1 = $this->service->connect($this->device, $config1);
        $result2 = $this->service->connect($this->device, $config2);

        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('success', $result2['status']);
        $this->assertNotEquals($result1['connection_id'], $result2['connection_id']);

        $params = $this->service->getAllParameters($this->device);
        $this->assertEquals(2, (int)$params['Device.STOMP.ConnectionNumberOfEntries']);
    }

    public function test_heart_beat_configuration(): void
    {
        $config = [
            'host' => 'localhost',
            'port' => 61613,
            'heart_beat' => [5000, 10000],
        ];

        $result = $this->service->connect($this->device, $config);

        $this->assertEquals('success', $result['status']);
        
        $stats = $this->service->getConnectionStats($result['connection_id']);
        $this->assertArrayHasKey('last_heartbeat', $stats);
    }

    public function test_virtual_host_support(): void
    {
        $config = [
            'host' => 'localhost',
            'port' => 61613,
            'virtual_host' => '/production',
        ];

        $result = $this->service->connect($this->device, $config);

        $this->assertEquals('success', $result['status']);
        
        $stats = $this->service->getConnectionStats($result['connection_id']);
        $this->assertEquals('/production', $stats['virtual_host']);
    }

    public function test_publish_with_qos_header(): void
    {
        $config = ['host' => 'localhost', 'port' => 61613];
        $result = $this->service->connect($this->device, $config);
        $connectionId = $result['connection_id'];

        $publishResult = $this->service->publish(
            $connectionId,
            '/queue/critical-events',
            'Critical message',
            ['qos' => 'exactly_once', 'persistent' => 'true']
        );

        $this->assertEquals('success', $publishResult['status']);
        $this->assertEquals('exactly_once', $publishResult['qos_level']);
    }
}
