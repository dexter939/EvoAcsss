#!/bin/bash
set -e

# CRITICAL: Session cookies configuration for Replit iframe
# Chrome 2025+ requires Partitioned cookies (CHIPS standard) for third-party contexts
export SESSION_DRIVER=database
export SESSION_SAME_SITE=none
export SESSION_SECURE_COOKIE=true
export SESSION_PARTITIONED_COOKIE=true

php artisan config:clear
php artisan serve --host=0.0.0.0 --port=5000
