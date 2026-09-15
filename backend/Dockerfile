FROM php:8.4-fpm-alpine

ARG AMQP_VERSION=2.2.0

RUN apk add --no-cache \
    git \
    unzip \
    curl \
    bash \
    netcat-openbsd \
    libpq \
    rabbitmq-c \
    rabbitmq-c-dev \
    libxml2-dev \
    sqlite-libs \
    sqlite-dev \
    $PHPIZE_DEPS \
    postgresql-dev \
    && mkdir -p /tmp/amqp \
    && curl -fsSL "https://pecl.php.net/get/amqp-${AMQP_VERSION}.tgz" \
        | tar -xz -C /tmp/amqp --strip-components=1 \
    && cd /tmp/amqp \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable amqp \
    && cd / \
    && rm -rf /tmp/amqp \
    && docker-php-ext-install -j"$(nproc)" dom xml xmlwriter pdo pdo_pgsql pdo_sqlite opcache \
    && apk del $PHPIZE_DEPS postgresql-dev rabbitmq-c-dev libxml2-dev sqlite-dev

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
