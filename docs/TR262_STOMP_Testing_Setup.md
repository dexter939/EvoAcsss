# TR-262 STOMP Testing Setup Guide

## Overview
This guide explains how to set up STOMP brokers for testing the TR-262 CWMP-STOMP Binding implementation.

## Supported Brokers
The TR-262 implementation (via stomp-php v5.1.3) supports:
- **RabbitMQ** (with STOMP plugin)
- **ActiveMQ** 
- **Apache Apollo**
- **Apache Artemis**

## Quick Start - RabbitMQ (Recommended)

### Docker Setup
```bash
# Run RabbitMQ with STOMP plugin enabled
docker run -d \
  --name rabbitmq-stomp \
  -p 5672:5672 \
  -p 15672:15672 \
  -p 61613:61613 \
  -e RABBITMQ_DEFAULT_USER=guest \
  -e RABBITMQ_DEFAULT_PASS=guest \
  rabbitmq:3-management

# Enable STOMP plugin
docker exec rabbitmq-stomp rabbitmq-plugins enable rabbitmq_stomp

# Verify STOMP is running
docker exec rabbitmq-stomp rabbitmq-diagnostics check_port_connectivity
```

### Connection Configuration
```php
$config = [
    'host' => 'localhost',
    'port' => 61613,
    'login' => 'guest',
    'passcode' => 'guest',
    'version' => '1.2',
];

$result = $tr262Service->connect($device, $config);
```

## Alternative Brokers

### ActiveMQ
```bash
# Docker
docker run -d \
  --name activemq \
  -p 61616:61616 \
  -p 8161:8161 \
  -p 61613:61613 \
  rmohr/activemq

# Connection
$config = [
    'host' => 'localhost',
    'port' => 61613,
    'version' => '1.0',
];
```

### Apache Apollo
```bash
# Docker
docker run -d \
  --name apollo \
  -p 61613:61613 \
  -p 61614:61614 \
  danstoner/apollo

# Connection
$config = [
    'host' => 'localhost',
    'port' => 61613,
    'login' => 'admin',
    'passcode' => 'password',
    'version' => '1.1',
];
```

### Apache Artemis
```bash
# Docker
docker run -d \
  --name artemis \
  -p 61616:61616 \
  -p 8161:8161 \
  -p 61613:61613 \
  -e ARTEMIS_USERNAME=artemis \
  -e ARTEMIS_PASSWORD=artemis \
  vromero/activemq-artemis

# Connection
$config = [
    'host' => 'localhost',
    'port' => 61613,
    'login' => 'artemis',
    'passcode' => 'artemis',
    'version' => '1.2',
];
```

## CI/CD Integration

### GitHub Actions Example
```yaml
name: TR-262 Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      rabbitmq:
        image: rabbitmq:3-management
        ports:
          - 5672:5672
          - 15672:15672
          - 61613:61613
        options: >-
          --health-cmd "rabbitmq-diagnostics -q ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_pgsql, redis
      
      - name: Enable RabbitMQ STOMP
        run: |
          docker exec ${{ job.services.rabbitmq.id }} \
            rabbitmq-plugins enable rabbitmq_stomp
      
      - name: Install Dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run Tests
        run: php artisan test --testsuite=Unit --filter=TR262ServiceTest
        env:
          STOMP_HOST: localhost
          STOMP_PORT: 61613
          STOMP_USER: guest
          STOMP_PASS: guest
```

### GitLab CI Example
```yaml
test:tr262:
  image: php:8.2-cli
  services:
    - name: rabbitmq:3-management
      alias: rabbitmq
  variables:
    STOMP_HOST: rabbitmq
    STOMP_PORT: "61613"
    STOMP_USER: guest
    STOMP_PASS: guest
  before_script:
    - docker exec rabbitmq rabbitmq-plugins enable rabbitmq_stomp
    - composer install
  script:
    - php artisan test --filter=TR262ServiceTest
```

## Test Execution

### Running Tests Locally

**With Real Broker:**
```bash
# Start broker first (Docker)
docker run -d --name rabbitmq-stomp -p 61613:61613 rabbitmq:3-management
docker exec rabbitmq-stomp rabbitmq-plugins enable rabbitmq_stomp

# Run tests
php artisan test --filter=TR262ServiceTest
```

**With Mocked Client:**
```php
// In tests/Unit/Services/TR262ServiceTest.php
use Mockery;

protected function setUp(): void
{
    parent::setUp();
    
    // Mock STOMP client for unit testing
    $mockClient = Mockery::mock(\Stomp\Client::class);
    $mockClient->shouldReceive('connect')->andReturn(true);
    $mockClient->shouldReceive('getSessionId')->andReturn('session_123');
    
    $this->app->instance(\Stomp\Client::class, $mockClient);
}
```

### Environment Variables
Add to `.env.testing`:
```env
STOMP_HOST=localhost
STOMP_PORT=61613
STOMP_USER=guest
STOMP_PASS=guest
STOMP_VERSION=1.2
```

## Testing Checklist

- [ ] Broker is running and accessible
- [ ] STOMP port (61613) is open
- [ ] Credentials are correct
- [ ] STOMP plugin/protocol is enabled
- [ ] Network connectivity from test environment
- [ ] PHP extensions installed (sockets, mbstring)

## Troubleshooting

### Connection Refused
```bash
# Check if broker is running
docker ps | grep rabbitmq

# Check port is open
nc -zv localhost 61613

# Check logs
docker logs rabbitmq-stomp
```

### Authentication Failed
```bash
# Verify credentials
docker exec rabbitmq-stomp rabbitmqctl list_users

# Reset password
docker exec rabbitmq-stomp rabbitmqctl change_password guest guest
```

### Plugin Not Enabled
```bash
# List enabled plugins
docker exec rabbitmq-stomp rabbitmq-plugins list

# Enable STOMP
docker exec rabbitmq-stomp rabbitmq-plugins enable rabbitmq_stomp

# Restart broker
docker restart rabbitmq-stomp
```

## Performance Testing

### Load Testing Script
```php
// tests/Performance/TR262LoadTest.php
use App\Services\TR262Service;

$service = app(TR262Service::class);
$device = CpeDevice::factory()->create();

$config = [
    'host' => 'localhost',
    'port' => 61613,
    'login' => 'guest',
    'passcode' => 'guest',
];

// Connect
$result = $service->connect($device, $config);
$connectionId = $result['connection_id'];

// Publish 1000 messages
$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $service->publish($connectionId, '/topic/load-test', "Message {$i}");
}
$elapsed = microtime(true) - $start;

echo "Published 1000 messages in {$elapsed}s\n";
echo "Throughput: " . (1000 / $elapsed) . " msg/s\n";
```

## Security Configuration

### SSL/TLS Setup
```php
$config = [
    'host' => 'secure-broker.example.com',
    'port' => 61614,
    'ssl' => true,
    'login' => 'admin',
    'passcode' => 'secure_password',
    'version' => '1.2',
];
```

### Virtual Hosts
```php
$config = [
    'host' => 'localhost',
    'port' => 61613,
    'virtual_host' => '/production',
    'login' => 'admin',
    'passcode' => 'password',
];
```

## Production Recommendations

1. **High Availability**: Use clustered broker setup
2. **Monitoring**: Enable broker metrics and alerting
3. **Persistence**: Configure durable queues/topics
4. **Resource Limits**: Set connection/memory limits
5. **Security**: Use SSL/TLS in production
6. **Heartbeats**: Configure heartbeat intervals
7. **Backup**: Regular broker configuration backups

## References

- [stomp-php Documentation](https://github.com/stomp-php/stomp-php)
- [RabbitMQ STOMP Plugin](https://www.rabbitmq.com/stomp.html)
- [ActiveMQ STOMP](https://activemq.apache.org/stomp)
- [STOMP Protocol Specification](https://stomp.github.io/)
