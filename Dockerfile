FROM php:8.2-cli-alpine

RUN apk --no-cache add \
    $PHPIZE_DEPS \
    openssl-dev \
    postgresql-dev \
    && docker-php-ext-install pdo_pgsql \
    && pecl install openswoole \
    && docker-php-ext-enable openswoole

WORKDIR /app

COPY . /app

EXPOSE 9501

CMD ["php", "/app/index.php"]
