FROM php:8.4-apache

# Habilita o mod_rewrite do Apache (necessário para rotas e APIs)
RUN a2enmod rewrite

# Instala dependências do SO e extensões do PHP
RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copia o Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copia os arquivos de dependência primeiro
COPY composer.json composer.lock* ./

# Instala as dependências
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

# Copia o resto do código
COPY . .
RUN composer dump-autoload --optimize

# Ajusta as permissões para o Apache
RUN chown -R www-data:www-data /var/www/html

# Configura o Apache para usar a variável $PORT do Cloud Run 
# e aponta a raiz do servidor para a pasta /public
ENV PORT=8080
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
RUN sed -i "s!/var/www/html!/var/www/html/public!g" /etc/apache2/sites-available/000-default.conf

# Garante que qualquer rota da sua API seja redirecionada para o index.php
RUN echo "<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
    FallbackResource /index.php\n\
    </Directory>" >> /etc/apache2/apache2.conf

EXPOSE 8080