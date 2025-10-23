# Overview
The ACS (Auto Configuration Server) project is a carrier-grade Laravel 11 system designed for managing over 100,000 CPE devices. It supports a comprehensive suite of 10 TR protocols and offers functionalities like device auto-registration, zero-touch provisioning, firmware management, advanced remote diagnostics, real-time STOMP messaging, comprehensive monitoring & alerting, and AI-powered configuration assistance. The business vision is to deliver a highly scalable, performant solution for large-scale device management in telecommunication environments with carrier-grade reliability and enterprise security.

# User Preferences
I prefer clear and concise explanations. When making changes, prioritize core functionalities and ensure backward compatibility. I prefer an iterative development approach, focusing on delivering functional components incrementally. Please ask for confirmation before implementing significant architectural changes or altering existing API contracts. Ensure all new features have comprehensive test coverage. I want the agent to use proper markdown formatting in all its responses.

# Replit Environment Configuration
- **Session Cookies**: Replit preview runs inside an HTTPS iframe. Chrome (2025+) blocks third-party cookies unless marked with `Partitioned` flag (CHIPS standard). The ACS Server workflow MUST include `SESSION_PARTITIONED_COOKIE=true` to prevent 419 "PAGE EXPIRED" errors after login. Required session env vars: `SESSION_DRIVER=database`, `SESSION_SAME_SITE=none`, `SESSION_SECURE_COOKIE=true`, `SESSION_PARTITIONED_COOKIE=true`.

# System Architecture

## UI/UX Decisions
The web interface utilizes the Soft UI Dashboard Laravel template for a modern, responsive design. Key UI elements include a redesigned dashboard, enhanced CPE device configuration editors, a real-time alarms system, card-based device listings, a tabbed device details modal, an AI-Powered Configuration Assistant Dashboard, a Network Topology Map, an Advanced Provisioning Dashboard, a Performance Monitoring Dashboard, and an Advanced Monitoring & Alerting System. The sidebar navigation includes an "Impostazioni" (Settings) section containing System Updates management and user/role administration.

## Technical Implementations
- **Protocol Support**: Comprehensive implementation of 10 production-ready TR protocols (TR-069, TR-104, TR-106, TR-111, TR-135, TR-140, TR-157, TR-181, TR-262, TR-369) with BBF-compliant services. This includes a real STOMP client for TR-262 and device-scoped caching for TR-181.
- **Database**: PostgreSQL with optimized indexing and multi-tenancy.
- **Performance Optimizations**: Strategic database indexes, multi-tier Redis caching, and a centralized CacheService.
- **Asynchronous Processing**: Laravel Horizon with Redis queues for provisioning, firmware, and TR-069 requests.
- **API Security**: API Key authentication for v1 RESTful endpoints, rate limiting, and DDoS protection.
- **Security Hardening**: Enterprise-grade security features including RBAC, input validation, security audit logging, IP blacklist management, and comprehensive Audit Log System for compliance tracking.
- **Multi-Tenant Device Access Control**: Role-based device scoping via user_devices pivot table with three permission levels (viewer, manager, admin). EnsureDeviceAccess middleware enforces tenant isolation across all device-scoped API routes with super-admin bypass support. Secure handling of route model binding and scalar device IDs prevents authorization bypass vulnerabilities. Carrier-grade backfill command (`devices:backfill-access`) uses cursor-based streaming, chunked processing, batch operations, and scoped transactions to safely assign devices to users at 100K+ scale.
- **Scalability**: Achieved through database optimizations, Redis caching, and a high-throughput queue system.
- **Production Deployment Infrastructure**: Complete multi-tier deployment strategy:
  - **Docker/Compose**: Multi-stage Dockerfile, docker-compose orchestration (ACS app, PostgreSQL, Redis, Prosody XMPP, Nginx), production nginx configuration with SSL/TLS and rate limiting, automated deployment scripts (deploy.sh, backup.sh, restore.sh), health check endpoints, Makefile for operational commands, DEPLOYMENT.md guide. Network isolation with internal-only database/cache access. PRODUCTION-NOTES.md documents HA requirements.
  - **Kubernetes**: Production-grade Helm chart for carrier-grade deployments (100K+ devices) with HPA (3-20 app pods, 5-50 workers), StatefulSets for dev/staging, managed service integration (RDS PostgreSQL, ElastiCache Redis), NetworkPolicies for security, ServiceMonitor for Prometheus, Ingress with TLS, comprehensive resource limits, deploy-k8s.sh automation script, and KUBERNETES.md deployment guide. Chart enforces managed services for production HA.
- **Monitoring & Observability**: Carrier-grade monitoring infrastructure with Prometheus metrics exporter (15+ custom ACS metrics), Grafana dashboards (ACS Overview), PrometheusRule with 10+ alert definitions (critical/warning levels), AlertManager configuration for multi-channel notifications (email, Slack, PagerDuty), ServiceMonitor for Kubernetes auto-discovery, docker-compose.monitoring.yml for local testing, Redis DB 3 for metrics storage, and comprehensive MONITORING.md guide. Metrics cover devices, TR-069/TR-369 sessions, queue performance, alarms, database connections, and cache hit ratios.
- **Software Auto-Update System**: Carrier-grade automatic deployment tracking, environment-aware versioning, transactional failure handling with rollback, 5-stage health checks, and a GitHub Releases-based system with a web dashboard.
- **Test Infrastructure**: Comprehensive test suite with 5 Fake Services (FakeUspMqttService, FakeUspWebSocketService, FakeUpnpDiscoveryService, FakeParameterDiscoveryService, FakeConnectionRequestService) registered globally in TestCase::setUp() to isolate external dependencies. All Feature tests free from Mockery usage, ensuring deterministic test execution without real network calls to MQTT brokers, WebSocket servers, or HTTP endpoints. Unit tests with complex Mockery marked as @group skip for future refactoring.
- **Audit Log System**: Comprehensive compliance and security tracking system for all CRUD operations and business-critical actions. Features polymorphic audit_logs table with change tracking (old/new values), AuditLogger service for centralized logging, Auditable trait for automatic model tracking, RecordAuditLog middleware for HTTP requests, RESTful API endpoints with filtering/search/export (CSV/JSON), statistical summaries, and audit:cleanup command for retention policy enforcement. Supports compliance standards (SOC 2, ISO 27001, HIPAA, GDPR, PCI DSS) with compliance-critical flag for permanent retention. Documented in SECURITY.md with usage patterns, event categories, severity levels, and forensic analysis workflows.

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
- **Compliance & Audit Logging**: Carrier-grade audit trail system for regulatory compliance (SOC 2, ISO 27001, HIPAA, GDPR, PCI DSS). Tracks all CRUD operations, authentication events, authorization failures, configuration changes, firmware upgrades, bulk operations, and data exports. Supports automatic model tracking via Auditable trait, manual logging for business actions, queued logging for high volume, and CSV/JSON export for compliance reporting. Retention policy with compliance-critical preservation and automated cleanup via scheduled command.

# External Dependencies
- **PostgreSQL 16+**: Primary relational database.
- **Redis 7+**: Queue driver for Laravel Horizon, WebSocket message routing, and Prometheus metrics storage (DB 0: default, DB 1: cache, DB 2: session, DB 3: metrics).
- **Laravel Horizon**: Manages Redis queues.
- **Guzzle**: HTTP client.
- **Google Protocol Buffers v4.32.1**: For TR-369 USP message encoding/decoding.
- **PHP-MQTT Client v1.6.1**: For USP broker-based transport.
- **Prosody XMPP Server**: For TR-369 USP XMPP transport.
- **pdahal/php-xmpp v1.0.1**: PHP XMPP client library.
- **stomp-php/stomp-php v5.1.3**: Production STOMP client for TR-262 implementation with support for ActiveMQ, RabbitMQ, Apollo, Artemis brokers.
- **promphp/prometheus_client_php v2.14.1**: Prometheus metrics exporter for PHP with Redis storage adapter.
- **Soft UI Dashboard**: Laravel template for the admin interface.
- **Chart.js**: JavaScript library for interactive charts.
- **FontAwesome**: Icon library.
- **Nginx**: Production web server and reverse proxy.
- **Supervisor/Systemd**: Process management.
- **OpenAI**: For AI-powered configuration and diagnostics.
- **Prometheus**: Time-series metrics database (monitoring stack).
- **Grafana**: Visualization and dashboarding platform (monitoring stack).
- **AlertManager**: Alert routing and notification system (monitoring stack).