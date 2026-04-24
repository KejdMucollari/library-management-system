FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git curl zip unzip libzip-dev \
    libpng-dev libonig-dev libxml2-dev \
    libcurl4-openssl-dev \
    nginx \
    && docker-php-ext-install pdo_mysql mbstring xml curl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

WORKDIR /app

COPY . .

RUN composer install --optimize-autoloader --no-scripts --no-interaction

RUN npm ci && npm run build

RUN mkdir -p storage/framework/{sessions,views,cache,testing} \
    storage/logs bootstrap/cache \
    && chmod -R a+rw storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/sites-available/default

EXPOSE 8080

CMD mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache \
    && chmod -R a+rw storage bootstrap/cache \
    && php artisan migrate --force \
    && php artisan db:seed --class=AdminSeeder --force \
    && php-fpm -D \
    && nginx -g "daemon off;"
