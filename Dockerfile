# ACS (Auto Configuration Server) - Production Dockerfile
# Multi-stage build for optimized Laravel 11 application

# Stage 1: Build dependencies
FROM php:8.3-fpm-alpine AS builder

# Install build dependencies
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    postgresql-dev \
    autoconf \
    g++ \
    make

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    soap \
    opcache \
    pcntl \
    bcmath

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (production only)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# Optimize Laravel for production
RUN php artisan config:cache || true && \
    php artisan route:cache || true && \
    php artisan view:cache || true

# Stage 2: Production runtime
FROM php:8.3-fpm-alpine

# Install runtime dependencies only
RUN apk add --no-cache \
    libzip \
    libpng \
    libxml2 \
    postgresql-libs \
    curl \
    nginx \
    supervisor

# Install PHP extensions (runtime)
RUN apk add --no-cache --virtual .build-deps \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    postgresql-dev \
    autoconf \
    g++ \
    make && \
    docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    soap \
    opcache \
    pcntl \
    bcmath && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del .build-deps

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/acs.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Create non-root user
RUN addgroup -g 1000 acs && \
    adduser -D -u 1000 -G acs acs

# Set working directory
WORKDIR /var/www/html

# Copy application from builder
COPY --from=builder --chown=acs:acs /var/www/html /var/www/html

# Create necessary directories
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache && \
    chown -R acs:acs storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Expose ports
EXPOSE 9000 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD php artisan system:health || exit 1

# Start supervisor as root (nginx needs to bind privileged ports)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
