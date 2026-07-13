FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git \
    unzip \
    curl \
    bash \
    netcat-openbsd \
    libpq \
    rabbitmq-c \
    rabbitmq-c-dev \
    $PHPIZE_DEPS \
    postgresql-dev \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && docker-php-ext-install pdo pdo_pgsql opcache \
    && apk del $PHPIZE_DEPS postgresql-dev rabbitmq-c-dev

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --no-interaction

COPY . .
RUN composer dump-autoload --optimize

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
