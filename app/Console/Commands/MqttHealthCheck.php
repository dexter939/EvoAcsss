<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;
use Illuminate\Support\Facades\Config;

/**
 * MQTT Health Check Command
 * 
 * Validates MQTT broker configuration and tests connection
 * Used for production readiness verification and monitoring
 */
class MqttHealthCheck extends Command
{
    /**
     * Command signature
     */
    protected $signature = 'mqtt:health-check
                          {--timeout=10 : Connection timeout in seconds}
                          {--fail-fast : Exit with error code on failure}';

    /**
     * Command description
     */
    protected $description = 'Validate MQTT broker configuration and test connection';

    /**
     * Execute command
     */
    public function handle(): int
    {
        $this->info('🔍 MQTT Health Check - TR-369 USP Transport');
        $this->newLine();

        $timeout = (int) $this->option('timeout');
        $failFast = $this->option('fail-fast');
        $allPassed = true;

        // Step 1: Validate configuration
        $this->info('Step 1: Validating configuration...');
        $configValid = $this->validateConfiguration();
        
        if (!$configValid) {
            $allPassed = false;
            if ($failFast) {
                return Command::FAILURE;
            }
        }

        $this->newLine();

        // Step 2: Test broker connection
        $this->info('Step 2: Testing broker connection...');
        $connectionValid = $this->testConnection($timeout);
        
        if (!$connectionValid) {
            $allPassed = false;
            if ($failFast) {
                return Command::FAILURE;
            }
        }

        $this->newLine();

        // Final summary
        if ($allPassed) {
            $this->info('✅ MQTT Health Check: ALL TESTS PASSED');
            return Command::SUCCESS;
        } else {
            $this->error('❌ MQTT Health Check: SOME TESTS FAILED');
            return Command::FAILURE;
        }
    }

    /**
     * Validate MQTT configuration
     */
    protected function validateConfiguration(): bool
    {
        $allValid = true;

        // Required configuration keys
        $requiredKeys = [
            'mqtt-client.default_connection.host' => 'MQTT_HOST',
            'mqtt-client.default_connection.port' => 'MQTT_PORT',
            'mqtt-client.default_connection.client_id' => 'MQTT_CLIENT_ID',
        ];

        foreach ($requiredKeys as $configKey => $envKey) {
            $value = Config::get($configKey);
            
            if (empty($value)) {
                $this->error("  ❌ Missing: {$envKey}");
                $allValid = false;
            } else {
                $this->line("  ✅ {$envKey}: {$value}");
            }
        }

        // Optional but recommended for production
        $optionalKeys = [
            'mqtt-client.default_connection.authentication.username' => 'MQTT_AUTH_USERNAME',
            'mqtt-client.default_connection.authentication.password' => 'MQTT_AUTH_PASSWORD',
            'mqtt-client.default_connection.tls.enabled' => 'MQTT_TLS_ENABLED',
            'mqtt-client.default_connection.reconnection_settings.enabled' => 'MQTT_AUTO_RECONNECT_ENABLED',
        ];

        $this->newLine();
        $this->line('  Optional configuration (recommended for production):');
        
        foreach ($optionalKeys as $configKey => $envKey) {
            $value = Config::get($configKey);
            $isConfigured = !empty($value) || $value === false;
            
            if ($isConfigured) {
                $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                if ($envKey === 'MQTT_AUTH_PASSWORD') {
                    $displayValue = '***';
                }
                $this->line("  ✅ {$envKey}: {$displayValue}");
            } else {
                $this->line("  ⚠️  {$envKey}: not configured");
            }
        }

        return $allValid;
    }

    /**
     * Test MQTT broker connection
     */
    protected function testConnection(int $timeout): bool
    {
        $host = Config::get('mqtt-client.default_connection.host');
        $port = Config::get('mqtt-client.default_connection.port');
        
        $this->line("  Testing connection to {$host}:{$port} (timeout: {$timeout}s)...");

        try {
            // Set connection timeout
            Config::set('mqtt-client.default_connection.connection_settings.connect_timeout', $timeout);
            
            // Get MQTT client and force actual connection
            $mqtt = MQTT::connection();
            
            // Force connection by publishing to test topic
            // This actually triggers network I/O and will throw on failure
            $testTopic = 'acs/health-check/' . time();
            $testPayload = 'health-check-' . time();
            
            $this->line("  Attempting publish to test topic: {$testTopic}");
            $mqtt->publish($testTopic, $testPayload, 0); // QoS 0 for speed
            
            $this->info("  ✅ MQTT broker connection successful");
            $this->line("  ✅ Publish operation completed");
            
            // Test topic structure
            $this->newLine();
            $this->line('  USP Topic Structure (BBF TR-369):');
            $controllerTopic = Config::get('usp.mqtt.controller_topic_pattern', 'usp/controller');
            $agentTopic = Config::get('usp.mqtt.agent_topic_pattern', 'usp/agent');
            $this->line("    Controller: {$controllerTopic}/#");
            $this->line("    Agent: {$agentTopic}/{device_id}");
            
            // Disconnect cleanly
            try {
                $mqtt->disconnect();
                $this->line("  ✅ Disconnected cleanly");
            } catch (\Exception $e) {
                // Ignore disconnect errors
            }
            
            return true;
            
        } catch (\PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException $e) {
            $this->error("  ❌ Connection to broker failed: {$e->getMessage()}");
            $this->provideTroubleshootingHints($host, $port, 'connection_refused');
            return false;
            
        } catch (\PhpMqtt\Client\Exceptions\DataTransferException $e) {
            $this->error("  ❌ Data transfer failed: {$e->getMessage()}");
            $this->provideTroubleshootingHints($host, $port, 'data_transfer');
            return false;
            
        } catch (\Exception $e) {
            $this->error("  ❌ Connection test failed: {$e->getMessage()}");
            $this->provideTroubleshootingHints($host, $port, 'general');
            return false;
        }
    }

    /**
     * Provide troubleshooting hints based on error type
     */
    protected function provideTroubleshootingHints(string $host, int $port, string $errorType): void
    {
        $this->newLine();
        $this->warn('  Troubleshooting:');
        
        if ($errorType === 'connection_refused') {
            $this->line('    1. Verify MQTT broker is running: systemctl status mosquitto');
            $this->line("    2. Check firewall allows port {$port}: sudo ufw status");
            $this->line("    3. Test connection: mosquitto_sub -h {$host} -p {$port} -t test");
            $this->line('    4. Review broker logs: tail -f /var/log/mosquitto/mosquitto.log');
        } elseif ($errorType === 'data_transfer') {
            $this->line('    1. Check TLS configuration if enabled');
            $this->line('    2. Verify authentication credentials');
            $this->line('    3. Check broker ACL rules');
            $this->line('    4. Test with mosquitto_pub:');
            $this->line("       mosquitto_pub -h {$host} -p {$port} -t test -m 'hello'");
        } else {
            $this->line('    1. Verify all MQTT environment variables are set');
            $this->line('    2. Check .env file for typos');
            $this->line('    3. Review config/mqtt-client.php');
            $this->line('    4. Enable MQTT logging: MQTT_ENABLE_LOGGING=true');
        }
    }
}
