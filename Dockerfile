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