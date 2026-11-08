# Image applicative Parema Connect (Laravel + outils de build front).
# Sert pour l'app web, la file, le planificateur et Reverb (même binaire).
FROM php:8.4-cli-bookworm

# Dépendances système pour les extensions PHP + Node (build des assets).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates \
        libpq-dev libzip-dev libicu-dev libonig-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql zip intl bcmath gd exif pcntl mbstring \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (copié depuis l'image officielle).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Un peu plus de marge pour composer/artisan.
RUN { echo "memory_limit=512M"; echo "upload_max_filesize=20M"; echo "post_max_size=25M"; } \
    > /usr/local/etc/php/conf.d/parema.ini

WORKDIR /var/www/html

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
