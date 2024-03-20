FROM php:8.3-cli-alpine

RUN apk --no-cache add \
    $PHPIZE_DEPS \
    openssl-dev \
    postgresql-dev \
    && docker-php-ext-install pdo_pgsql pcntl \
    && pecl install openswoole \
    && docker-php-ext-enable openswoole pcntl

WORKDIR /app

COPY . /app

EXPOSE 8000 8001

CMD ["php", "/app/index.php"]
