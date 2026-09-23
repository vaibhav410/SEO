# SYSCOM GrowthHub - single-container demo image: Apache + PHP 8.3 + MariaDB (MySQL-compatible).
# The database lives inside the container and is re-seeded with demo data on every start.
# For production, point DB_HOST at a managed MySQL instead and drop the bundled server.
FROM php:8.3-apache-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends mariadb-server libicu-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql intl gd opcache \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers deflate expires \
 && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf && a2enconf servername \
 && echo 'ServerTokens Prod' >> /etc/apache2/conf-available/security.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-growthhub.ini
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/assets/uploads \
 && chmod +x /var/www/html/docker/entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
