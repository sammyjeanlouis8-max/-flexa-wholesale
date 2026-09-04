FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        mbstring \
        pdo_mysql \
        xml \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# The source archive contains the original, correctly encoded Laravel files.
RUN if [ -f hosting-assets/flexa-source.tar.gz ]; then \
        tar -xzf hosting-assets/flexa-source.tar.gz -C /var/www/html; \
    fi \
    && bash restore-hosting-assets.sh \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# DigitalOcean MySQL requires every table to have a primary key.
# Apply this after restoring the hosting archive, which contains the legacy migration.
RUN migration="database/migrations/2014_10_12_100000_create_password_resets_table.php"; \
    if ! grep -qF '$table->id();' "$migration"; then \
        sed -i "/Schema::create('password_resets'/a\\            \$table->id();" "$migration"; \
    fi

RUN migration="database/migrations/2024_08_04_115138_create_product_image_table.php"; \
    if ! grep -qF '$table->id();' "$migration"; then \
        sed -i "/Schema::create('product_image'/a\\            \$table->id();" "$migration"; \
    fi

RUN migration="database/migrations/2014_10_12_000000_create_users_table.php"; \
    if ! grep -Eq 'primary|increments|->id\(' "$migration"; then \
        sed -i "/Schema::create('users'/a\\            \$table->id();" "$migration"; \
    fi

RUN migration="database/migrations/2026_08_31_000001_create_permissions_tables.php"; \
    sed -i "/Schema::create('permission_user'/a\\            \$table->id();" "$migration"; \
    sed -i "s/\\\$table->primary(\\['permission_id', 'user_id'\\]);/\\\$table->unique(['permission_id', 'user_id']);/" "$migration"

RUN migration="database/migrations/2026_09_01_000000_add_marketplace_capabilities_and_activity_logs.php"; \
    sed -i "/Schema::create('capability_user'/a\\            \$table->id();" "$migration"; \
    sed -i "s/\\\$table->primary(\\['capability_id', 'user_id'\\]);/\\\$table->unique(['capability_id', 'user_id']);/" "$migration"

RUN migration="database/migrations/2026_09_02_000001_create_countries_and_product_availability.php"; \
    sed -i "/Schema::create('product_country_availability'/a\\                \$table->id();" "$migration"; \
    sed -i "s/\\\$table->primary(\\['product_id', 'country_id'\\]);/\\\$table->unique(['product_id', 'country_id']);/" "$migration"

RUN migration="database/migrations/2026_09_06_000000_add_seller_profile_location_and_availability.php"; \
    sed -i "/Schema::create('vendor_country_availability'/a\\                \$table->id();" "$migration"; \
    sed -i "s/\\\$table->primary(\\['vendor_id', 'country_id'\\]);/\\\$table->unique(['vendor_id', 'country_id']);/" "$migration"

RUN migration="database/migrations/2026_09_01_000001_add_marketplace_interaction_tables.php"; \
    sed -i "s/\\\$table->uuid('id')->primary();/\\\$table->id();/" "$migration"

RUN php -r '$file = "app/Providers/AppServiceProvider.php"; \
    $source = str_replace("\r\n", "\n", file_get_contents($file)); \
    if (strpos($source, "URL::forceScheme") === false) { \
        $source = str_replace("use Illuminate\\Support\\Facades\\View;\n", "use Illuminate\\Support\\Facades\\View;\nuse Illuminate\\Support\\Facades\\URL;\n", $source); \
        $source = str_replace("    public function boot()\n    {\n", "    public function boot()\n    {\n        if (\$this->app->environment(\"production\")) {\n            URL::forceScheme(\"https\");\n        }\n\n", $source); \
    } \
    file_put_contents($file, $source);' \
    && php -l app/Providers/AppServiceProvider.php


# Run installer migrations safely in production.
RUN php -r '$file = "app/Http/Controllers/InstallController.php"; \
    $source = file_get_contents($file); \
    $source = str_replace("Artisan::call(\"migrate\");", "Artisan::call(\"migrate\", [\"--force\" => true, \"--no-interaction\" => true]);", $source); \
    $source = str_replace("catch (Exception \$e)", "catch (\\Throwable \$e)", $source); \
    file_put_contents($file, $source);' \
    && php -l app/Http/Controllers/InstallController.php

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel \
    && chmod +x /var/www/html/docker-start.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

EXPOSE 80
CMD ["/var/www/html/docker-start.sh"]