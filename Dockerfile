# syntax=docker/dockerfile:1
FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl git unzip mariadb-client util-linux \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libicu-dev libonig-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath exif ftp gd intl mbstring opcache pcntl pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --no-dev --no-scripts --optimize \
    && mkdir -p storage/app/server-storage storage/app/locks storage/framework/cache/data \
       storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && printf 'INSTALLED=true\n' > .env \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/filevault.ini
COPY docker/container-entrypoint.sh /usr/local/bin/filevault-entrypoint
COPY docker/container-backup.sh /usr/local/bin/filevault-backup
COPY docker/container-backup-loop.sh /usr/local/bin/filevault-backup-loop
COPY docker/container-backup-health.sh /usr/local/bin/filevault-backup-health
COPY docker/health.php /usr/local/bin/filevault-health
RUN chmod +x /usr/local/bin/filevault-*
EXPOSE 80
ENTRYPOINT ["filevault-entrypoint"]
CMD ["apache2-foreground"]
