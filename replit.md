# Overview
The ACS (Auto Configuration Server) project is a carrier-grade Laravel 11 system designed for managing over 100,000 CPE devices. It supports a comprehensive suite of 10 TR protocols (TR-069, TR-104, TR-106, TR-111, TR-135, TR-140, TR-157, TR-181, TR-262, TR-369), all production-ready, and offers functionalities like device auto-registration, zero-touch provisioning, firmware management, advanced remote diagnostics, real-time STOMP messaging, comprehensive monitoring & alerting, and AI-powered configuration assistance. The business vision is to deliver a highly scalable, performant solution for large-scale device management in telecommunication environments with carrier-grade reliability and enterprise security.

# User Preferences
I prefer clear and concise explanations. When making changes, prioritize core functionalities and ensure backward compatibility. I prefer an iterative development approach, focusing on delivering functional components incrementally. Please ask for confirmation before implementing significant architectural changes or altering existing API contracts. Ensure all new features have comprehensive test coverage. I want the agent to use proper markdown formatting in all its responses.

# System Architecture

## UI/UX Decisions
The web interface utilizes the Soft UI Dashboard Laravel template for a modern, responsive design. Key UI elements include a redesigned dashboard, enhanced CPE device configuration editors, a real-time alarms system, card-based device listings, a tabbed device details modal, an AI-Powered Configuration Assistant Dashboard, a Network Topology Map, an Advanced Provisioning Dashboard, a Performance Monitoring Dashboard, and an Advanced Monitoring & Alerting System. The sidebar navigation includes an "Impostazioni" (Settings) section containing System Updates management and user/role administration.

## Technical Implementations
- **Protocol Support**: Comprehensive implementation of 10 TR protocols (TR-069, TR-104, TR-106, TR-111, TR-135, TR-140, TR-157, TR-181, TR-262, TR-369) with BBF-compliant services. All protocols are production-ready with complete implementations:
  - TR-069: CWMP core protocol with Inform/GetParameterValues/SetParameterValues
  - TR-104 (485 lines): VoIP Service with SIP/MGCP/H.323, codec negotiation, QoS (DSCP), E911, failover
  - TR-106 (375 lines): Data Model Template with XML import/export, parameter validation, version compatibility
  - TR-111 (388 lines): Proximity Detection with UPnP/LLDP/mDNS discovery, network topology mapping
  - TR-135 (279 lines): STB (Set-Top Box) Data Model for media devices
  - TR-140 (352 lines): StorageService Data Model for NAS and media servers
  - TR-157 (630 lines): Component Objects with software lifecycle management, database-backed
  - TR-181: Device:2 Data Model with device-scoped caching for 100K+ scale
  - TR-262 (650+ lines): CWMP-STOMP Binding with **real STOMP client** (stomp-php v5.1.3), pub/sub messaging, STOMP 1.0/1.1/1.2 protocol support, transactions (BEGIN/COMMIT/ABORT), ACK/NACK, heartbeat mechanism, SSL/TLS encryption, virtual hosts. Supports ActiveMQ, RabbitMQ, Apollo, Artemis brokers
  - TR-369: USP (User Services Platform) with Protocol Buffers, MQTT, WebSocket, XMPP transports
- **Database**: PostgreSQL with optimized indexing and multi-tenancy.
- **Performance Optimizations**: Strategic database indexes, multi-tier Redis caching, and a centralized CacheService.
- **Asynchronous Processing**: Laravel Horizon with Redis queues for provisioning, firmware, and TR-069 requests.
- **API Security**: API Key authentication for v1 RESTful endpoints.
- **Security Hardening**: Enterprise-grade security features including rate limiting, DDoS protection, RBAC, input validation, security audit logging, and IP blacklist management.
- **Scalability**: Achieved through database optimizations, Redis caching, and a high-throughput queue system.
- **Configuration**: Laravel environment variables.
- **Deployment**: VM-based deployment configured for always-running services (Laravel + Queue Workers + XMPP). The build phase includes config/route/view caching, and multi-service orchestration with parallel process execution.
- **Telemetry & Observability**: Automated metrics collection (22+ system/device/queue metrics) scheduled every 5 minutes, RESTful Telemetry API, PostgreSQL persistence, and comprehensive monitoring dashboards.
- **Software Auto-Update System**: Carrier-grade automatic deployment tracking and migration system, environment-aware version tracking with a `system_versions` table, auto-execution of migrations, transactional failure handling with rollback, 5-stage health checks, and RESTful API/CLI interfaces. This system is GitHub Releases-based, replacing a Replit-triggered workflow. The web dashboard is accessible via the "System Updates" link in the "Impostazioni" (Settings) section, featuring 6 stat cards, approval workflow, real-time progress monitoring, and deployment timeline.

## Feature Specifications
- **Device Management**: Auto-registration, zero-touch provisioning with configuration profiles, and firmware management.
- **Advanced Provisioning**: Enterprise-grade system with bulk operations, scheduling, templates, conditional rules, configuration versioning with rollback, pre-flight validation, and staged rollout.
- **TR-181 Data Model**: Parameters stored with type, path, access, and update history.
- **Connection Management**: System-initiated connection requests and TR-369 subscription/notification.
- **AI-Powered Configuration Assistant**: Integrates OpenAI GPT-4o-mini for template generation, configuration validation, optimization, diagnostic analysis, and historical pattern detection.
- **Multi-Tenant Architecture**: Supports multiple customers with a 3-level web hierarchy.
- **Data Model Import**: Automated XML parser for vendor-specific and BBF standard TR-069 data models.
- **Configuration Templates**: Database-driven templates with validation rules.
- **BBF-Compliant Parameter Validation**: Production-ready validation engine supporting 12+ BBF data types.
- **Router Manufacturers & Products Database**: Hierarchical view of manufacturers and models.
- **TR-143 Diagnostics**: UI and workflow for Ping, Traceroute, Download, and Upload tests.
- **Network Topology Map**: Real-time interactive visualization of connected LAN/WiFi clients.
- **NAT Traversal & Pending Commands Queue**: Solution for executing TR-069 commands on devices behind NAT/firewalls.
- **Real-time Alarms & Monitoring**: Carrier-grade alarm management with SSE real-time notifications, dashboard, and event-driven processing.
- **Advanced Monitoring & Alerting System**: Comprehensive infrastructure with multi-channel alert notifications, a configurable alert rules engine, real-time system metrics tracking, and an alert management dashboard.

# Recent Updates (Sprint 2 - October 2025)

## Sprint 2 Completions
- **TR-262 Production-Ready**: Implemented real STOMP client with stomp-php v5.1.3 library, replacing previous simulation with actual broker connectivity
- **Queue Worker Fixed**: Created cache tables (cache, cache_locks) resolving Queue Worker startup failures
- **STOMP Test Infrastructure**: Comprehensive testing guide (`docs/TR262_STOMP_Testing_Setup.md`) with Docker setup for RabbitMQ, ActiveMQ, Apollo, Artemis brokers, CI/CD integration examples (GitHub Actions, GitLab CI), and load testing scripts
- **STOMP Monitoring**: Added metrics collection system with:
  - CLI command: `php artisan metrics:stomp` for real-time metrics
  - REST API endpoints: `/api/v1/stomp/metrics`, `/api/v1/stomp/connections`, `/api/v1/stomp/throughput`, `/api/v1/stomp/broker-health`
  - StompMetricsController for connection stats, message throughput, error tracking
- **Performance Optimization**: Cached routes, configuration, and views for production deployment
- **Deployment Verified**: VM deployment with build.sh (migrations, caching) and run.sh (multi-service orchestration) scripts fully functional

## All Systems Operational
- ✅ ACS Server (port 5000): RUNNING
- ✅ Queue Worker: RUNNING
- ✅ Prosody XMPP Server: RUNNING
- ✅ Database PostgreSQL: CONNECTED
- ✅ All 10 TR Protocols: PRODUCTION-READY

# External Dependencies
- **PostgreSQL 16+**: Primary relational database.
- **Redis 7+**: Queue driver for Laravel Horizon and WebSocket message routing.
- **Laravel Horizon**: Manages Redis queues.
- **Guzzle**: HTTP client.
- **Google Protocol Buffers v4.32.1**: For TR-369 USP message encoding/decoding.
- **PHP-MQTT Client v1.6.1**: For USP broker-based transport.
- **Prosody XMPP Server**: For TR-369 USP XMPP transport.
- **pdahal/php-xmpp v1.0.1**: PHP XMPP client library.
- **stomp-php/stomp-php v5.1.3**: Production STOMP client for TR-262 implementation with support for ActiveMQ, RabbitMQ, Apollo, Artemis brokers.
- **Soft UI Dashboard**: Laravel template for the admin interface.
- **Chart.js**: JavaScript library for interactive charts.
- **FontAwesome**: Icon library.
- **Nginx**: Production web server and reverse proxy.
- **Supervisor/Systemd**: Process management.
- **OpenAI**: For AI-powered configuration and diagnostics.
# Test Coverage & Quality Assurance

## Test Suite Overview
The project includes a comprehensive test suite with **41+ test files** covering Unit, Feature, and Integration tests.

### Unit Tests (Services Layer)
Comprehensive unit testing for all TR protocol services:
- **TR-262Service** (29 test cases): Real STOMP client integration testing with connection, pub/sub messaging, transactions (BEGIN/COMMIT/ABORT), ACK/NACK, QoS levels, heart-beat, SSL/TLS, virtual hosts
  - Status: Uses stomp-php library for production broker connectivity (tests require STOMP broker or mocks)
- **TR-104Service** (26 test cases): VoIP service parameters, SIP registration, codec negotiation, QoS configuration, failover, E911
- **TR-106Service** (28 test cases): Data model templates, parameter validation, XML import/export, version compatibility, vendor extensions
- **TR-111Service** (17 test cases): Proximity detection, UPnP/LLDP/mDNS discovery, network topology mapping, device relationships
- **TR-135Service**: STB data model and media service testing
- **TR-140Service**: Storage service and NAS capabilities testing
- **TR-157Service**: Component lifecycle and software management testing

### Feature Tests
- **TR-069 Operations**: Inform flow, connection requests, parameter operations
- **TR-369 USP**: HTTP, MQTT, WebSocket transport testing
- **API Endpoints**: Device management, provisioning, diagnostics, VoIP, STB, storage services

### Integration Tests
- **TR-157 Integration**: CWMP and USP integration workflows
- **Queue Processing**: Asynchronous job execution and monitoring

### Database Factories
Production-ready factories for testing:
- `CpeDeviceFactory`: TR-069 and TR-369 device generation with online/offline states
- `VoiceServiceFactory`: VoIP service configuration with SIP/MGCP/H.323 protocols
- `SipProfileFactory`: SIP profiles with UDP/TCP/TLS transport options
- `VoipLineFactory`: VoIP lines with call forwarding, DND, registration states

### Test Execution
```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration

# Run TR protocol tests
php artisan test --filter=TR262ServiceTest
php artisan test --filter=TR104ServiceTest
```

### Coverage Status
- **Service Layer**: Comprehensive coverage of all TR protocol services
- **Controllers**: API endpoint testing for device management and provisioning
- **Models**: Factory-based testing with realistic data generation
- **Target**: 70-80% code coverage for core functionality

### Known Test Requirements
- Redis mock configuration for STOMP queue testing
- Database refresh for integration tests
- Factory relationships properly configured
