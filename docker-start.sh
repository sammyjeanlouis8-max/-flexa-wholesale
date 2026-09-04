#!/usr/bin/env bash
set -euo pipefail

echo "Clearing cached Laravel configuration..."
php artisan config:clear

echo "Applying pending database migrations..."
php artisan migrate --force --no-interaction

echo "Starting Apache..."
exec apache2-foreground
