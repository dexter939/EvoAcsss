# ACS Production Deployment Makefile
# Semplifica i comandi operativi comuni

.PHONY: help build deploy start stop restart logs status clean backup restore health test

# Default target
.DEFAULT_GOAL := help

# Variables
COMPOSE_FILE := docker-compose.yml
APP_CONTAINER := acs-app
DB_CONTAINER := postgres

##@ General

help: ## Show this help message
	@awk 'BEGIN {FS = ":.*##"; printf "\n\033[1m🚀 ACS Deployment Commands\033[0m\n\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Deployment

build: ## Build Docker images
	@echo "🔨 Building Docker images..."
	docker-compose -f $(COMPOSE_FILE) build --no-cache

deploy: ## Deploy ACS to production
	@echo "🚀 Deploying ACS..."
	./deploy.sh production

start: ## Start all services
	@echo "▶️  Starting services..."
	docker-compose -f $(COMPOSE_FILE) up -d
	@echo "✅ Services started"

stop: ## Stop all services
	@echo "⏸️  Stopping services..."
	docker-compose -f $(COMPOSE_FILE) down
	@echo "✅ Services stopped"

restart: ## Restart all services
	@echo "🔄 Restarting services..."
	docker-compose -f $(COMPOSE_FILE) restart
	@echo "✅ Services restarted"

##@ Database

migrate: ## Run database migrations
	@echo "📊 Running migrations..."
	docker-compose exec -T $(APP_CONTAINER) php artisan migrate --force

migrate-status: ## Show migration status
	docker-compose exec $(APP_CONTAINER) php artisan migrate:status

db-reset: ## Reset database (WARNING: DESTRUCTIVE)
	@echo "⚠️  WARNING: This will delete all data!"
	@read -p "Type 'yes' to confirm: " confirm && [ "$$confirm" = "yes" ] || exit 1
	docker-compose exec $(APP_CONTAINER) php artisan migrate:fresh --force

##@ Cache

cache-clear: ## Clear all caches
	@echo "🧹 Clearing caches..."
	docker-compose exec $(APP_CONTAINER) php artisan cache:clear
	docker-compose exec $(APP_CONTAINER) php artisan config:clear
	docker-compose exec $(APP_CONTAINER) php artisan route:clear
	docker-compose exec $(APP_CONTAINER) php artisan view:clear
	@echo "✅ Caches cleared"

optimize: ## Optimize application for production
	@echo "⚡ Optimizing application..."
	docker-compose exec $(APP_CONTAINER) php artisan config:cache
	docker-compose exec $(APP_CONTAINER) php artisan route:cache
	docker-compose exec $(APP_CONTAINER) php artisan view:cache
	docker-compose exec $(APP_CONTAINER) php artisan optimize
	@echo "✅ Optimization complete"

##@ Monitoring

logs: ## Show live logs
	docker-compose -f $(COMPOSE_FILE) logs -f

logs-app: ## Show application logs
	docker-compose -f $(COMPOSE_FILE) logs -f $(APP_CONTAINER)

logs-nginx: ## Show nginx logs
	docker-compose -f $(COMPOSE_FILE) logs -f nginx

logs-db: ## Show database logs
	docker-compose -f $(COMPOSE_FILE) logs -f $(DB_CONTAINER)

status: ## Show service status
	@echo "📊 Service Status:"
	@docker-compose -f $(COMPOSE_FILE) ps

health: ## Check system health
	@echo "🏥 System Health Check:"
	@docker-compose exec $(APP_CONTAINER) php artisan system:health
	@echo ""
	@echo "📊 Container Status:"
	@docker stats --no-stream

##@ Backup & Restore

backup: ## Create database backup
	@echo "💾 Creating backup..."
	./backup.sh full

backup-incremental: ## Create incremental backup
	@echo "💾 Creating incremental backup..."
	./backup.sh incremental

restore: ## Restore database from backup (specify BACKUP_FILE)
	@if [ -z "$(BACKUP_FILE)" ]; then \
		echo "❌ Error: BACKUP_FILE not specified"; \
		echo "Usage: make restore BACKUP_FILE=backups/acs_backup_full_*.sql.gz"; \
		exit 1; \
	fi
	./restore.sh $(BACKUP_FILE)

##@ Maintenance

shell: ## Open shell in application container
	docker-compose exec $(APP_CONTAINER) /bin/sh

shell-db: ## Open PostgreSQL shell
	docker-compose exec $(DB_CONTAINER) psql -U acs_user -d acs_production

artisan: ## Run artisan command (specify CMD)
	@if [ -z "$(CMD)" ]; then \
		echo "❌ Error: CMD not specified"; \
		echo "Usage: make artisan CMD='make:controller MyController'"; \
		exit 1; \
	fi
	docker-compose exec $(APP_CONTAINER) php artisan $(CMD)

queue-restart: ## Restart queue workers
	docker-compose exec $(APP_CONTAINER) php artisan queue:restart
	docker-compose restart horizon

##@ Cleanup

clean: ## Remove stopped containers and unused volumes
	@echo "🧹 Cleaning up..."
	docker-compose -f $(COMPOSE_FILE) down -v
	docker system prune -f
	@echo "✅ Cleanup complete"

clean-logs: ## Clean old log files
	@echo "🧹 Cleaning old logs..."
	find logs/ -name "*.log" -mtime +30 -delete
	@echo "✅ Old logs cleaned"

##@ Testing

test: ## Run tests
	docker-compose exec $(APP_CONTAINER) php artisan test

test-coverage: ## Run tests with coverage
	docker-compose exec $(APP_CONTAINER) php artisan test --coverage

##@ SSL

ssl-generate: ## Generate self-signed SSL certificate
	@echo "🔐 Generating self-signed SSL certificate..."
	mkdir -p docker/nginx/ssl
	openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
		-keyout docker/nginx/ssl/key.pem \
		-out docker/nginx/ssl/cert.pem \
		-subj "/C=IT/ST=Italy/L=Rome/O=ACS/CN=acs.local"
	@echo "✅ SSL certificate generated"

ssl-renew: ## Renew Let's Encrypt certificate (if using certbot)
	@echo "🔐 Renewing SSL certificate..."
	sudo certbot renew
	@make ssl-copy

ssl-copy: ## Copy Let's Encrypt certificates to docker directory
	@echo "📋 Copying certificates..."
	sudo cp /etc/letsencrypt/live/acs.example.com/fullchain.pem docker/nginx/ssl/cert.pem
	sudo cp /etc/letsencrypt/live/acs.example.com/privkey.pem docker/nginx/ssl/key.pem
	sudo chmod 644 docker/nginx/ssl/*.pem
	@echo "✅ Certificates copied"

##@ Information

version: ## Show application version
	docker-compose exec $(APP_CONTAINER) php artisan --version

env-check: ## Verify environment configuration
	@echo "🔍 Checking environment configuration..."
	@if [ ! -f .env ]; then \
		echo "❌ .env file not found!"; \
		exit 1; \
	fi
	@echo "✅ .env file exists"
	@grep -q "^APP_KEY=" .env && echo "✅ APP_KEY configured" || echo "❌ APP_KEY missing"
	@grep -q "^DB_PASSWORD=" .env && echo "✅ DB_PASSWORD configured" || echo "❌ DB_PASSWORD missing"
	@grep -q "^REDIS_PASSWORD=" .env && echo "✅ REDIS_PASSWORD configured" || echo "❌ REDIS_PASSWORD missing"
