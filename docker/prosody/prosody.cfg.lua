-- ACS Prosody XMPP Server Configuration
-- For TR-369 USP XMPP Transport

-- Server identification
prosody_user = "prosody"
pidfile = "/var/run/prosody/prosody.pid"

-- Modules
modules_enabled = {
    -- Core
    "roster";
    "saslauth";
    "tls";
    "dialback";
    "disco";
    "carbons";
    "pep";
    "private";
    "blocklist";
    "vcard4";
    "vcard_legacy";
    "version";
    "uptime";
    "time";
    "ping";
    "register";
    "admin_adhoc";
    
    -- USP specific
    "mam";          -- Message Archive Management
    "offline";      -- Offline messages
    "announce";     -- Broadcast messages
    "http";         -- HTTP server
    "http_files";   -- Serve static files
}

modules_disabled = {
    -- Disabled for production
}

-- Allow registration
allow_registration = false

-- Authentication
authentication = "internal_hashed"

-- Storage
storage = "internal"

-- Logging
log = {
    info = "/var/log/prosody/prosody.log";
    error = "/var/log/prosody/prosody.err";
    "*syslog";
}

-- Network settings
http_ports = { 5280 }
http_interfaces = { "*" }
https_ports = { 5281 }
https_interfaces = { "*" }

-- SSL/TLS
ssl = {
    key = "/etc/prosody/certs/key.pem";
    certificate = "/etc/prosody/certs/cert.pem";
}

c2s_require_encryption = false -- Set to true in production with valid certs
s2s_require_encryption = false
s2s_secure_auth = false

-- Archive settings (for USP message persistence)
archive_expires_after = "1w"
default_archive_policy = true

-- Virtual host for ACS domain
VirtualHost "localhost"
    enabled = true
    
-- Component for USP devices
Component "devices.localhost"
    component_secret = "changeme_component_secret"

-- Admin users
admins = { "admin@localhost" }

-- Limits
limits = {
    c2s = {
        rate = "10kb/s";
        burst = "2s";
    };
    s2sin = {
        rate = "30kb/s";
        burst = "2s";
    };
}

-- USP-specific settings
mam_default_archive_policy = true
max_archive_query_results = 100
max_history_messages = 1000
