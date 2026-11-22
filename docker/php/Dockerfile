# Development image for monStudio (PHP 8.4 + Apache).
FROM php:8.4-apache

# pdo_mysql and opcache ship with the PHP sources: no system packages needed.
RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1

RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-monstudio.ini

WORKDIR /var/www/html
