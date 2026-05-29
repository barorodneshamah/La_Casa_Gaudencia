FROM php:8.3-fpm AS php

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    nginx \
    supervisor \
    gettext-base \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libsodium-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        intl \
        zip \
        mbstring \
        gd \
        sodium \
        opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu opcache \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# PHP configuration
COPY docker/php.ini $PHP_INI_DIR/conf.d/app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf

# Supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copy application files
COPY . .

# Symfony requires .env to exist even in prod (actual values come from Railway env vars)
RUN touch .env

# Install WebSocket server dependencies
RUN cd websocket-server && npm install --omit=dev

# Install PHP dependencies
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && rm /usr/bin/composer

# Set permissions
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data /var/www/html/var /var/www/html/public \
    && chmod +x bin/console

# Copy entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Nginx — site config stored as template; PORT is substituted at startup
COPY nginx.conf /etc/nginx/sites-available/default.template
RUN rm -f /etc/nginx/sites-enabled/default

# Main nginx.conf
COPY nginx-main.conf /etc/nginx/nginx.conf

ENV APP_ENV=prod
ENV APP_DEBUG=0

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]