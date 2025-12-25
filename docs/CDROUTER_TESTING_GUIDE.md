# CDRouter USP/TR-369 Testing Guide

## Overview

CDRouter is the **official self-test platform** for BBF.369 USP Agent Certification. This guide provides complete configuration for testing the ACS system with CDRouter.

## Prerequisites

1. **CDRouter License**: Version 12.24+ with USP add-on
2. **Broadband Forum Membership**: Required for official certification
3. **Network Access**: CDRouter must reach ACS endpoints
4. **Test Device**: CPE with TR-369 USP support

---

## ACS Endpoint Configuration

### WebSocket Endpoint (Recommended)

```
URL: wss://your-acs-domain.com:8080/usp
Protocol: WebSocket over TLS
```

### HTTP Endpoint

```
URL: https://your-acs-domain.com/api/v1/usp
Protocol: HTTP/2 over TLS
```

### MQTT Endpoint

```
Broker: mqtt://your-acs-domain.com:1883
TLS Broker: mqtts://your-acs-domain.com:8883
Topic: /usp/controller
```

### STOMP Endpoint

```
URL: stomp://your-acs-domain.com:61613
TLS URL: stomps://your-acs-domain.com:61614
Destination: /usp/controller
```

---

## CDRouter Configuration Files

### WebSocket Configuration (websocket.conf)

```bash
SECTION "CDRouter USP Add-On" {
    testvar supportsUSP yes
    
    SECTION "Controller Configuration" {
        testvar uspControllerID urn:bbf:usp:id:oui:000000:acs-controller
        testvar uspControllerIpMode ipv4-only
        testvar uspControllerIpv4 auto
        testvar uspControllerDomain your-acs-domain.com
        testvar uspControllerPort 8080
        testvar uspControllerPath /usp
        testvar uspControllerMTP websocket
        testvar uspControllerMTPEncryption yes
        testvar uspControllerUSPEncryption no
        testvar uspControllerUseSessionContext no
        testvar uspControllerUseNonPayloadProtection no
    }
    
    SECTION "Agent Configuration" {
        testvar uspAgentID proto::agent-id
        testvar uspAgentPort auto
        testvar uspAgentPath /agent
        testvar uspAgentIpv4 auto
    }
    
    SECTION "Authentication" {
        testvar uspControllerUsername admin
        testvar uspControllerPassword your-password
    }
}
```

### MQTT Configuration (mqtt.conf)

```bash
SECTION "CDRouter USP Add-On" {
    testvar supportsUSP yes
    
    SECTION "Controller Configuration" {
        testvar uspControllerID urn:bbf:usp:id:oui:000000:acs-controller
        testvar uspControllerIpMode ipv4-only
        testvar uspControllerIpv4 auto
        testvar uspControllerDomain your-acs-domain.com
        testvar uspControllerPort 8883
        testvar uspControllerMTP mqtt
        testvar uspControllerMTPEncryption yes
        testvar uspControllerDestination /usp/controller
        testvar uspAgentDestination /usp/agent
    }
    
    SECTION "Agent Configuration" {
        testvar uspAgentID proto::agent-id
        testvar uspAgentPort auto
        testvar uspAgentIpv4 auto
    }
}
```

### STOMP Configuration (stomp.conf)

```bash
SECTION "CDRouter USP Add-On" {
    testvar supportsUSP yes
    
    SECTION "Controller Configuration" {
        testvar uspControllerID urn:bbf:usp:id:oui:000000:acs-controller
        testvar uspControllerIpMode ipv4-only
        testvar uspControllerIpv4 auto
        testvar uspControllerDomain your-acs-domain.com
        testvar uspControllerPort 61614
        testvar uspControllerPath /usp
        testvar uspControllerMTP stomp
        testvar uspControllerMTPEncryption yes
        testvar uspControllerDestination /usp/controller
        testvar uspAgentDestination /usp/agent
        testvar uspControllerUsername admin
        testvar uspControllerPassword your-password
    }
    
    SECTION "Agent Configuration" {
        testvar uspAgentID proto::agent-id
        testvar uspAgentPort auto
        testvar uspAgentIpv4 auto
    }
}
```

---

## TLS Certificate Configuration

For mTLS authentication:

```bash
SECTION "TLS Configuration" {
    testvar uspControllerUSPCertPath /path/to/controller-cert.pem
    testvar uspControllerUSPCaCertPath /path/to/ca-cert.pem
    testvar uspControllerMTPCertPath /path/to/mtp-cert.pem
    testvar uspControllerMTPCaCertPath /path/to/mtp-ca-cert.pem
}
```

---

## Test Modules

### 1. Conformance Tests (TP-469)

```bash
# Run full certification test suite
cdrouter-cli run -p your-config.conf -m usp_conformance
```

Tests included:
- Mandatory tests for all USP agents
- Conditional mandatory tests (feature-specific)
- All MTP protocol coverage

### 2. Basic Functional Tests

```bash
# Run basic validation
cdrouter-cli run -p your-config.conf -m usp_basic
```

### 3. Multi-Controller Tests

```bash
# Test multi-controller scenarios
cdrouter-cli run -p your-config.conf -m usp_multi_controller
```

### 4. Bulk Data Collection

```bash
# Test Annex A bulk data
cdrouter-cli run -p your-config.conf -m usp_annex_a
```

---

## Running Tests

### Command Line

```bash
# List available tests
cdrouter-cli list -m usp_conformance

# Run specific test
cdrouter-cli run -p config.conf -t usp_conformance.tc_get_supported_proto

# Run all conformance tests
cdrouter-cli run -p config.conf -m usp_conformance

# Export results
cdrouter-cli export -r <run-id> -o results.zip
```

### Web Interface

1. Navigate to CDRouter web UI
2. Upload configuration file
3. Select test module (usp_conformance)
4. Click "Run Tests"
5. Export results for ATL submission

---

## ACS Test Endpoints

The ACS system provides these endpoints for CDRouter testing:

### USP Controller Endpoint

```
POST /api/v1/usp/message
Content-Type: application/protobuf
Authorization: Bearer <token>
```

### WebSocket Endpoint

```
wss://your-domain:8080/usp
Subprotocol: v1.usp
```

### Health Check

```
GET /api/v1/usp/health
```

---

## Test Categories for BBF.369 Certification

### Mandatory Tests

| Test ID | Description |
|---------|-------------|
| GET_SUPPORTED_PROTO | Protocol negotiation |
| GET | Parameter retrieval |
| SET | Parameter modification |
| ADD | Object creation |
| DELETE | Object removal |
| OPERATE | Command execution |
| NOTIFY | Event notification |

### Conditional Mandatory Tests

| Feature | Test IDs |
|---------|----------|
| Firmware Update | FW_* tests |
| Software Modules | SMM_* tests |
| Multi-Controller | MC_* tests |
| Bulk Data | BD_* tests |

---

## Troubleshooting

### Agent Never Responds

1. Verify `uspControllerID` matches ACS controller endpoint
2. Check `uspAgentID` matches device endpoint
3. Confirm network connectivity

### Certificate Validation Fails

1. Ensure certificates have correct `subjectAltName`
2. Update `uspControllerID` to match certificate
3. Check CA chain is complete

### MQTT Connection Issues

1. Verify agent subscribes to correct topic
2. Check `uspAgentDestination` configuration
3. Confirm broker authentication

### WebSocket Handshake Fails

1. Check WebSocket URL format
2. Verify TLS configuration
3. Confirm authentication credentials

---

## Certification Submission

### Step 1: Run All Tests

```bash
# Run complete certification suite
cdrouter-cli run -p config.conf -m usp_conformance --all
```

### Step 2: Export Results

```bash
# Export signed results package
cdrouter-cli export -r <run-id> -o bbf369-results.zip --signed
```

### Step 3: Submit to ATL

1. Contact approved Test Lab (e.g., UNH-IOL)
2. Submit results package
3. Address any findings
4. Receive certification

### Approved Test Laboratories

- UNH InterOperability Laboratory (UNH-IOL)
- Contact Broadband Forum for full list

---

## Resources

- [CDRouter USP User Guide](https://support.qacafe.com/cdrouter/user-guide/cdrouter-usp-user-guide)
- [BBF.369 Certification Program](https://www.broadband-forum.org/testing-and-certification-programs/bbf-369-usp-certification)
- [TP-469 Test Plan](https://usp-test.broadband-forum.org/)
- [CDRouter Training Videos](https://support.qacafe.com/cdrouter/training/videos)

---

## Quick Reference

| Parameter | Description | Example |
|-----------|-------------|---------|
| `uspControllerMTP` | Transport protocol | websocket, mqtt, stomp |
| `uspControllerID` | Controller endpoint ID | urn:bbf:usp:id:oui:000000:acs |
| `uspAgentID` | Agent endpoint ID | proto::agent-id |
| `uspControllerMTPEncryption` | TLS enabled | yes/no |
| `uspControllerPort` | Port number | 8080, 8883, 61614 |
