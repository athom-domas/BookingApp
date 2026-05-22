FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    curl \
    wget \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    jpeg-dev \
    libzip-dev \
    icu-dev \
    nodejs \
    npm \
    supervisor \
    && rm -rf /var/cache/apk/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install \
    pdo \
    pdo_mysql \
    gd \
    zip \
    bcmath \
    intl \
    exif \
    && docker-php-ext-enable pdo pdo_mysql gd zip bcmath intl exif

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install --no-interaction --prefer-dist --no-scripts

COPY . .

RUN npm install

RUN mkdir -p /app/storage/logs && \
    chmod -R 775 /app/storage /app/bootstrap/cache

RUN php artisan key:generate --force || true

EXPOSE 8000 9000

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
