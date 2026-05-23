FROM php:8.2-cli-alpine

RUN apk add --no-cache git unzip \
    && docker-php-ext-install pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || composer install --no-interaction

COPY . .

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
