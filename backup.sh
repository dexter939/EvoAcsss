#!/bin/bash
# ACS Database Backup Script
# Usage: ./backup.sh [full|incremental]

set -e

BACKUP_TYPE=${1:-full}
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="./backups"
COMPOSE_FILE="docker-compose.yml"

echo "💾 ACS Database Backup Script"
echo "=============================="
echo "Type: $BACKUP_TYPE"
echo "Timestamp: $TIMESTAMP"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Get database credentials from .env
if [ -f ".env" ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo -e "${RED}❌ .env file not found${NC}"
    exit 1
fi

DB_NAME=${DB_DATABASE:-acs_production}
DB_USER=${DB_USERNAME:-acs_user}
BACKUP_FILE="$BACKUP_DIR/acs_backup_${BACKUP_TYPE}_${TIMESTAMP}.sql"

# Check if database container is running
if ! docker-compose -f $COMPOSE_FILE ps | grep -q "acs-postgres.*Up"; then
    echo -e "${RED}❌ PostgreSQL container is not running${NC}"
    exit 1
fi

echo "📊 Backing up database: $DB_NAME"

# Perform backup
if [ "$BACKUP_TYPE" == "full" ]; then
    echo "🔄 Creating full backup..."
    docker-compose -f $COMPOSE_FILE exec -T postgres pg_dump \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        --verbose \
        --format=custom \
        --blobs \
        --no-owner \
        --no-acl \
        > "$BACKUP_FILE.dump"
    
    # Also create SQL format for easier inspection
    docker-compose -f $COMPOSE_FILE exec -T postgres pg_dump \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        --format=plain \
        > "$BACKUP_FILE"
    
    echo -e "${GREEN}✓ Full backup created${NC}"
    
elif [ "$BACKUP_TYPE" == "incremental" ]; then
    echo "🔄 Creating incremental backup (WAL archive)..."
    docker-compose -f $COMPOSE_FILE exec -T postgres pg_basebackup \
        -U "$DB_USER" \
        -D - \
        -Ft \
        -z \
        -P \
        > "$BACKUP_FILE.tar.gz"
    
    echo -e "${GREEN}✓ Incremental backup created${NC}"
else
    echo -e "${RED}❌ Invalid backup type: $BACKUP_TYPE${NC}"
    echo "Usage: ./backup.sh [full|incremental]"
    exit 1
fi

# Compress SQL file
if [ -f "$BACKUP_FILE" ]; then
    echo "🗜️  Compressing backup..."
    gzip -f "$BACKUP_FILE"
    BACKUP_FILE="${BACKUP_FILE}.gz"
fi

# Get file size
BACKUP_SIZE=$(du -h "$BACKUP_FILE" 2>/dev/null | cut -f1 || echo "unknown")

echo ""
echo -e "${GREEN}=============================="
echo "✅ Backup completed successfully!"
echo "==============================${NC}"
echo ""
echo "📁 Backup file: $BACKUP_FILE"
echo "📦 Size: $BACKUP_SIZE"
echo ""

# Cleanup old backups (keep last 30 days)
RETENTION_DAYS=30
echo "🧹 Cleaning up backups older than $RETENTION_DAYS days..."
find "$BACKUP_DIR" -name "acs_backup_*.sql.gz" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "acs_backup_*.dump" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "acs_backup_*.tar.gz" -mtime +$RETENTION_DAYS -delete

REMAINING=$(ls -1 "$BACKUP_DIR" | wc -l)
echo -e "${GREEN}✓ Cleanup complete. Remaining backups: $REMAINING${NC}"

echo ""
echo "📋 To restore this backup:"
echo "   ./restore.sh $BACKUP_FILE"
echo ""
