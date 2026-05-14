# Utiliser l'image officielle PHP 8.3 avec Apache
FROM php:8.3-apache

COPY config/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Installer les extensions nécessaires
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .
# Installer les dépendances PHP (d'abord !)
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# Créer le lien symbolique pour le stockage (après composer)
RUN php artisan storage:link


# Configurer les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache


# Configurer le DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

# Exposer le port
EXPOSE 80

CMD sh -c "php artisan route:clear && php artisan config:cache && php artisan migrate --force && apache2-foreground"