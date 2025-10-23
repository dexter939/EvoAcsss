# Overview
The ACS (Auto Configuration Server) project is a carrier-grade Laravel 11 system designed for managing over 100,000 CPE devices. It supports a comprehensive suite of 10 TR protocols and offers functionalities like device auto-registration, zero-touch provisioning, firmware management, advanced remote diagnostics, real-time STOMP messaging, comprehensive monitoring & alerting, and AI-powered configuration assistance. The business vision is to deliver a highly scalable, performant solution for large-scale device management in telecommunication environments with carrier-grade reliability and enterprise security.

# User Preferences
I prefer clear and concise explanations. When making changes, prioritize core functionalities and ensure backward compatibility. I prefer an iterative development approach, focusing on delivering functional components incrementally. Please ask for confirmation before implementing significant architectural changes or altering existing API contracts. Ensure all new features have comprehensive test coverage. I want the agent to use proper markdown formatting in all its responses.

# System Architecture

## UI/UX Decisions
The web interface utilizes the Soft UI Dashboard Laravel template for a modern, responsive design. Key UI elements include a redesigned dashboard, enhanced CPE device configuration editors, a real-time alarms system, card-based device listings, a tabbed device details modal, an AI-Powered Configuration Assistant Dashboard, a Network Topology Map, an Advanced Provisioning Dashboard, a Performance Monitoring Dashboard, and an Advanced Monitoring & Alerting System. The sidebar navigation includes an "Impostazioni" (Settings) section containing System Updates management and user/role administration.

## Technical Implementations
- **Protocol Support**: Comprehensive implementation of 10 production-ready TR protocols (TR-069, TR-104, TR-106, TR-111, TR-135, TR-140, TR-157, TR-181, TR-262, TR-369) with BBF-compliant services. This includes a real STOMP client for TR-262 and device-scoped caching for TR-181.
- **Database**: PostgreSQL with optimized indexing and multi-tenancy.
- **Performance Optimizations**: Strategic database indexes, multi-tier Redis caching, and a centralized CacheService.
- **Asynchronous Processing**: Laravel Horizon with Redis queues for provisioning, firmware, and TR-069 requests.
- **API Security**: API Key authentication for v1 RESTful endpoints, rate limiting, and DDoS protection.
- **Security Hardening**: Enterprise-grade security features including RBAC, input validation, security audit logging, and IP blacklist management.
- **Multi-Tenant Device Access Control**: Role-based device scoping via user_devices pivot table with three permission levels (viewer, manager, admin). EnsureDeviceAccess middleware enforces tenant isolation across all device-scoped API routes with super-admin bypass support. Secure handling of route model binding and scalar device IDs prevents authorization bypass vulnerabilities. Carrier-grade backfill command (`devices:backfill-access`) uses cursor-based streaming, chunked processing, batch operations, and scoped transactions to safely assign devices to users at 100K+ scale.
- **Scalability**: Achieved through database optimizations, Redis caching, and a high-throughput queue system.
- **Production Deployment Infrastructure**: Complete Docker-based deployment with multi-stage Dockerfile, docker-compose orchestration (ACS app, PostgreSQL, Redis, Prosody XMPP, Nginx), production nginx configuration with SSL/TLS and rate limiting, automated deployment scripts (deploy.sh, backup.sh, restore.sh), health check endpoints and artisan commands, Makefile for operational commands, and comprehensive DEPLOYMENT.md guide. Session management supports both database (default) and Redis (production) with environment-driven configuration. Network isolation with internal-only database/cache access. PRODUCTION-NOTES.md documents HA requirements for carrier-grade deployments (100K+ devices).
- **Telemetry & Observability**: Automated metrics collection, RESTful Telemetry API, PostgreSQL persistence, and comprehensive monitoring dashboards.
- **Software Auto-Update System**: Carrier-grade automatic deployment tracking, environment-aware versioning, transactional failure handling with rollback, 5-stage health checks, and a GitHub Releases-based system with a web dashboard.
- **Test Infrastructure**: Comprehensive test suite with 5 Fake Services (FakeUspMqttService, FakeUspWebSocketService, FakeUpnpDiscoveryService, FakeParameterDiscoveryService, FakeConnectionRequestService) registered globally in TestCase::setUp() to isolate external dependencies. All Feature tests free from Mockery usage, ensuring deterministic test execution without real network calls to MQTT brokers, WebSocket servers, or HTTP endpoints. Unit tests with complex Mockery marked as @group skip for future refactoring.

## Feature Specifications
- **Device Management**: Auto-registration, zero-touch provisioning with configuration profiles, firmware management, and a multi-vendor device library with auto-detection.
- **Advanced Provisioning**: Enterprise-grade system with bulk operations, scheduling, templates, conditional rules, configuration versioning with rollback, pre-flight validation, and staged rollout.
- **TR-181 Data Model**: Parameters stored with type, path, access, and update history.
- **Connection Management**: System-initiated connection requests and TR-369 subscription/notification.
- **AI-Powered Configuration Assistant**: Integrates OpenAI GPT-4o-mini for template generation, configuration validation, optimization, diagnostic analysis, and historical pattern detection.
- **Multi-Tenant Architecture**: Supports multiple customers with a 3-level web hierarchy.
- **Configuration Templates**: Database-driven templates with validation rules and BBF-compliant parameter validation.
- **TR-143 Diagnostics**: UI and workflow for Ping, Traceroute, Download, and Upload tests.
- **Network Topology Map**: Real-time interactive visualization of connected LAN/WiFi clients.
- **NAT Traversal & Pending Commands Queue**: Solution for executing TR-069 commands on devices behind NAT/firewalls.
- **Real-time Alarms & Monitoring**: Carrier-grade alarm management with SSE real-time notifications, a dashboard, event-driven processing, and a comprehensive monitoring and alerting system with multi-channel notifications and a configurable rules engine.
- **Multi-Vendor Device Library**: Comprehensive vendor management system with 6 dedicated UI views (manufacturers, products, quirks, templates), firmware compatibility matrix, vendor quirks database, configuration template library, and OUI-based auto-detection with 85% fuzzy matching threshold.
- **Bulk Operations API**: RESTful endpoints for bulk vendor detection, bulk template application with dry-run mode, and bulk firmware compatibility checking across multiple devices with detailed result reporting.
- **Vendor Detection CLI**: Artisan command `vendor:detect` with options for batch processing (--all, --unmatched, --device, --force), progress tracking, and comprehensive operational monitoring.

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