<?php

namespace Tests\Unit\Services;

use App\Services\UspMqttService;
use App\Services\UspMessageService;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * @group skip
 * Skipped temporarily due to Mockery teardown issues
 */
class UspMqttServiceTest extends TestCase
{
    private UspMqttService $service;
    private $mockMessageService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockMessageService = Mockery::mock(UspMessageService::class);
        $this->service = new UspMqttService($this->mockMessageService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_topic_for_device_request(): void
    {
        $endpointId = 'proto::device-12345';
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildTopicForDevice');
        $method->setAccessible(true);

        $topic = $method->invokeArgs($this->service, [$endpointId]);

        $this->assertStringContainsString($endpointId, $topic);
        $this->assertStringStartsWith('usp/request/', $topic);
    }

    public function test_build_response_topic(): void
    {
        $endpointId = 'proto::controller-1';
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildResponseTopic');
        $method->setAccessible(true);

        $topic = $method->invokeArgs($this->service, [$endpointId]);

        $this->assertStringContainsString($endpointId, $topic);
        $this->assertStringStartsWith('usp/response/', $topic);
    }

    public function test_get_endpoint_id_from_topic(): void
    {
        $endpointId = 'proto::device-67890';
        $topic = "usp/request/{$endpointId}";
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractEndpointIdFromTopic');
        $method->setAccessible(true);

        $extracted = $method->invokeArgs($this->service, [$topic]);

        $this->assertEquals($endpointId, $extracted);
    }

    public function test_qos_level_is_at_least_once(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('qos');
        $property->setAccessible(true);

        $qos = $property->getValue($this->service);

        // QoS 1 = At least once delivery
        $this->assertGreaterThanOrEqual(1, $qos);
    }
}
