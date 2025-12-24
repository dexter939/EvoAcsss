# Overview
The ACS (Auto Configuration Server) project is a carrier-grade Laravel 11 system designed for managing over 100,000 CPE devices. It supports a comprehensive suite of 10 TR protocols and offers functionalities like device auto-registration, zero-touch provisioning, firmware management, advanced remote diagnostics, real-time STOMP messaging, comprehensive monitoring & alerting, and AI-powered configuration assistance. The business vision is to deliver a highly scalable, performant solution for large-scale device management in telecommunication environments with carrier-grade reliability and enterprise security.

# User Preferences
I prefer clear and concise explanations. When making changes, prioritize core functionalities and ensure backward compatibility. I prefer an iterative development approach, focusing on delivering functional components incrementally. Please ask for confirmation before implementing significant architectural changes or altering existing API contracts. Ensure all new features have comprehensive test coverage. I want the agent to use proper markdown formatting in all its responses.

# System Architecture

## UI/UX Decisions
The web interface utilizes the **Soft UI Dashboard Pro Laravel** template for a modern, responsive design with **comprehensive GUI optimizations** following Creative Tim best practices. Key UI elements include a redesigned dashboard, enhanced CPE device configuration editors, a real-time alarms system, card-based device listings, a tabbed device details modal, an AI-Powered Configuration Assistant Dashboard, a Network Topology Map, an Advanced Provisioning Dashboard, a Performance Monitoring Dashboard, and an Advanced Monitoring & Alerting System.

**GUI Optimizations (NEW - October 2025)**:
- Global CSS utilities (`soft-ui-enhancements.css`) with shadow-xl, gradients, animations
- Enhanced cards with hover effects and soft shadows
- Gradient buttons and badges throughout
- Improved tables with hover states and responsive wrappers
- Consistent icon shapes and spacing
- Animation utilities (fade-in, slide-up, skeleton loading)
- Complete optimization guide documented in `docs/GUI_OPTIMIZATION_GUIDE.md`

**Mobile Application**: Native React Native/Expo mobile app (v2.0.0 - Phase 2) for field technicians and administrators with iOS/Android support. Phase 1 features: token-based authentication, device management, real-time statistics dashboard. **Phase 2 features (NEW)**: Real-time alarm monitoring with 30s auto-refresh, alarm acknowledge/clear flows, TR-143 diagnostics execution (ping/traceroute), device details screen with comprehensive metadata, QR scanner setup guide (expo-camera), push notifications documentation. Built with carrier-grade security (environment-based API configuration, no hardcoded secrets).

## Technical Implementations
- **Protocol Support**: Comprehensive implementation of 10 production-ready TR protocols (TR-069, TR-104, TR-106, TR-111, TR-135, TR-140, TR-157, TR-181, TR-262, TR-369) with BBF-compliant services, including real STOMP client for TR-262 and complete TR-369 USP transport layer implementations (HTTP, MQTT, WebSocket).
- **Database**: PostgreSQL with optimized indexing and multi-tenancy.
- **Performance Optimizations**: Strategic database indexes, multi-tier Redis caching, and a centralized CacheService.
- **Asynchronous Processing**: Laravel Horizon with Redis queues for provisioning, firmware, and TR-069 requests.
- **API Security**: Dual authentication system for v1 RESTful endpoints - Laravel Sanctum token-based authentication for mobile apps (with AlarmController endpoints), API Key authentication for server-to-server calls, rate limiting, and DDoS protection.
- **Security Hardening**: Enterprise-grade security features including RBAC, input validation, security audit logging, IP blacklist management, and a comprehensive Audit Log System.
- **Multi-Tenant Device Access Control**: Role-based device scoping via `user_devices` pivot table with three permission levels (viewer, manager, admin), enforced by `EnsureDeviceAccess` middleware. **NEW (November 2025)**: UserDeviceScope global scope for automatic query filtering, DeviceAccessController for grant/revoke operations, CpeDevicePolicy for fine-grained authorization, complete audit logging for access management. **Phase 0-2 Complete (December 2025)**: Tenant/TenantCredential models, TenantDiscoveryService (subdomain/header/token/user resolution), TenantContext singleton, dual-write mode with backward compatibility (tenant_id nullable), HasTenant trait for auto-scoping, TenantAwareTokenService for Sanctum token scoping, tenant_id added to cpe_devices and alarms tables. **Phase 2**: TenantScope global scope with enforce_isolation flag, IdentifyTenant/EnforceTenantContext middleware applied to ACS web routes and API v1 routes. **Phase 3 Token Scoping (December 2025)**: ValidateTokenTenant middleware for Sanctum token tenant validation, AuthController integration with TenantAwareTokenService, cross-tenant token access detection with security logging, dedicated security log channel (90-day retention). Feature flags in config/tenant.php for gradual rollout. See `docs/MULTI_TENANT_AUTH_ROADMAP.md` for Phase 4 roadmap.
- **Real-Time Broadcasting (Laravel Reverb v1.6.0)**: WebSocket server for real-time alarm notifications with carrier-grade multi-tenant isolation. Broadcasts to user-specific private channels only for users with explicit device access. **LIMITATION**: Currently broadcasts only to users with explicit `user_devices` pivot entries; users with inherited tenant access require tenant_id migration for full coverage. See `docs/WEBSOCKET_LIMITATIONS_AND_ROADMAP.md` for migration path.
- **Scalability**: Achieved through database optimizations, Redis caching, and a high-throughput queue system.
- **Production Deployment Infrastructure**: Multi-tier deployment strategy supporting Docker/Compose and Kubernetes (with a production-grade Helm chart for carrier-grade deployments up to 100K+ devices).
- **Monitoring & Observability**: Carrier-grade monitoring infrastructure with Prometheus metrics exporter, Grafana dashboards, PrometheusRule with alert definitions, and AlertManager configuration.
- **Software Auto-Update System**: Carrier-grade automatic deployment tracking, environment-aware versioning, transactional failure handling with rollback, and health checks.
- **Test Infrastructure**: Comprehensive test suite with 5 Fake Services to isolate external dependencies and ensure deterministic test execution. Includes a regression test suite for JavaScript integrity.
- **JavaScript Quality Assurance**: Custom ESLint-based linting system for Blade templates, validating JavaScript for duplicate declarations.
- **Audit Log System**: Comprehensive compliance and security tracking system for all CRUD operations and business-critical actions, supporting various compliance standards.
- **Mobile Application Architecture**: React Native/Expo app with TypeScript, environment-aware configuration (dotenv + expo-constants), API service layer (Axios) consuming Laravel REST endpoints, React Navigation (Stack + Bottom Tabs), AsyncStorage for offline token management, secure credential handling via .env files, and multi-source Constants support for dev/production builds. Backend provides mobile endpoints: /api/auth/login, /api/auth/logout (Sanctum tokens), /api/v1/alarms/* (CRUD, stats, acknowledge, clear), /api/v1/diagnostics/* (TR-143 test execution), /api/v1/devices/* (device management). Phase 2 screens: AlarmsScreen (30s auto-refresh), DiagnosticsScreen (ping/traceroute execution), DeviceDetailsScreen, QRScannerScreen (documented placeholder), Push Notifications setup guide.

## Feature Specifications
- **Device Management**: Auto-registration, zero-touch provisioning with configuration profiles, firmware management, and a multi-vendor device library.
- **Advanced Provisioning**: Enterprise-grade system with bulk operations, scheduling, templates, conditional rules, configuration versioning, and staged rollout.
- **TR-181 Data Model**: Parameters stored with type, path, access, and update history.
- **Connection Management**: System-initiated connection requests and TR-369 subscription/notification.
- **AI-Powered Configuration Assistant**: Integrates OpenAI GPT-4o-mini for template generation, configuration validation, optimization, and diagnostic analysis.
- **Multi-Tenant Architecture**: Supports multiple customers with a 3-level web hierarchy.
- **Configuration Templates**: Database-driven templates with validation rules and BBF-compliant parameter validation.
- **TR-143 Diagnostics**: UI and workflow for Ping, Traceroute, Download, and Upload tests.
- **Network Topology Map**: Real-time interactive visualization of connected LAN/WiFi clients.
- **NAT Traversal & Pending Commands Queue**: Solution for executing TR-069 commands on devices behind NAT/firewalls.
- **Real-time Alarms & Monitoring**: Carrier-grade alarm management with SSE real-time notifications, event-driven processing, and a comprehensive monitoring and alerting system.
- **Multi-Vendor Device Library**: Comprehensive vendor management system with dedicated UI views, firmware compatibility matrix, and OUI-based auto-detection.
- **Bulk Operations API**: RESTful endpoints for bulk vendor detection, template application, and firmware compatibility checking.
- **Compliance & Audit Logging**: Carrier-grade audit trail system for regulatory compliance (SOC 2, ISO 27001, HIPAA, GDPR, PCI DSS).
- **Mobile App Features**:
  - **Phase 1 MVP**: Authentication with token persistence, dashboard with device/alarm statistics, device list with search/filter, profile management.
  - **Phase 2 (COMPLETED)**: Real-time alarm monitoring (30s polling), alarm acknowledge/clear flows, TR-143 diagnostic execution (ping/traceroute), device details screen, QR scanner setup guide (expo-camera integration), push notifications setup documentation.
  - **Phase 3 (IMPLEMENTED - November 2025)**: Backend WebSocket infrastructure with Laravel Reverb, secure multi-tenant alarm broadcasting, complete mobile integration guide with code examples for WebSocket client setup, offline sync with AsyncStorage, advanced filtering UI, and bulk operations interface. See `docs/WEBSOCKET_MOBILE_INTEGRATION.md` for implementation details.

# External Dependencies
- **PostgreSQL 16+**: Primary relational database.
- **Redis 7+**: Queue driver for Laravel Horizon, WebSocket message routing, and Prometheus metrics storage.
- **Laravel Reverb v1.6.0**: Built-in WebSocket server for real-time broadcasting (NEW - November 2025).
- **Laravel Horizon**: Manages Redis queues.
- **Guzzle**: HTTP client.
- **Google Protocol Buffers v4.32.1**: For TR-369 USP message encoding/decoding.
- **PHP-MQTT Client v1.6.1**: For USP broker-based transport.
- **Prosody XMPP Server**: For TR-369 USP XMPP transport.
- **pdahal/php-xmpp v1.0.1**: PHP XMPP client library.
- **stomp-php/stomp-php v5.1.3**: Production STOMP client for TR-262 implementation.
- **promphp/prometheus_client_php v2.14.1**: Prometheus metrics exporter for PHP.
- **Soft UI Dashboard**: Laravel template for the admin interface.
- **Chart.js**: JavaScript library for interactive charts.
- **FontAwesome**: Icon library.
- **Nginx**: Production web server and reverse proxy.
- **Supervisor/Systemd**: Process management.
- **OpenAI**: For AI-powered configuration and diagnostics.
- **Prometheus**: Time-series metrics database.
- **Grafana**: Visualization and dashboarding platform.
- **AlertManager**: Alert routing and notification system.
- **React Native 0.74.5**: Mobile application framework.
- **Expo SDK ~51.0.0**: Native features (camera, location, notifications).
- **React Navigation 6.x**: Mobile app navigation.
- **Expo Constants 16.0.2**: Environment variable management for mobile.
- **dotenv 16.4.5**: Environment configuration for mobile builds.