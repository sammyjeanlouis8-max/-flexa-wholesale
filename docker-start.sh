#!/usr/bin/env bash
set -euo pipefail

case "${SESSION_DRIVER:-}" in
    ""|file)
        export SESSION_DRIVER=database
        ;;
esac

echo "Using Laravel session driver: $SESSION_DRIVER"
echo "Clearing cached Laravel configuration..."
php artisan config:clear

echo "Applying pending database migrations..."
php artisan migrate --force --no-interaction

echo "Starting Apache..."
exec apache2-foreground
