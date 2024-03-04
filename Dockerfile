FROM php:8.2-cli-alpine

RUN apk --no-cache add \
    $PHPIZE_DEPS \
    openssl-dev \
    mysql-client \
    && docker-php-ext-install pdo_mysql \
    && pecl install openswoole \
    && docker-php-ext-enable openswoole

WORKDIR /app

COPY . /app

EXPOSE 9501

CMD ["php", "/app/index.php"]
