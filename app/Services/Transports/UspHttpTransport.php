<?php

namespace App\Services\Transports;

use App\Models\CpeDevice;
use App\Services\UspMessageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Usp\Msg;

/**
 * USP HTTP Transport (MTP) - BBF TR-369 Compliant
 * 
 * Implements HTTP-based Message Transfer Protocol for USP communication
 * Manages HTTP POST requests/responses with Protocol Buffers encoding
 * 
 * Specification: BBF TR-369 Section 4.3 - HTTP Binding
 */
class UspHttpTransport
{
    protected UspMessageService $uspService;
    
    public function __construct(UspMessageService $uspService)
    {
        $this->uspService = $uspService;
    }
    
    /**
     * Send USP message via HTTP POST
     * 
     * @param CpeDevice $device Target device with connection_request_url
     * @param Msg $message USP Protocol Buffers message
     * @param string $msgId Message ID for tracking
     * @return array Response data with status
     * @throws \Exception If HTTP request fails
     */
    public function sendMessage(CpeDevice $device, Msg $message, string $msgId): array
    {
        // Validate HTTP configuration
        if (empty($device->connection_request_url)) {
            throw new \Exception('HTTP connection URL not configured for device');
        }
        
        // Wrap message in USP Record
        $record = $this->uspService->wrapInRecord(
            $message,
            config('usp.controller_endpoint_id', 'proto::acs-controller-001'),
            $device->usp_endpoint_id ?? 'proto::device-' . $device->id
        );
        
        // Serialize Record to binary Protocol Buffers format
        $binaryPayload = $record->serializeToString();
        
        Log::info('USP HTTP: Sending message', [
            'device_id' => $device->id,
            'msg_id' => $msgId,
            'url' => $device->connection_request_url,
            'payload_size' => strlen($binaryPayload)
        ]);
        
        // Send HTTP POST with USP-specific headers
        // Use withBody() + send('POST') to transmit raw binary data
        // Note: Using ->post() after ->withBody() can override the binary payload
        $response = Http::withHeaders([
            'Content-Type' => 'application/vnd.bbf.usp.msg',
            'Accept' => 'application/vnd.bbf.usp.msg'
        ])
        ->withBody($binaryPayload, 'application/vnd.bbf.usp.msg')
        ->timeout(30)
        ->send('POST', $device->connection_request_url);
        
        // Check HTTP response status
        if (!$response->successful()) {
            Log::error('USP HTTP: Request failed', [
                'device_id' => $device->id,
                'msg_id' => $msgId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            throw new \Exception("HTTP request failed with status {$response->status()}");
        }
        
        Log::info('USP HTTP: Message sent successfully', [
            'device_id' => $device->id,
            'msg_id' => $msgId,
            'response_status' => $response->status(),
            'response_size' => strlen($response->body())
        ]);
        
        return [
            'msg_id' => $msgId,
            'status' => 'sent',
            'transport' => 'http',
            'http_status' => $response->status(),
            'response_body' => $response->body()
        ];
    }
    
    /**
     * Send USP Get request
     * 
     * @param CpeDevice $device Target device
     * @param array $paramPaths Array of parameter paths to retrieve
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendGetRequest(CpeDevice $device, array $paramPaths, ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createGetMessage($paramPaths, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Send USP Set request
     * 
     * @param CpeDevice $device Target device
     * @param array $updateObjects Object paths with parameters to set
     * @param bool $allowPartial Allow partial updates
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendSetRequest(CpeDevice $device, array $updateObjects, bool $allowPartial = false, ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createSetMessage($updateObjects, $allowPartial, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Send USP Add request
     * 
     * @param CpeDevice $device Target device
     * @param string $objectPath Object path to create
     * @param array $paramSettings Parameters for new object
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendAddRequest(CpeDevice $device, string $objectPath, array $paramSettings, ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createAddMessage($objectPath, $paramSettings, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Send USP Delete request
     * 
     * @param CpeDevice $device Target device
     * @param array $objectPaths Object paths to delete
     * @param bool $allowPartial Allow partial deletion
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendDeleteRequest(CpeDevice $device, array $objectPaths, bool $allowPartial = false, ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createDeleteMessage($objectPaths, $allowPartial, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Send USP Operate request
     * 
     * @param CpeDevice $device Target device
     * @param string $command Command path (e.g., 'Device.Reboot()')
     * @param array $commandArgs Command arguments
     * @param string|null $msgId Optional message ID
     * @return array Response data
     */
    public function sendOperateRequest(CpeDevice $device, string $command, array $commandArgs = [], ?string $msgId = null): array
    {
        $msgId = $msgId ?? $this->generateMessageId();
        $message = $this->uspService->createOperateMessage($command, $commandArgs, $msgId);
        
        return $this->sendMessage($device, $message, $msgId);
    }
    
    /**
     * Generate unique message ID
     * 
     * @return string UUID message ID
     */
    protected function generateMessageId(): string
    {
        return 'msg-' . uniqid() . '-' . bin2hex(random_bytes(4));
    }
    
    /**
     * Parse USP response from HTTP body
     * 
     * @param string $binaryPayload Binary Protocol Buffers data
     * @return \Usp_record\Record|null Parsed USP Record or null on error
     */
    public function parseResponse(string $binaryPayload): ?\Usp_record\Record
    {
        try {
            return $this->uspService->deserializeRecord($binaryPayload);
        } catch (\Exception $e) {
            Log::error('USP HTTP: Failed to parse response', [
                'error' => $e->getMessage(),
                'payload_size' => strlen($binaryPayload)
            ]);
            return null;
        }
    }
}
