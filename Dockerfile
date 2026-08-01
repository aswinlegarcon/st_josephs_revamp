FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql exif \
    && rm -rf /var/lib/apt/lists/*

# Make .htaccess actually apply in dev (Debian ships AllowOverride None and the
# header/expires modules disabled). Prod hosts honour .htaccess already; this
# just brings the dev container in line so S4/F1 rules can be tested locally.
RUN a2enmod headers expires rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Composer for dev only (regenerate the autoloader with `composer dump-autoload`).
# vendor/ is committed, so production never needs Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN { \
        echo 'upload_max_filesize=12M'; \
        echo 'post_max_size=13M'; \
        echo 'memory_limit=512M'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
    } > /usr/local/etc/php/conf.d/stjosephs.ini
