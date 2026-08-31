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

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

EXPOSE 80
CMD ["apache2-foreground"]