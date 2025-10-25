# TR-369 USP MQTT Configuration

## Overview

Il sistema ACS supporta TR-369 USP MQTT transport (BBF TR-369 Section 4.4) per comunicazione pub/sub con dispositivi CPE.

## Environment Variables

### MQTT Broker Connection

```bash
# MQTT Broker Host (required)
MQTT_HOST=localhost

# MQTT Broker Port (default: 1883 for unencrypted, 8883 for TLS)
MQTT_PORT=1883

# MQTT Client ID (unique identifier for ACS controller)
MQTT_CLIENT_ID=acs-controller-001

# Clean Session flag (true = start fresh, false = resume existing session)
MQTT_CLEAN_SESSION=true

# Enable MQTT logging
MQTT_ENABLE_LOGGING=true
MQTT_LOG_CHANNEL=default
```

### Authentication

```bash
# MQTT Authentication (if broker requires username/password)
MQTT_AUTH_USERNAME=acs_user
MQTT_AUTH_PASSWORD=secure_password
```

### TLS/SSL Configuration

```bash
# Enable TLS encryption
MQTT_TLS_ENABLED=false

# Allow self-signed certificates (development only)
MQTT_TLS_ALLOW_SELF_SIGNED_CERT=false

# Verify peer certificate
MQTT_TLS_VERIFY_PEER=true
MQTT_TLS_VERIFY_PEER_NAME=true

# Certificate Authority file
MQTT_TLS_CA_FILE=/path/to/ca.crt

# Client certificate (mutual TLS)
MQTT_TLS_CLIENT_CERT_FILE=/path/to/client.crt
MQTT_TLS_CLIENT_CERT_KEY_FILE=/path/to/client.key
MQTT_TLS_CLIENT_CERT_KEY_PASSPHRASE=
```

### Connection Timeouts

```bash
# Connection timeout (seconds)
MQTT_CONNECT_TIMEOUT=60

# Socket timeout (seconds)
MQTT_SOCKET_TIMEOUT=5

# Resend timeout (seconds)
MQTT_RESEND_TIMEOUT=10

# Keep alive interval (seconds)
MQTT_KEEP_ALIVE_INTERVAL=10
```

### Auto-Reconnect

```bash
# Enable auto-reconnect on connection loss
MQTT_AUTO_RECONNECT_ENABLED=true

# Maximum reconnection attempts
MQTT_AUTO_RECONNECT_MAX_RECONNECT_ATTEMPTS=10

# Delay between reconnection attempts (seconds)
MQTT_AUTO_RECONNECT_DELAY_BETWEEN_RECONNECT_ATTEMPTS=5
```

### Last Will and Testament

```bash
# MQTT Last Will configuration (message sent when controller disconnects unexpectedly)
MQTT_LAST_WILL_TOPIC=usp/controller/status
MQTT_LAST_WILL_MESSAGE={"status":"offline"}
MQTT_LAST_WILL_QUALITY_OF_SERVICE=1
MQTT_LAST_WILL_RETAIN=true
```

### USP-Specific Configuration

```bash
# Enable USP MQTT transport
USP_MQTT_ENABLED=true

# USP MQTT topic patterns (BBF TR-369 compliant)
USP_MQTT_CONTROLLER_TOPIC=usp/controller
USP_MQTT_AGENT_TOPIC=usp/agent

# USP Controller endpoint ID
USP_CONTROLLER_ENDPOINT_ID=proto::acs-controller-001
```

## MQTT Broker Recommendations

### Production Deployment

**Carrier-Grade MQTT Brokers** (100K+ devices):

1. **VerneMQ** (Recommended)
   - Erlang-based, highly scalable
   - Clustering support
   - Plugin system for auth/ACL
   - Install: https://vernemq.com/docs/installation/

2. **EMQ X (EMQX)**
   - Massive scalability (10M+ connections)
   - Built-in dashboard
   - Multi-protocol support
   - Install: https://www.emqx.io/docs/en/latest/

3. **HiveMQ**
   - Enterprise-grade
   - High availability clustering
   - Commercial support
   - Install: https://www.hivemq.com/downloads/

### Development/Testing

**Mosquitto** (Small scale, <10K devices):
```bash
# Install
sudo apt-get install mosquitto mosquitto-clients

# Start
sudo systemctl start mosquitto

# Test
mosquitto_sub -h localhost -t 'usp/#' -v
```

## Topic Structure (BBF TR-369 Compliant)

### Controller → Agent (Device)
```
usp/agent/{device_mqtt_client_id}
```

**Example**: `usp/agent/device-12345`

### Agent → Controller
```
usp/controller/{controller_id}
```

**Example**: `usp/controller/acs-001`

## QoS Levels

- **QoS 0** - At most once (fire and forget)
- **QoS 1** - At least once (default, recommended)
- **QoS 2** - Exactly once (highest reliability, slower)

**Recommendation**: Use QoS 1 for balance between reliability and performance.

## Connection Flow

1. **ACS Controller** connects to MQTT broker
2. **Controller** subscribes to: `usp/controller/#`
3. **Device** connects with unique `mqtt_client_id`
4. **Device** subscribes to: `usp/agent/{mqtt_client_id}`
5. **Communication**:
   - Controller publishes USP messages to device topic
   - Device publishes USP responses to controller topic

## Security Best Practices

### Production Requirements

1. **Enable TLS encryption** (`MQTT_TLS_ENABLED=true`)
2. **Use strong authentication** (username/password minimum)
3. **Implement ACL rules** (topic-based access control)
4. **Isolate MQTT broker** (internal network only)
5. **Rate limiting** (prevent DDoS)
6. **Monitor connections** (detect anomalies)

### Network Architecture

```
┌─────────────┐      TLS 8883      ┌──────────────┐
│ ACS Server  │◄──────────────────►│ MQTT Broker  │
│ (Controller)│                    │   Internal   │
└─────────────┘                    └──────┬───────┘
                                          │
                                          │ TLS 8883
                                          │
                                   ┌──────▼───────┐
                                   │  CPE Devices │
                                   │   (Agents)   │
                                   └──────────────┘
```

## Testing MQTT Configuration

### 1. Test Broker Connection

```bash
# Subscribe to all USP topics
mosquitto_sub -h ${MQTT_HOST} -p ${MQTT_PORT} \
  -u ${MQTT_AUTH_USERNAME} -P ${MQTT_AUTH_PASSWORD} \
  -t 'usp/#' -v

# In another terminal, publish test message
mosquitto_pub -h ${MQTT_HOST} -p ${MQTT_PORT} \
  -u ${MQTT_AUTH_USERNAME} -P ${MQTT_AUTH_PASSWORD} \
  -t 'usp/test' -m 'Hello'
```

### 2. Test ACS MQTT Bridge Endpoint

```bash
# Send USP message via MQTT bridge
curl -X POST http://localhost:5000/tr369/mqtt/publish \
  -H 'Content-Type: application/json' \
  -d '{
    "topic": "usp/agent/test-device",
    "payload": "base64_encoded_usp_record",
    "qos": 1
  }'
```

### 3. Monitor MQTT Traffic

```bash
# Real-time monitoring with mosquitto_sub
mosquitto_sub -h ${MQTT_HOST} -p ${MQTT_PORT} \
  -u ${MQTT_AUTH_USERNAME} -P ${MQTT_AUTH_PASSWORD} \
  -t 'usp/#' -F '@Y-@m-@dT@H:@M:@S@z : %t : %p'
```

## Troubleshooting

### Connection Refused

**Symptoms**: `Connection refused` or `Unable to connect to broker`

**Solutions**:
1. Verify broker is running: `systemctl status mosquitto`
2. Check firewall: `sudo ufw allow 1883/tcp`
3. Verify host/port in `.env`
4. Check logs: `tail -f /var/log/mosquitto/mosquitto.log`

### Authentication Failed

**Symptoms**: `Connection error: Not authorized`

**Solutions**:
1. Verify username/password in broker configuration
2. Check `.env` credentials match broker
3. Test with mosquitto_pub:
   ```bash
   mosquitto_pub -h localhost -p 1883 \
     -u test_user -P test_pass \
     -t test -m "hello" -d
   ```

### TLS Certificate Issues

**Symptoms**: `TLS handshake failed`, `Certificate verify failed`

**Solutions**:
1. Verify CA certificate path: `MQTT_TLS_CA_FILE`
2. For development, set: `MQTT_TLS_ALLOW_SELF_SIGNED_CERT=true`
3. Check certificate validity: `openssl x509 -in ca.crt -text -noout`

### High Latency

**Symptoms**: Slow message delivery, timeouts

**Solutions**:
1. Increase timeouts: `MQTT_SOCKET_TIMEOUT`, `MQTT_CONNECT_TIMEOUT`
2. Enable keep-alive: `MQTT_KEEP_ALIVE_INTERVAL=10`
3. Check broker resources (CPU, memory, connections)
4. Optimize QoS level (use QoS 1 instead of QoS 2)

## Monitoring & Alerting

### Metrics to Monitor

1. **Connection count** - Active MQTT clients
2. **Message rate** - Messages/second
3. **Queue depth** - Pending messages
4. **Latency** - Publish to delivery time
5. **Error rate** - Failed publishes/subscribes

### Prometheus Integration

MQTT brokers (EMQX, VerneMQ) expose Prometheus metrics:

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'mqtt'
    static_configs:
      - targets: ['mqtt-broker:9100']
```

**Key Metrics**:
- `mqtt_connections_total` - Total connections
- `mqtt_messages_published_total` - Published messages
- `mqtt_messages_received_total` - Received messages
- `mqtt_message_latency_seconds` - Message latency

## Load Testing

Use K6 scripts to test MQTT scalability:

```bash
# Test MQTT bridge with 10K concurrent publishers
k6 run --vus 10000 --duration 5m tests/Load/scenarios/tr369.js
```

Monitor:
- Broker CPU/memory usage
- Message delivery latency
- Connection stability
- Queue depth

## Configuration Examples

### Development (.env)

```bash
MQTT_HOST=localhost
MQTT_PORT=1883
MQTT_CLIENT_ID=acs-dev-001
MQTT_CLEAN_SESSION=true
MQTT_TLS_ENABLED=false
MQTT_AUTH_USERNAME=
MQTT_AUTH_PASSWORD=
USP_MQTT_ENABLED=true
```

### Production (.env)

```bash
MQTT_HOST=mqtt-cluster.internal.company.com
MQTT_PORT=8883
MQTT_CLIENT_ID=acs-prod-controller-001
MQTT_CLEAN_SESSION=false
MQTT_TLS_ENABLED=true
MQTT_TLS_VERIFY_PEER=true
MQTT_TLS_CA_FILE=/etc/ssl/certs/mqtt-ca.crt
MQTT_TLS_CLIENT_CERT_FILE=/etc/ssl/certs/acs-client.crt
MQTT_TLS_CLIENT_CERT_KEY_FILE=/etc/ssl/private/acs-client.key
MQTT_AUTH_USERNAME=acs_controller
MQTT_AUTH_PASSWORD=${MQTT_PASSWORD_SECRET}
MQTT_AUTO_RECONNECT_ENABLED=true
MQTT_AUTO_RECONNECT_MAX_RECONNECT_ATTEMPTS=999
USP_MQTT_ENABLED=true
USP_CONTROLLER_ENDPOINT_ID=proto::acs-prod-controller-001
```

## References

- BBF TR-369 Specification: https://www.broadband-forum.org/technical/download/TR-369.pdf
- MQTT 3.1.1 Specification: http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/
- PHP-MQTT Client Documentation: https://github.com/php-mqtt/client
