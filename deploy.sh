#!/bin/bash
# ACS Production Deployment Script
# Usage: ./deploy.sh [production|staging]

set -e

ENV=${1:-production}
COMPOSE_FILE="docker-compose.yml"

echo "🚀 ACS Deployment Script - Environment: $ENV"
echo "================================================"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker is not installed${NC}"
    exit 1
fi

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo -e "${RED}❌ Docker Compose is not installed${NC}"
    exit 1
fi

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}⚠️  .env file not found. Creating from template...${NC}"
    if [ ! -f ".env.example" ]; then
        echo -e "${RED}❌ .env.example not found${NC}"
        exit 1
    fi
    cp .env.example .env
    echo -e "${YELLOW}⚠️  Please configure .env file with production values${NC}"
    exit 1
fi

# Verify required environment variables
REQUIRED_VARS=("APP_KEY" "DB_PASSWORD" "REDIS_PASSWORD")
for var in "${REQUIRED_VARS[@]}"; do
    if ! grep -q "^${var}=" .env || grep -q "^${var}=CHANGE_THIS" .env; then
        echo -e "${RED}❌ Required variable $var is not set in .env${NC}"
        exit 1
    fi
done

echo -e "${GREEN}✓ Environment file validated${NC}"

# Create necessary directories
echo "📁 Creating directories..."
mkdir -p storage/app/public
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs
mkdir -p bootstrap/cache
mkdir -p docker/nginx/ssl

echo -e "${GREEN}✓ Directories created${NC}"

# Generate SSL certificates if not exist
if [ ! -f "docker/nginx/ssl/cert.pem" ] || [ ! -f "docker/nginx/ssl/key.pem" ]; then
    echo "🔐 Generating self-signed SSL certificates..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout docker/nginx/ssl/key.pem \
        -out docker/nginx/ssl/cert.pem \
        -subj "/C=IT/ST=Italy/L=Rome/O=ACS/OU=IT/CN=acs.local"
    echo -e "${GREEN}✓ SSL certificates generated${NC}"
    echo -e "${YELLOW}⚠️  Use valid certificates in production!${NC}"
fi

# Build Docker images
echo "🔨 Building Docker images..."
docker-compose -f $COMPOSE_FILE build --no-cache

echo -e "${GREEN}✓ Images built successfully${NC}"

# Stop existing containers
echo "🛑 Stopping existing containers..."
docker-compose -f $COMPOSE_FILE down

# Start services
echo "🚀 Starting services..."
docker-compose -f $COMPOSE_FILE up -d

# Wait for database
echo "⏳ Waiting for database to be ready..."
sleep 10

# Run migrations
echo "📊 Running database migrations..."
docker-compose -f $COMPOSE_FILE exec -T acs-app php artisan migrate --force

# Clear and optimize caches
echo "🧹 Optimizing application..."
docker-compose -f $COMPOSE_FILE exec -T acs-app php artisan config:cache
docker-compose -f $COMPOSE_FILE exec -T acs-app php artisan route:cache
docker-compose -f $COMPOSE_FILE exec -T acs-app php artisan view:cache
docker-compose -f $COMPOSE_FILE exec -T acs-app php artisan optimize

# Create storage symlink
echo "🔗 Creating storage symlink..."
docker-compose -f $COMPOSE_FILE exec -T acs-app php artisan storage:link || true

# Check health
echo "🏥 Checking service health..."
sleep 5

# Test database connection
if docker-compose -f $COMPOSE_FILE exec -T acs-app php artisan migrate:status &> /dev/null; then
    echo -e "${GREEN}✓ Database connection OK${NC}"
else
    echo -e "${RED}❌ Database connection failed${NC}"
    docker-compose -f $COMPOSE_FILE logs acs-app
    exit 1
fi

# Show running containers
echo ""
echo "📋 Running containers:"
docker-compose -f $COMPOSE_FILE ps

echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo "🌐 ACS is now running at:"
echo "   - HTTP:  http://localhost"
echo "   - HTTPS: https://localhost"
echo "   - TR-069: https://localhost:7547/tr069"
echo ""
echo "📊 To view logs:"
echo "   docker-compose logs -f acs-app"
echo ""
echo "🔧 To run artisan commands:"
echo "   docker-compose exec acs-app php artisan <command>"
echo ""
