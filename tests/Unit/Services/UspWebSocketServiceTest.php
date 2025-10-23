<?php

namespace Tests\Unit\Services;

use App\Services\UspWebSocketService;
use App\Services\UspMessageService;
use App\Models\CpeDevice;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * @group skip
 * Skipped temporarily due to Mockery teardown issues
 */
class UspWebSocketServiceTest extends TestCase
{
    private UspWebSocketService $service;
    private $mockMessageService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockMessageService = Mockery::mock(UspMessageService::class);
        $this->service = new UspWebSocketService($this->mockMessageService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_get_request_builds_correct_structure(): void
    {
        $device = Mockery::mock(CpeDevice::class);
        $device->shouldReceive('getAttribute')->with('websocket_client_id')->andReturn('ws-client-123');
        $device->shouldReceive('getAttribute')->with('usp_endpoint_id')->andReturn('proto::device-123');

        $paths = ['Device.DeviceInfo.', 'Device.WiFi.'];
        $msgId = 'test-get-001';

        // Mock message creation
        $mockMsg = Mockery::mock(\Usp\Msg::class);
        $mockHeader = Mockery::mock(\Usp\Header::class);
        $mockHeader->shouldReceive('getMsgId')->andReturn($msgId);
        $mockMsg->shouldReceive('getHeader')->andReturn($mockHeader);

        $this->mockMessageService
            ->shouldReceive('createGetMessage')
            ->with($paths, $msgId)
            ->once()
            ->andReturn($mockMsg);

        $mockRecord = Mockery::mock(\Usp_record\Record::class);
        $this->mockMessageService
            ->shouldReceive('wrapInRecord')
            ->once()
            ->andReturn($mockRecord);

        $this->mockMessageService
            ->shouldReceive('serializeRecord')
            ->once()
            ->andReturn('binary-data');

        // Execute - will fail to send but validates structure
        $result = $this->service->sendGetRequest($device, $paths, $msgId);

        // Verify mocks were called
        $this->mockMessageService->shouldHaveReceived('createGetMessage');
    }

    public function test_send_set_request_with_parameters(): void
    {
        $device = Mockery::mock(CpeDevice::class);
        $device->shouldReceive('getAttribute')->with('websocket_client_id')->andReturn('ws-client-456');
        $device->shouldReceive('getAttribute')->with('usp_endpoint_id')->andReturn('proto::device-456');

        $parameters = [
            'Device.ManagementServer.' => [
                'PeriodicInformInterval' => '600'
            ]
        ];
        $msgId = 'test-set-002';

        $mockMsg = Mockery::mock(\Usp\Msg::class);
        $mockHeader = Mockery::mock(\Usp\Header::class);
        $mockHeader->shouldReceive('getMsgId')->andReturn($msgId);
        $mockMsg->shouldReceive('getHeader')->andReturn($mockHeader);

        $this->mockMessageService
            ->shouldReceive('createSetMessage')
            ->with($parameters, true, $msgId)
            ->once()
            ->andReturn($mockMsg);

        $mockRecord = Mockery::mock(\Usp_record\Record::class);
        $this->mockMessageService
            ->shouldReceive('wrapInRecord')
            ->once()
            ->andReturn($mockRecord);

        $this->mockMessageService
            ->shouldReceive('serializeRecord')
            ->once()
            ->andReturn('binary-data');

        $result = $this->service->sendSetRequest($device, $parameters, $msgId);

        $this->mockMessageService->shouldHaveReceived('createSetMessage');
    }

    public function test_send_delete_request_with_multiple_paths(): void
    {
        $device = Mockery::mock(CpeDevice::class);
        $device->shouldReceive('getAttribute')->with('websocket_client_id')->andReturn('ws-client-789');
        $device->shouldReceive('getAttribute')->with('usp_endpoint_id')->andReturn('proto::device-789');

        $objectPaths = [
            'Device.WiFi.SSID.3.',
            'Device.WiFi.SSID.4.'
        ];
        $msgId = 'test-delete-003';

        $mockMsg = Mockery::mock(\Usp\Msg::class);
        $mockHeader = Mockery::mock(\Usp\Header::class);
        $mockHeader->shouldReceive('getMsgId')->andReturn($msgId);
        $mockMsg->shouldReceive('getHeader')->andReturn($mockHeader);

        $this->mockMessageService
            ->shouldReceive('createDeleteMessage')
            ->with($objectPaths, false, $msgId)
            ->once()
            ->andReturn($mockMsg);

        $mockRecord = Mockery::mock(\Usp_record\Record::class);
        $this->mockMessageService
            ->shouldReceive('wrapInRecord')
            ->once()
            ->andReturn($mockRecord);

        $this->mockMessageService
            ->shouldReceive('serializeRecord')
            ->once()
            ->andReturn('binary-data');

        $result = $this->service->sendDeleteRequest($device, $objectPaths, $msgId);

        $this->mockMessageService->shouldHaveReceived('createDeleteMessage');
    }

    public function test_send_operate_request_with_command(): void
    {
        $device = Mockery::mock(CpeDevice::class);
        $device->shouldReceive('getAttribute')->with('websocket_client_id')->andReturn('ws-client-100');
        $device->shouldReceive('getAttribute')->with('usp_endpoint_id')->andReturn('proto::device-100');

        $command = 'Device.Reboot()';
        $commandArgs = [];
        $msgId = 'test-operate-004';

        $mockMsg = Mockery::mock(\Usp\Msg::class);
        $mockHeader = Mockery::mock(\Usp\Header::class);
        $mockHeader->shouldReceive('getMsgId')->andReturn($msgId);
        $mockMsg->shouldReceive('getHeader')->andReturn($mockHeader);

        $this->mockMessageService
            ->shouldReceive('createOperateMessage')
            ->with($command, $commandArgs, $msgId)
            ->once()
            ->andReturn($mockMsg);

        $mockRecord = Mockery::mock(\Usp_record\Record::class);
        $this->mockMessageService
            ->shouldReceive('wrapInRecord')
            ->once()
            ->andReturn($mockRecord);

        $this->mockMessageService
            ->shouldReceive('serializeRecord')
            ->once()
            ->andReturn('binary-data');

        $result = $this->service->sendOperateRequest($device, $command, $commandArgs, $msgId);

        $this->mockMessageService->shouldHaveReceived('createOperateMessage');
    }

    public function test_get_connected_devices_returns_array(): void
    {
        $devices = $this->service->getConnectedDevices();

        $this->assertIsArray($devices);
    }

    public function test_is_device_connected_returns_boolean(): void
    {
        $device = Mockery::mock(CpeDevice::class);
        $device->shouldReceive('getAttribute')->with('websocket_client_id')->andReturn('ws-test');

        $result = $this->service->isDeviceConnected($device);

        $this->assertIsBool($result);
    }
}
