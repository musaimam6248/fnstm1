FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mbstring pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html
COPY config/php.ini /usr/local/etc/php/conf.d/99-uploads.ini
COPY docker-entrypoint.sh /usr/local/bin/fnstm-entrypoint
RUN chmod +x /usr/local/bin/fnstm-entrypoint \
    && mkdir -p /var/www/html/database/sessions /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/database/sessions /var/www/html/uploads

EXPOSE 10000
ENTRYPOINT ["fnstm-entrypoint"]
