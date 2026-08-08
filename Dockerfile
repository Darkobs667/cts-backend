# Utiliser l'image officielle PHP 8.3 avec Apache
FROM php:8.3-apache

# Activer mod_rewrite
RUN a2enmod rewrite

# Installer les extensions nécessaires
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql mbstring zip

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .

# Installer les dépendances PHP
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Configurer les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Configurer le DocumentRoot vers /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Augmenter les timeouts PHP
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/timeout.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/timeout.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/memory.ini

# Configurer Apache timeout
RUN echo "Timeout 300" >> /etc/apache2/apache2.conf \
    && echo "KeepAlive On" >> /etc/apache2/apache2.conf \
    && echo "KeepAliveTimeout 5" >> /etc/apache2/apache2.conf \
    && echo "MaxKeepAliveRequests 100" >> /etc/apache2/apache2.conf

# Render injecte PORT à l'exécution. Le script de démarrage configure Apache.
EXPOSE 10000

RUN chmod +x /var/www/html/docker/start.sh

# Commande de démarrage
CMD ["/var/www/html/docker/start.sh"]
