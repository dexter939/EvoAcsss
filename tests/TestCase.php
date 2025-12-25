<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fakes\FakeUspMqttService;
use Tests\Fakes\FakeUspWebSocketService;
use Tests\Fakes\FakeUpnpDiscoveryService;
use Tests\Fakes\FakeParameterDiscoveryService;
use Tests\Fakes\FakeConnectionRequestService;
use App\Services\UspMqttService;
use App\Services\UspWebSocketService;
use App\Services\UpnpDiscoveryService;
use App\Services\ParameterDiscoveryService;
use App\Services\ConnectionRequestService;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, DatabaseTransactions;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutVite();
        
        // Replace real services with fakes to avoid actual network connections
        
        // USP Protocol Services
        $this->app->singleton(UspMqttService::class, function ($app) {
            return new FakeUspMqttService();
        });
        
        $this->app->singleton(UspWebSocketService::class, function ($app) {
            return new FakeUspWebSocketService();
        });
        
        // Discovery and Provisioning Services
        $this->app->singleton(UpnpDiscoveryService::class, function ($app) {
            return new FakeUpnpDiscoveryService();
        });
        
        $this->app->singleton(ParameterDiscoveryService::class, function ($app) {
            return new FakeParameterDiscoveryService();
        });
        
        $this->app->singleton(ConnectionRequestService::class, function ($app) {
            return new FakeConnectionRequestService();
        });
    }

    /**
     * Get authenticated API headers with API key
     */
    protected function apiHeaders(array $additional = []): array
    {
        return array_merge([
            'X-API-Key' => env('ACS_API_KEY', 'test-api-key-for-phpunit-testing'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $additional);
    }

    /**
     * Make authenticated API request
     */
    protected function apiGet(string $uri, array $headers = [])
    {
        return $this->get($uri, $this->apiHeaders($headers));
    }

    /**
     * Make authenticated API POST request
     */
    protected function apiPost(string $uri, array $data = [], array $headers = [])
    {
        return $this->postJson($uri, $data, $this->apiHeaders($headers));
    }

    /**
     * Make authenticated API PUT request
     */
    protected function apiPut(string $uri, array $data = [], array $headers = [])
    {
        return $this->putJson($uri, $data, $this->apiHeaders($headers));
    }

    /**
     * Make authenticated API DELETE request
     */
    protected function apiDelete(string $uri, array $headers = [])
    {
        return $this->deleteJson($uri, [], $this->apiHeaders($headers));
    }

    /**
     * Create TR-069 SOAP envelope for testing
     */
    protected function createTr069SoapEnvelope(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
               '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" ' .
               'xmlns:cwmp="urn:dslforum-org:cwmp-1-0" ' .
               'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ' .
               'xmlns:xsd="http://www.w3.org/2001/XMLSchema">' . "\n" .
               '<soap:Header>' . "\n" .
               '<cwmp:ID soap:mustUnderstand="1">test-id-' . uniqid() . '</cwmp:ID>' . "\n" .
               '</soap:Header>' . "\n" .
               '<soap:Body>' . "\n" .
               $body . "\n" .
               '</soap:Body>' . "\n" .
               '</soap:Envelope>';
    }

    /**
     * Post TR-069 SOAP request
     */
    protected function postTr069Soap(string $uri, string $soapXml, array $cookies = [])
    {
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => ''
        ];
        
        // Add cookies as HTTP Cookie header for reliable transmission in tests
        if (!empty($cookies)) {
            $cookiePairs = [];
            foreach ($cookies as $name => $value) {
                $cookiePairs[] = "{$name}={$value}";
            }
            $headers['Cookie'] = implode('; ', $cookiePairs);
        }
        
        return $this->withHeaders($headers)->call('POST', $uri, [], [], [], [], $soapXml);
    }

    /**
     * Create TR-069 Inform message
     */
    protected function createTr069Inform(array $params = []): string
    {
        $defaults = [
            'serial_number' => 'TEST-' . uniqid(),
            'oui' => '00259E',
            'product_class' => 'IGD',
            'manufacturer' => 'TestManufacturer',
            'software_version' => null,
            'hardware_version' => null,
            'events' => ['0 BOOTSTRAP'],
            'parameters' => []
        ];
        
        $params = array_merge($defaults, $params);
        
        // Build DeviceId section (must have cwmp: prefix)
        $deviceIdXml = '<cwmp:DeviceId>' . "\n" .
                       '<Manufacturer>' . $params['manufacturer'] . '</Manufacturer>' . "\n" .
                       '<OUI>' . $params['oui'] . '</OUI>' . "\n" .
                       '<ProductClass>' . $params['product_class'] . '</ProductClass>' . "\n" .
                       '<SerialNumber>' . $params['serial_number'] . '</SerialNumber>' . "\n";
        
        if ($params['software_version']) {
            $deviceIdXml .= '<SoftwareVersion>' . $params['software_version'] . '</SoftwareVersion>' . "\n";
        }
        if ($params['hardware_version']) {
            $deviceIdXml .= '<HardwareVersion>' . $params['hardware_version'] . '</HardwareVersion>' . "\n";
        }
        
        $deviceIdXml .= '</cwmp:DeviceId>' . "\n";
        
        // Build Event section (support multiple events)
        $eventsXml = '<Event>' . "\n";
        foreach ($params['events'] as $event) {
            $eventsXml .= '<EventStruct><EventCode>' . $event . '</EventCode><CommandKey></CommandKey></EventStruct>' . "\n";
        }
        $eventsXml .= '</Event>' . "\n";
        
        // Build ParameterList section
        $paramListXml = '<ParameterList>' . "\n";
        foreach ($params['parameters'] as $name => $value) {
            $paramListXml .= '<ParameterValueStruct>' . "\n" .
                            '<Name>' . $name . '</Name>' . "\n" .
                            '<Value>' . $value . '</Value>' . "\n" .
                            '</ParameterValueStruct>' . "\n";
        }
        $paramListXml .= '</ParameterList>' . "\n";
        
        $body = '<cwmp:Inform>' . "\n" .
                $deviceIdXml .
                $eventsXml .
                '<MaxEnvelopes>1</MaxEnvelopes>' . "\n" .
                '<CurrentTime>2025-01-01T00:00:00Z</CurrentTime>' . "\n" .
                '<RetryCount>0</RetryCount>' . "\n" .
                $paramListXml .
                '</cwmp:Inform>';
        
        return $this->createTr069SoapEnvelope($body);
    }

    /**
     * Create USP Get request protobuf for testing
     */
    protected function createUspGetRequest(array $paths): array
    {
        return [
            'header' => [
                'msg_id' => 'test-msg-' . uniqid(),
                'msg_type' => 1, // GET
            ],
            'body' => [
                'request' => [
                    'get' => [
                        'param_paths' => $paths,
                    ],
                ],
            ],
        ];
    }

    /**
     * Assert JSON response has correct structure
     */
    protected function assertJsonStructure(array $structure, $response = null)
    {
        if ($response === null) {
            $response = $this->response;
        }
        
        return $response->assertJsonStructure($structure);
    }

    /**
     * Assert response is successful API response
     */
    protected function assertSuccessResponse($response = null)
    {
        if ($response === null) {
            $response = $this->response;
        }
        
        return $response->assertStatus(200)
                        ->assertJsonStructure(['success', 'data']);
    }
}
