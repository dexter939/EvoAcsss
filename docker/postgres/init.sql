-- ACS PostgreSQL Database Initialization
-- Production-ready schema optimizations

-- Enable necessary extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm"; -- For fuzzy search (vendor OUI matching)
CREATE EXTENSION IF NOT EXISTS "btree_gin"; -- For optimized indexing
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements"; -- For query performance monitoring

-- Create dedicated schema for ACS
CREATE SCHEMA IF NOT EXISTS acs;

-- Set default search path
ALTER DATABASE acs_production SET search_path TO acs, public;

-- Performance tuning
ALTER SYSTEM SET shared_buffers = '256MB';
ALTER SYSTEM SET effective_cache_size = '1GB';
ALTER SYSTEM SET maintenance_work_mem = '128MB';
ALTER SYSTEM SET checkpoint_completion_target = '0.9';
ALTER SYSTEM SET wal_buffers = '16MB';
ALTER SYSTEM SET default_statistics_target = '100';
ALTER SYSTEM SET random_page_cost = '1.1';
ALTER SYSTEM SET effective_io_concurrency = '200';
ALTER SYSTEM SET work_mem = '4MB';
ALTER SYSTEM SET min_wal_size = '1GB';
ALTER SYSTEM SET max_wal_size = '4GB';

-- Connection pooling
ALTER SYSTEM SET max_connections = '200';

-- Logging for production
ALTER SYSTEM SET log_min_duration_statement = '1000'; -- Log queries > 1s
ALTER SYSTEM SET log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h ';
ALTER SYSTEM SET log_checkpoints = 'on';
ALTER SYSTEM SET log_connections = 'on';
ALTER SYSTEM SET log_disconnections = 'on';
ALTER SYSTEM SET log_lock_waits = 'on';

-- Reload configuration
SELECT pg_reload_conf();

-- Grant privileges to ACS user
GRANT ALL PRIVILEGES ON SCHEMA acs TO acs_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA acs TO acs_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA acs TO acs_user;

-- Default privileges for future objects
ALTER DEFAULT PRIVILEGES IN SCHEMA acs GRANT ALL ON TABLES TO acs_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA acs GRANT ALL ON SEQUENCES TO acs_user;

-- Vacuum settings for high-write workload
ALTER SYSTEM SET autovacuum_vacuum_scale_factor = '0.05';
ALTER SYSTEM SET autovacuum_analyze_scale_factor = '0.02';

COMMENT ON DATABASE acs_production IS 'ACS (Auto Configuration Server) - Production Database';
