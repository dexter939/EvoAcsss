# 🚀 ACS Production Deployment Guide

Guida completa per il deployment carrier-grade del sistema ACS (Auto Configuration Server) in ambiente production.

---

## 📋 Table of Contents

1. [Requisiti di Sistema](#requisiti-di-sistema)
2. [Architettura Deployment](#architettura-deployment)
3. [Preparazione Pre-Deployment](#preparazione-pre-deployment)
4. [Configurazione Secrets](#configurazione-secrets)
5. [Deployment con Docker](#deployment-con-docker)
6. [Verifica e Testing](#verifica-e-testing)
7. [Backup e Disaster Recovery](#backup-e-disaster-recovery)
8. [Monitoraggio](#monitoraggio)
9. [Troubleshooting](#troubleshooting)
10. [Scaling e High Availability](#scaling-e-high-availability)

---

## 📦 Requisiti di Sistema

### Hardware Minimo (per 100K dispositivi)
- **CPU**: 16 cores (x86_64)
- **RAM**: 64 GB
- **Storage**: 500 GB SSD (NVMe preferito)
- **Network**: 10 Gbps

### Software
- **OS**: Ubuntu 22.04 LTS / Debian 12 / Rocky Linux 9
- **Docker**: 24.0+
- **Docker Compose**: 2.20+
- **OpenSSL**: 1.1+
- **Git**: 2.30+

### Porte Richieste
- `80/TCP`: HTTP (redirect to HTTPS)
- `443/TCP`: HTTPS (Web Interface)
- `5432/TCP`: PostgreSQL (interno)
- `6379/TCP`: Redis (interno)
- `7547/TCP`: TR-069 ACS
- `5222/TCP`: XMPP Client-to-Server (TR-369 USP)
- `5269/TCP`: XMPP Server-to-Server
- `5280/TCP`: XMPP HTTP
- `1883/TCP`: MQTT (se USP MQTT esterno)
- `6001/TCP`: WebSocket (se USP WebSocket)

---

## 🏗️ Architettura Deployment

```
┌─────────────────────────────────────────────────┐
│           Internet / CPE Devices                │
└─────────────────┬───────────────────────────────┘
                  │
         ┌────────▼────────┐
         │   Nginx LB      │ (443, 7547)
         │   + SSL/TLS     │
         └────────┬────────┘
                  │
    ┌─────────────┼─────────────┐
    │             │             │
┌───▼────┐   ┌───▼────┐   ┌───▼────┐
│ ACS-1  │   │ ACS-2  │   │ ACS-3  │
│ App    │   │ App    │   │ App    │
└───┬────┘   └───┬────┘   └───┬────┘
    │            │            │
    └────────────┼────────────┘
                 │
       ┌─────────┼─────────┐
       │         │         │
   ┌───▼───┐ ┌──▼──┐  ┌───▼────┐
   │ PgSQL │ │Redis│  │Prosody │
   │  HA   │ │Sent.│  │ XMPP   │
   └───────┘ └─────┘  └────────┘
```

---

## 🔧 Preparazione Pre-Deployment

### 1. Clone Repository

```bash
git clone https://github.com/your-org/acs-server.git
cd acs-server
```

### 2. Verifica Requisiti

```bash
# Check Docker version
docker --version  # Must be 24.0+

# Check Docker Compose
docker compose version  # Must be 2.20+

# Check disk space
df -h  # Ensure 500GB+ available
```

### 3. Crea Struttura Directory

```bash
mkdir -p backups logs docker/nginx/ssl
chmod 755 backups logs
```

---

## 🔐 Configurazione Secrets

### 1. Template Environment File

Crea `.env` dalla seguente struttura (NON committare in Git!):

```bash
# Application
APP_NAME="ACS Production Server"
APP_ENV=production
APP_KEY=                    # Generare con: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://acs.example.com

# Database PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=acs_production
DB_USERNAME=acs_user
DB_PASSWORD=                # Generare strong password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=             # Generare strong password

# Cache & Session
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SESSION_SECRET=             # Generare random 64 chars

# Queue
QUEUE_CONNECTION=redis

# XMPP (TR-369 USP)
XMPP_HOST=prosody
XMPP_PORT=5222
XMPP_DOMAIN=acs.example.com
XMPP_USERNAME=acs_controller
XMPP_PASSWORD=              # Generare strong password

# OpenAI (AI Assistant)
OPENAI_API_KEY=sk-         # Your OpenAI API key

# ACS Configuration
ACS_API_KEY=                # Generare UUID o strong key

# TR Protocols
USP_MQTT_ENABLED=true
USP_WEBSOCKET_ENABLED=true
TR069_ENABLED=true
TR262_ENABLED=true

# Mail (Notifications)
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=              # Mail password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
```

### 2. Genera Secrets

```bash
# Generate APP_KEY
docker run --rm php:8.3-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"

# Generate strong passwords (64 chars)
openssl rand -base64 48

# Generate UUIDs
uuidgen
```

### 3. SSL Certificates

**Opzione A: Let's Encrypt (Production Recommended)**

```bash
# Install certbot
sudo apt install certbot

# Generate certificates
sudo certbot certonly --standalone -d acs.example.com

# Copy certificates
sudo cp /etc/letsencrypt/live/acs.example.com/fullchain.pem docker/nginx/ssl/cert.pem
sudo cp /etc/letsencrypt/live/acs.example.com/privkey.pem docker/nginx/ssl/key.pem
sudo chmod 644 docker/nginx/ssl/*.pem
```

**Opzione B: Self-Signed (Development/Testing)**

```bash
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout docker/nginx/ssl/key.pem \
  -out docker/nginx/ssl/cert.pem \
  -subj "/C=IT/ST=Italy/L=Rome/O=ACS/CN=acs.example.com"
```

---

## 🐳 Deployment con Docker

### 1. Build Images

```bash
# Build production images
docker-compose build --no-cache
```

### 2. Deploy con Script Automatico

```bash
# Execute deployment script
chmod +x deploy.sh
./deploy.sh production
```

### 3. Deployment Manuale (Step-by-Step)

```bash
# 1. Start services
docker-compose up -d

# 2. Wait for database
sleep 15

# 3. Run migrations
docker-compose exec -T acs-app php artisan migrate --force

# 4. Optimize application
docker-compose exec -T acs-app php artisan config:cache
docker-compose exec -T acs-app php artisan route:cache
docker-compose exec -T acs-app php artisan view:cache
docker-compose exec -T acs-app php artisan optimize

# 5. Create storage symlink
docker-compose exec -T acs-app php artisan storage:link

# 6. Create admin user
docker-compose exec -T acs-app php artisan make:filament-user
```

### 4. Verify Deployment

```bash
# Check container status
docker-compose ps

# Check health
curl -k https://localhost/health  # Should return "OK"

# Check logs
docker-compose logs -f acs-app

# Verify database
docker-compose exec acs-app php artisan migrate:status
```

---

## ✅ Verifica e Testing

### 1. Health Checks

```bash
# Application health
docker-compose exec acs-app php artisan system:health

# Database health
docker-compose exec postgres pg_isready -U acs_user

# Redis health
docker-compose exec redis redis-cli ping

# Nginx health
curl http://localhost/health
```

### 2. Functional Tests

```bash
# Test TR-069 endpoint
curl -X POST https://localhost:7547/tr069 \
  -H "Content-Type: text/xml" \
  --data '<soap:Envelope>...</soap:Envelope>'

# Test API endpoint
curl -k https://localhost/api/v1/system/health \
  -H "X-API-Key: ${ACS_API_KEY}"

# Test web interface
curl -k https://localhost/login
```

### 3. Performance Tests

```bash
# Test concurrent connections
ab -n 1000 -c 100 https://localhost/health

# Monitor resource usage
docker stats
```

---

## 💾 Backup e Disaster Recovery

### 1. Automated Backups

```bash
# Setup daily cron job
crontab -e

# Add this line (daily at 3 AM)
0 3 * * * /path/to/acs-server/backup.sh full >> /var/log/acs-backup.log 2>&1
```

### 2. Manual Backup

```bash
# Full database backup
./backup.sh full

# Incremental backup
./backup.sh incremental
```

### 3. Restore from Backup

```bash
# List available backups
ls -lh backups/

# Restore specific backup
./restore.sh backups/acs_backup_full_20250123_030000.sql.gz
```

### 4. Backup to Remote Storage

```bash
# S3 backup (esempio)
aws s3 sync backups/ s3://your-bucket/acs-backups/

# Rsync to remote server
rsync -avz backups/ user@backup-server:/backups/acs/
```

---

## 📊 Monitoraggio

### 1. Log Locations

```bash
# Application logs
docker-compose logs -f acs-app

# Nginx access logs
docker-compose exec nginx tail -f /var/log/nginx/access.log

# Nginx error logs
docker-compose exec nginx tail -f /var/log/nginx/error.log

# PostgreSQL logs
docker-compose logs -f postgres

# Queue worker logs
docker-compose logs -f horizon
```

### 2. Metrics Endpoints

- **System Health**: `GET /api/v1/system/health`
- **Telemetry**: `GET /api/v1/telemetry/current`
- **STOMP Metrics**: `GET /api/v1/stomp/metrics`
- **Database Status**: `docker-compose exec acs-app php artisan migrate:status`

### 3. Resource Monitoring

```bash
# Container resources
docker stats

# Disk usage
df -h
du -sh storage/

# Database size
docker-compose exec postgres psql -U acs_user -d acs_production -c \
  "SELECT pg_size_pretty(pg_database_size('acs_production'));"
```

---

## 🔧 Troubleshooting

### Problem: Container Won't Start

```bash
# Check logs
docker-compose logs acs-app

# Check disk space
df -h

# Verify .env file
cat .env | grep -v '^#'

# Rebuild without cache
docker-compose build --no-cache
docker-compose up -d
```

### Problem: Database Connection Failed

```bash
# Check PostgreSQL is running
docker-compose ps postgres

# Test connection manually
docker-compose exec acs-app php artisan tinker
>>> DB::connection()->getPdo();

# Reset database
docker-compose exec postgres psql -U acs_user -d acs_production
```

### Problem: Queue Not Processing

```bash
# Restart queue worker
docker-compose restart horizon

# Check Redis connection
docker-compose exec acs-app php artisan queue:monitor

# Clear failed jobs
docker-compose exec acs-app php artisan queue:flush
```

### Problem: High Memory Usage

```bash
# Clear all caches
docker-compose exec acs-app php artisan cache:clear
docker-compose exec acs-app php artisan config:clear
docker-compose exec acs-app php artisan route:clear
docker-compose exec acs-app php artisan view:clear

# Restart PHP-FPM
docker-compose restart acs-app

# Increase memory limit in docker/php/php.ini
memory_limit = 1024M
```

---

## 📈 Scaling e High Availability

### 1. Horizontal Scaling

Modifica `docker-compose.yml`:

```yaml
services:
  acs-app:
    deploy:
      replicas: 3  # Multiple instances
```

### 2. Database Replication

```bash
# Setup PostgreSQL streaming replication
# (requires docker-compose-ha.yml configuration)
docker-compose -f docker-compose.yml -f docker-compose-ha.yml up -d
```

### 3. Redis Sentinel

```yaml
# Add to docker-compose.yml
redis-sentinel-1:
  image: redis:7-alpine
  command: redis-sentinel /etc/redis/sentinel.conf
  
redis-sentinel-2:
  image: redis:7-alpine
  command: redis-sentinel /etc/redis/sentinel.conf
  
redis-sentinel-3:
  image: redis:7-alpine
  command: redis-sentinel /etc/redis/sentinel.conf
```

### 4. Load Balancer

```bash
# Use nginx as external load balancer
upstream acs_backend {
    server acs-app-1:8080;
    server acs-app-2:8080;
    server acs-app-3:8080;
    least_conn;
}
```

---

## 🔒 Security Best Practices

1. **Use strong passwords** (minimum 32 characters)
2. **Enable SSL/TLS** with valid certificates
3. **Configure firewall**:
   ```bash
   ufw allow 22/tcp   # SSH
   ufw allow 80/tcp   # HTTP
   ufw allow 443/tcp  # HTTPS
   ufw allow 7547/tcp # TR-069
   ufw enable
   ```
4. **Regular updates**:
   ```bash
   docker-compose pull
   docker-compose up -d
   ```
5. **Enable fail2ban** for SSH protection
6. **Rotate secrets** ogni 90 giorni
7. **Monitor security logs**:
   ```bash
   docker-compose logs -f | grep -i "fail\|error\|attack"
   ```

---

## 📞 Support

- **Documentation**: https://docs.acs.example.com
- **Issues**: https://github.com/your-org/acs-server/issues
- **Email**: support@example.com

---

## 📝 Checklist Pre-Production

- [ ] .env configurato con tutti i secrets
- [ ] SSL certificates installati (validi)
- [ ] Database backup automatico configurato
- [ ] Firewall rules configurate
- [ ] Health checks funzionanti
- [ ] Monitoring attivo
- [ ] Log rotation configurato
- [ ] Disaster recovery plan testato
- [ ] Load testing eseguito (100K devices)
- [ ] Security audit completato
- [ ] Team training completato
- [ ] Runbook operativo disponibile

---

**Deployment creato da ACS Team | Versione 1.0 | Gennaio 2025**
