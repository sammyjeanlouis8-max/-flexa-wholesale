# FLEXA WHOLESALE hosting setup

The complete public assets are stored in split compressed archives under `hosting-assets`.

Run these commands from the repository root during deployment:

    bash restore-hosting-assets.sh
    composer install --no-dev --optimize-autoloader
    cp .env.example .env
    php artisan key:generate
    php artisan migrate --force

Set production database and application values in the hosting provider environment settings. Never commit a real `.env` file.

Point the web server document root to `public` and ensure `storage` and `bootstrap/cache` are writable.
