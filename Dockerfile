# syntax=docker/dockerfile:1

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM dunglas/frankenphp:php8.3 AS app
WORKDIR /app

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pcntl \
    bcmath \
    intl \
    zip \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE 8080

CMD ["frankenphp", "php-server", "--listen", ":8080", "-r", "public/"]
