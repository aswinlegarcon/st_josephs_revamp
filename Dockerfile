FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql exif \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo 'upload_max_filesize=12M'; \
        echo 'post_max_size=13M'; \
        echo 'memory_limit=512M'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
    } > /usr/local/etc/php/conf.d/stjosephs.ini
