# PHP + Apache + Ghostscript + ZIP (extensão) para compressão de PDFs
FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        ghostscript \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/zz-app.conf /etc/apache2/conf-available/zz-app.conf
RUN a2enconf zz-app

COPY docker/php/conf.d/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads/temp /var/www/html/uploads/compressed \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R ug+rwX /var/www/html/uploads

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -sf http://127.0.0.1/ >/dev/null || exit 1
