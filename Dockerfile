FROM php:8.4-cli-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

COPY . .

RUN composer dump-autoload --optimize

EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public"]