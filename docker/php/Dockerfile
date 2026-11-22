# Development image for monStudio (PHP 8.4 + Apache).
FROM php:8.4-apache

# pdo_mysql and opcache ship with the PHP sources: no system packages needed.
# intl is optional (Symfony polyfills it; dates are formatted by App\Twig\FrenchDateExtension).
RUN docker-php-ext-install pdo_mysql opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-monstudio.ini

WORKDIR /var/www/html
