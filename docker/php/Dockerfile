FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libzip-dev libpng-dev poppler-utils \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && apt-get clean

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN pecl install redis && docker-php-ext-enable redis

WORKDIR /var/www/app
