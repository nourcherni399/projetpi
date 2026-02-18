# Force PHP 8.4 for Railway (Railpack uses 8.5 by default, incompatible with dompdf deps)
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) zip gd pdo pdo_mysql opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .

# Symfony post-install
RUN php bin/console assets:install public --no-interaction 2>/dev/null || true
RUN php bin/console importmap:install --no-interaction 2>/dev/null || true

EXPOSE 8000
ENV PORT=8000
# Migrations + cache run in subshell so PHP fatals don't kill container; server always starts
CMD ["sh", "-c", "(php bin/console doctrine:migrations:migrate --no-interaction 2>/dev/null) || true; (php bin/console cache:clear --env=prod 2>/dev/null) || true; exec php -d variables_order=EGPCS -S 0.0.0.0:${PORT:-8000} -t public"]
