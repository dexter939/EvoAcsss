#!/bin/bash
# ACS Database Restore Script
# Usage: ./restore.sh <backup_file>

set -e

BACKUP_FILE=$1
COMPOSE_FILE="docker-compose.yml"

echo "♻️  ACS Database Restore Script"
echo "==============================="

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check if backup file is provided
if [ -z "$BACKUP_FILE" ]; then
    echo -e "${RED}❌ Backup file not specified${NC}"
    echo "Usage: ./restore.sh <backup_file>"
    echo ""
    echo "Available backups:"
    ls -lh backups/
    exit 1
fi

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}❌ Backup file not found: $BACKUP_FILE${NC}"
    exit 1
fi

# Get database credentials from .env
if [ -f ".env" ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo -e "${RED}❌ .env file not found${NC}"
    exit 1
fi

DB_NAME=${DB_DATABASE:-acs_production}
DB_USER=${DB_USERNAME:-acs_user}

# Warning
echo -e "${YELLOW}⚠️  WARNING: This will REPLACE all data in database '$DB_NAME'${NC}"
echo -e "${YELLOW}⚠️  Make sure you have a recent backup before proceeding!${NC}"
echo ""
read -p "Are you sure you want to continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Restore cancelled."
    exit 0
fi

# Check if database container is running
if ! docker-compose -f $COMPOSE_FILE ps | grep -q "acs-postgres.*Up"; then
    echo -e "${RED}❌ PostgreSQL container is not running${NC}"
    exit 1
fi

# Decompress if gzipped
RESTORE_FILE="$BACKUP_FILE"
if [[ "$BACKUP_FILE" == *.gz ]]; then
    echo "🗜️  Decompressing backup..."
    RESTORE_FILE="${BACKUP_FILE%.gz}"
    gunzip -c "$BACKUP_FILE" > "$RESTORE_FILE"
fi

echo "🔄 Restoring database from: $RESTORE_FILE"

# Stop application to prevent writes during restore
echo "🛑 Stopping ACS application..."
docker-compose -f $COMPOSE_FILE stop acs-app horizon

# Drop existing connections
echo "🔌 Closing existing database connections..."
docker-compose -f $COMPOSE_FILE exec -T postgres psql -U "$DB_USER" -d postgres -c \
    "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();" \
    || true

# Restore based on file extension
if [[ "$RESTORE_FILE" == *.dump ]]; then
    echo "📥 Restoring from custom format dump..."
    docker-compose -f $COMPOSE_FILE exec -T postgres pg_restore \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        --verbose \
        --clean \
        --if-exists \
        --no-owner \
        --no-acl \
        < "$RESTORE_FILE"
elif [[ "$RESTORE_FILE" == *.sql ]]; then
    echo "📥 Restoring from SQL file..."
    docker-compose -f $COMPOSE_FILE exec -T postgres psql \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        < "$RESTORE_FILE"
else
    echo -e "${RED}❌ Unsupported backup file format${NC}"
    exit 1
fi

# Clean up decompressed file if it was created
if [ "$RESTORE_FILE" != "$BACKUP_FILE" ]; then
    rm -f "$RESTORE_FILE"
fi

# Restart application
echo "🚀 Restarting ACS application..."
docker-compose -f $COMPOSE_FILE start acs-app horizon

# Run migrations to ensure schema is up to date
echo "📊 Running migrations..."
sleep 5
docker-compose -f $COMPOSE_FILE exec -T acs-app php artisan migrate --force

echo ""
echo -e "${GREEN}=============================="
echo "✅ Restore completed successfully!"
echo "==============================${NC}"
echo ""
echo "🏥 Checking application health..."
docker-compose -f $COMPOSE_FILE exec -T acs-app php artisan migrate:status

echo ""
echo -e "${GREEN}✓ Database restored and application is running${NC}"
echo ""
