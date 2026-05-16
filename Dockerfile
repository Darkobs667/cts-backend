# Utiliser l'image officielle PHP 8.3 avec Apache
FROM php:8.3-apache

# Activer mod_rewrite
RUN a2enmod rewrite

# Copier la configuration Apache
COPY config/apache.conf /etc/apache2/sites-available/000-default.conf

# Installer les extensions nécessaires
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring zip

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .

# Installer les dépendances PHP
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Créer le lien symbolique pour le stockage
RUN php artisan storage:link

# Configurer les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Configurer le DocumentRoot
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

# Créer un script de démarrage avec health check (CORRIGÉ - suppression de --timeout)
RUN echo '#!/bin/bash\n\
echo "🚀 Démarrage du backend..."\n\
\n\
# Forcer le cache config\n\
php artisan config:clear\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
# Lancer les migrations\n\
php artisan migrate --force\n\
\n\
# Démarrer le serveur\n\
php artisan serve --host=0.0.0.0 --port=${PORT:-10000}\n\
' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Exposer le port
EXPOSE 10000

# Commande de démarrage
CMD ["/usr/local/bin/start.sh"]