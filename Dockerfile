FROM php:8.4-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql bcmath\
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock* ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

COPY . .
RUN composer dump-autoload --optimize
RUN chown -R www-data:www-data /var/www/html

# Prepara o php.ini de produção e aumenta limites para arquivos de áudio
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN echo "upload_max_filesize = 50M\n\
    post_max_size = 50M\n\
    max_execution_time = 120\n\
    memory_limit = 256M" >> $PHP_INI_DIR/conf.d/custom-uploads.ini

ENV PORT=8080
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
RUN sed -i "s!/var/www/html!/var/www/html/public!g" /etc/apache2/sites-available/000-default.conf

RUN echo "<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
    FallbackResource /index.php\n\
    CGIPassAuth On\n\
    </Directory>" >> /etc/apache2/apache2.conf

EXPOSE 8080