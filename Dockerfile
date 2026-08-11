FROM php:8.3-fpm-alpine

# Nginx sert les fichiers publics et transmet PHP à PHP-FPM.
RUN apk add --no-cache \
        nginx \
        bash \
        curl \
        git \
        unzip \
        zip \
        libpq \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        xml \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .

RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p /run/nginx /var/www/html/storage/app/public \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R ug=rwx /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/php-fpm-render.conf /usr/local/etc/php-fpm.d/zz-render.conf

RUN printf '%s\n' \
      'expose_php = Off' \
      'memory_limit = 256M' \
      'upload_max_filesize = 10M' \
      'post_max_size = 10M' \
      'max_execution_time = 60' \
      > /usr/local/etc/php/conf.d/render.ini \
    && chmod +x /var/www/html/docker/start.sh

EXPOSE 10000

CMD ["/var/www/html/docker/start.sh"]
