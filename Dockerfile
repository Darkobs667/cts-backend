# Utiliser l'image officielle PHP 8.3 avec Apache
FROM php:8.3-apache
RUN a2enmod rewrite
COPY config/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Installer les extensions nécessaires
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql mbstring zip

# Activer mod_rewrite pour Apache
RUN a2enmod rewrite

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

# Exposer le port (Render utilisera la variable PORT, mais Apache utilise 80 par défaut)
EXPOSE 80

# Commande de démarrage (avec migration automatique)
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]